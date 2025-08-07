<?php

namespace Tests\Feature\Http\Controllers\Account;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Branch;
use App\Models\CreatedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Account\AccountController
 */
final class AccountControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $accounts = Account::factory()->count(3)->create();

        $response = $this->get(route('accounts.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Account\AccountController::class,
            'store',
            \App\Http\Requests\Account\AccountStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = $this->faker->name();
        $number = $this->faker->word();
        $account_type = AccountType::factory()->create();
        $branch = Branch::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('accounts.store'), [
            'name' => $name,
            'number' => $number,
            'account_type_id' => $account_type->id,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $accounts = Account::query()
            ->where('name', $name)
            ->where('number', $number)
            ->where('account_type_id', $account_type->id)
            ->where('branch_id', $branch->id)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $accounts);
        $account = $accounts->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $account = Account::factory()->create();

        $response = $this->get(route('accounts.show', $account));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Account\AccountController::class,
            'update',
            \App\Http\Requests\Account\AccountUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $account = Account::factory()->create();
        $name = $this->faker->name();
        $number = $this->faker->word();
        $account_type = AccountType::factory()->create();
        $branch = Branch::factory()->create();
        $tenant = Tenant::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('accounts.update', $account), [
            'name' => $name,
            'number' => $number,
            'account_type_id' => $account_type->id,
            'branch_id' => $branch->id,
            'tenant_id' => $tenant->id,
            'created_by' => $created_by->id,
        ]);

        $account->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $account->name);
        $this->assertEquals($number, $account->number);
        $this->assertEquals($account_type->id, $account->account_type_id);
        $this->assertEquals($branch->id, $account->branch_id);
        $this->assertEquals($tenant->id, $account->tenant_id);
        $this->assertEquals($created_by->id, $account->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $account = Account::factory()->create();

        $response = $this->delete(route('accounts.destroy', $account));

        $response->assertNoContent();

        $this->assertModelMissing($account);
    }
}
