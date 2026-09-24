<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Http\Controllers\Concerns\TogglesRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\DiscountRuleRequest;
use App\Http\Resources\Inventory\DiscountRuleResource;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use App\Models\Administration\CustomerGroup;
use App\Models\Inventory\DiscountRule;
use App\Models\Inventory\Item;
use App\Models\Sale\Sale;
use App\Services\DateConversionService;
use App\Services\DiscountRuleResolver;
use App\Support\BranchContext;
use Illuminate\Http\Request;

class DiscountRuleController extends Controller
{
    use TogglesRecordStatus;

    public function __construct()
    {
        $this->authorizeResource(DiscountRule::class, 'discount_rule');
    }

    public function index(Request $request)
    {
        $rules = DiscountRule::query()
            ->with('customerGroup')
            ->search($request->query('search'))
            ->orderByDesc('is_active')
            ->orderByDesc('is_priority')
            ->orderBy('name')
            ->paginate($request->integer('perPage', recordsPerPage()))
            ->withQueryString();

        return inertia('Inventories/DiscountRules/Index', [
            // A resource collection, not the raw paginator: DataTable reads
            // items.meta.* for its pagination footer.
            'discountRules' => DiscountRuleResource::collection($rules),
            'options' => [
                'scopes' => collect(DiscountScope::cases())
                    ->map(fn ($case) => ['id' => $case->value, 'name' => $case->getLabel()])->values(),
                'discountTypes' => collect(DiscountType::cases())
                    ->map(fn ($case) => ['id' => $case->value, 'name' => $case->getLabel()])->values(),
                'items' => Item::query()->orderBy('name')->get(['id', 'name']),
                'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'local_name']),
                'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
                'customerGroups' => CustomerGroup::query()->orderBy('name_en')->get(['id', 'name_en', 'local_name']),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $request->integer('perPage', recordsPerPage()),
            ],
        ]);
    }

    /**
     * The rules a sale line could pick up, for the items on the form.
     *
     * The sale screen prefills each line's discount itself so the salesperson
     * can see it and change it before saving. It asks once per item and then
     * re-picks locally as the quantity changes, so the response carries every
     * candidate — best first, quantity gate open — rather than one amount.
     */
    public function forItems(Request $request)
    {
        // Whoever can write a sale needs to see the discounts that sale earns;
        // that is a different thing from being allowed to edit the rules.
        $this->authorize('create', Sale::class);

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'max:200'],
            'item_ids.*' => ['string'],
            'customer_id' => ['nullable', 'string'],
            'date' => ['nullable', 'string'],
        ]);

        $resolver = app(DiscountRuleResolver::class);
        $date = filled($validated['date'] ?? null)
            ? app(DateConversionService::class)->toGregorian($validated['date'])
            : null;

        $rules = collect($validated['item_ids'])
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $itemId) => [
                $itemId => $resolver->candidatesFor(
                    itemId: $itemId,
                    ledgerId: $validated['customer_id'] ?? null,
                    branchId: BranchContext::branchId(),
                    onDate: $date,
                )->map(fn (DiscountRule $rule) => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'discount_type' => $rule->discount_type->value,
                    'value' => (float) $rule->value,
                    'min_quantity' => $rule->min_quantity === null ? null : (float) $rule->min_quantity,
                    'show_on_invoice' => (bool) $rule->show_on_invoice,
                ])->all(),
            ]);

        return response()->json(['data' => $rules]);
    }

    public function store(DiscountRuleRequest $request)
    {
        DiscountRule::create($this->withGregorianDates($request->validated()));

        return back()->with('success', __('general.created_successfully', ['resource' => __('discount_rule.discount_rule')]));
    }

    public function update(DiscountRuleRequest $request, DiscountRule $discountRule)
    {
        $discountRule->update($this->withGregorianDates($request->validated()));

        return back()->with('success', __('general.updated_successfully', ['resource' => __('discount_rule.discount_rule')]));
    }

    /**
     * The date picker posts in the user's own calendar, so a Jalali user would
     * otherwise store year 1405 as if it were Gregorian.
     */
    private function withGregorianDates(array $validated): array
    {
        $dates = app(DateConversionService::class);

        foreach (['starts_at', 'ends_at'] as $field) {
            $validated[$field] = filled($validated[$field] ?? null)
                ? $dates->toGregorian($validated[$field])
                : null;
        }

        return $validated;
    }

    public function destroy(DiscountRule $discountRule)
    {
        $discountRule->delete();

        return back()->with('success', __('general.deleted_successfully', ['resource' => __('discount_rule.discount_rule')]));
    }

    /** Flip the record between active and inactive; see the trait for why. */
    public function toggleStatus(DiscountRule $discountRule)
    {
        return $this->toggleRecordStatus($discountRule, 'general.resource.discount_rule');
    }
}

