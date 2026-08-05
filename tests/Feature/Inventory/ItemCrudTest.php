<?php

namespace Tests\Feature\Inventory;

use App\Enums\BusinessType;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use App\Models\Inventory\StockMovement;
use App\Support\BusinessProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Item CRUD, variants and business-type shaping.
 *
 * Each of these covers a defect that reached the database because there was no
 * test here: uniqueness that never fired, a delete that reported success
 * without deleting, and a variant key built from the whole row.
 */
class ItemCrudTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Paracetamol 500mg',
            'code' => '9001',
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'income_account_id' => $this->ctx['accounts']['sales-revenue']->id,
            'cost_account_id' => $this->ctx['accounts']['cost-of-goods-sold']->id,
            'sale_price' => 120,
            'purchase_price' => 100,
            'variants' => [[
                'attributes' => [],
                'sku' => 'PARA-500',
                'barcode' => '5000001',
                'sale_price' => 120,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ]],
        ], $overrides);
    }

    // ---------------------------------------------------------------- create

    public function test_it_creates_an_item_with_its_default_variant(): void
    {
        $response = $this->post(route('items.store'), $this->payload());

        $response->assertRedirect();

        $item = Item::where('code', '9001')->firstOrFail();

        $this->assertSame('Paracetamol 500mg', $item->name);
        $this->assertCount(1, $item->variants);

        $variant = $item->variants->first();
        $this->assertTrue($variant->is_default);
        $this->assertSame('PARA-500', $variant->sku);
        $this->assertSame('5000001', $variant->barcode);
    }

    public function test_every_item_gets_a_variant_even_when_none_is_submitted(): void
    {
        $payload = $this->payload();
        unset($payload['variants']);

        $this->post(route('items.store'), $payload)->assertRedirect();

        $item = Item::where('code', '9001')->firstOrFail();

        // variant_id must never be null downstream, so a trade with no choices
        // still gets exactly one row.
        $this->assertCount(1, $item->variants);
        $this->assertTrue($item->variants->first()->is_default);
    }

    public function test_it_creates_multiple_variants_with_attributes(): void
    {
        $this->post(route('items.store'), $this->payload([
            'name' => 'T-Shirt',
            'code' => '9002',
            'variants' => [
                ['attributes' => ['color' => 'Red', 'size' => 'L'], 'sku' => 'TS-RD-L', 'is_default' => true, 'sort_order' => 0],
                ['attributes' => ['color' => 'Blue', 'size' => 'M'], 'sku' => 'TS-BL-M', 'is_default' => false, 'sort_order' => 1],
            ],
        ]))->assertRedirect();

        $item = Item::where('code', '9002')->firstOrFail();
        $this->assertCount(2, $item->variants);

        // The key is canonical: sorted and lower-cased, not the raw JSON and
        // definitely not the whole row.
        $keys = $item->variants->pluck('variant_key')->sort()->values()->all();
        $this->assertSame(['color=blue|size=m', 'color=red|size=l'], $keys);
    }

    public function test_variant_key_is_order_and_case_independent(): void
    {
        $this->assertSame(
            ItemVariant::makeKey(['ram' => '16GB', 'color' => 'Black']),
            ItemVariant::makeKey(['color' => 'black', 'ram' => '16gb']),
        );

        $this->assertSame('default', ItemVariant::makeKey([]));
    }

    // ------------------------------------------------------------ validation

    public function test_duplicate_name_is_rejected(): void
    {
        $this->post(route('items.store'), $this->payload())->assertRedirect();

        // The old rule tested `branch_id IS NULL` and never matched, so a
        // duplicate reached the database and raised a 500 instead.
        $this->post(route('items.store'), $this->payload(['code' => '9999']))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Item::where('name', 'Paracetamol 500mg')->count());
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $this->post(route('items.store'), $this->payload())->assertRedirect();

        $this->post(route('items.store'), $this->payload(['name' => 'Something else']))
            ->assertSessionHasErrors('code');
    }

    public function test_two_variants_with_the_same_attributes_are_rejected(): void
    {
        $this->post(route('items.store'), $this->payload([
            'variants' => [
                ['attributes' => ['color' => 'Red'], 'is_default' => true],
                ['attributes' => ['color' => 'red'], 'is_default' => false],
            ],
        ]))->assertSessionHasErrors('variants.1.attributes');
    }

    public function test_duplicate_variant_sku_within_the_payload_is_rejected(): void
    {
        $this->post(route('items.store'), $this->payload([
            'variants' => [
                ['attributes' => ['color' => 'Red'], 'sku' => 'DUP', 'is_default' => true],
                ['attributes' => ['color' => 'Blue'], 'sku' => 'DUP', 'is_default' => false],
            ],
        ]))->assertSessionHasErrors('variants.1.sku');
    }

    public function test_opening_quantity_requires_a_price_and_warehouse(): void
    {
        // `required_with:openings.*.quantity>0` was not a valid rule and never
        // fired, so openings could be saved with no price at all.
        $this->post(route('items.store'), $this->payload([
            'openings' => [['quantity' => 5]],
        ]))->assertSessionHasErrors([
            'openings.0.unit_price',
            'openings.0.warehouse_id',
        ]);
    }

    // ---------------------------------------------------------------- update

    public function test_it_updates_an_item_without_conflicting_with_itself(): void
    {
        $this->post(route('items.store'), $this->payload())->assertRedirect();
        $item = Item::where('code', '9001')->firstOrFail();
        $variant = $item->variants->first();

        // The update request used to reuse the store rule for `sku` with no
        // ignore(), so saving an unchanged item failed against its own row.
        $this->put(route('items.update', $item), $this->payload([
            'sale_price' => 150,
            'variants' => [[
                'id' => $variant->id,
                'attributes' => [],
                'sku' => 'PARA-500',
                'barcode' => '5000001',
                'sale_price' => 150,
                'is_default' => true,
                'is_active' => true,
            ]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('150.0000', $item->fresh()->variants->first()->sale_price);
    }

    public function test_removing_a_variant_from_the_payload_removes_it(): void
    {
        $this->post(route('items.store'), $this->payload([
            'variants' => [
                ['attributes' => ['color' => 'Red'], 'is_default' => true],
                ['attributes' => ['color' => 'Blue'], 'is_default' => false],
            ],
        ]))->assertRedirect();

        $item = Item::where('code', '9001')->firstOrFail();
        $this->assertCount(2, $item->variants);

        $this->put(route('items.update', $item), $this->payload([
            'variants' => [['attributes' => ['color' => 'Red'], 'is_default' => true]],
        ]))->assertSessionHasNoErrors();

        $this->assertCount(1, $item->fresh()->variants);
    }

    // --------------------------------------------------------------- destroy

    public function test_it_deletes_an_item_with_no_posted_stock(): void
    {
        $this->post(route('items.store'), $this->payload())->assertRedirect();
        $item = Item::where('code', '9001')->firstOrFail();

        $this->delete(route('items.destroy', $item))->assertRedirect();

        $this->assertSoftDeleted('items', ['id' => $item->id]);
    }

    public function test_it_refuses_to_delete_an_item_with_posted_stock(): void
    {
        $this->post(route('items.store'), $this->payload())->assertRedirect();
        $item = Item::where('code', '9001')->firstOrFail();

        StockMovement::create([
            'item_id' => $item->id,
            'branch_id' => $this->ctx['branch']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'movement_type' => StockMovementType::IN->value,
            'source' => StockSourceType::PURCHASE->value,
            'quantity' => 10,
            'unit_cost' => 100,
            'qty_remaining' => 10,
            'date' => now()->toDateString(),
            'status' => StockStatus::POSTED->value,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->delete(route('items.destroy', $item))->assertSessionHas('error');

        // The guard used to `return` inside a transaction closure, so the value
        // was discarded and the user was told the item had been deleted.
        $this->assertDatabaseHas('items', ['id' => $item->id, 'deleted_at' => null]);
    }

    // -------------------------------------------------- business type shaping

    public function test_the_profile_shapes_which_fields_a_trade_sees(): void
    {
        $company = $this->ctx['company'];

        $company->update(['business_type' => BusinessType::PHARMACY->value, 'preferences' => null]);
        $pharmacy = BusinessProfile::for($company->fresh());

        $company->update(['business_type' => BusinessType::MOBILE->value]);
        $mobile = BusinessProfile::for($company->fresh());

        $this->assertTrue($pharmacy->showsField('requires_prescription'));
        $this->assertFalse($mobile->showsField('requires_prescription'));

        $this->assertTrue($mobile->showsField('warranty_months'));
        $this->assertFalse($pharmacy->showsField('warranty_months'));

        $this->assertTrue($pharmacy->showsSection('lots'));
        $this->assertFalse($mobile->showsSection('lots'));

        $this->assertTrue($mobile->showsSection('pieces'));
        $this->assertFalse($pharmacy->showsSection('pieces'));

        $this->assertSame('batch', $pharmacy->specLabel());
    }

    public function test_a_supermarket_tracks_expiry_without_a_batch_number(): void
    {
        $company = $this->ctx['company'];
        $company->update(['business_type' => BusinessType::SUPERMARKET->value, 'preferences' => null]);

        $profile = BusinessProfile::for($company->fresh());

        // Milk and bread carry a printed date and no lot code, so the two
        // flags must stay independent rather than collapse into one enum.
        $this->assertTrue($profile->defaults()['is_expiry_tracked']);
        $this->assertFalse($profile->defaults()['is_batch_tracked']);
        $this->assertSame('expiry', $profile->specLabel());
    }

    public function test_company_preferences_override_the_trade_profile(): void
    {
        $company = $this->ctx['company'];
        $company->update([
            'business_type' => BusinessType::SUPERMARKET->value,
            'preferences' => [
                'item_management' => [
                    'visible_fields' => ['generic_name' => true],
                    'spec_text' => 'lot',
                ],
            ],
        ]);

        $profile = BusinessProfile::for($company->fresh());

        $this->assertTrue($profile->showsField('generic_name'));
        $this->assertSame('lot', $profile->specLabel());
        // Untouched keys still come from the profile.
        $this->assertTrue($profile->showsField('shelf_life_days'));
    }
}
