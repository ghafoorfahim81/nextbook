<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\BranchStoreRequest;
use App\Http\Requests\Administration\BranchUpdateRequest;
use App\Http\Resources\Administration\BranchCollection;
use App\Http\Resources\Administration\BranchResource;
use App\Models\Administration\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Administration\Quantity;
use App\Models\Administration\Size;
use App\Models\Administration\Currency;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Store;
use App\Models\Ledger\Ledger;
use Symfony\Component\Uid\Ulid;
class BranchController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Branch::class, 'branch');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $branches = Branch::with('parent')
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Branches/Index', [
            'branches' => BranchResource::collection($branches),
        ]);
    }

    public function store(BranchStoreRequest $request)
    {
        $branch = Branch::create($request->validated());

        $defaultAccountTypes = AccountType::defaultAccountTypes();

        foreach ($defaultAccountTypes as $accountType) {
            AccountType::withoutEvents(function () use ($accountType, $branch) {
            AccountType::create([
                'id' => (string) new Ulid(),
                'name' => $accountType['name'],
                'is_main' => $accountType['is_main'],
                'slug' => $accountType['slug'],
                'branch_id' => $branch->id,
                'created_by' => auth()->user()->id,
            ]);
            });
        }
        $accounts = Account::defaultAccounts();
        foreach ($accounts as $account) {
            Account::withoutEvents(function () use ($account, $branch) {
                Account::create([
                    'id' => (string) new Ulid(),
                    'name' => $account['name'],
                    'number' => $account['number'],
                    'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', $account['account_type_slug'])
                    ->where('branch_id', $branch->id)
                    ->first()->id,
                    'branch_id' => $branch->id,
                    'slug' => $account['slug'],
                    'remark' => $account['remark'],
                    'is_main' => $account['is_main'],
                    'created_by' => auth()->user()->id,
                ]);
            });
        }

        $defaultSizes = Size::defaultSizes();
        foreach ($defaultSizes as $size) {
            Size::withoutEvents(function () use ($size, $branch) {
                Size::create([
                    'id' => (string) new Ulid(),
                    'name' => $size['name'],
                    'code' => $size['code'],
                    'branch_id' => $branch->id,
                    'created_by' => auth()->user()->id,
                ]);
            });
        }

        $defaultCurrencies = Currency::defaultCurrencies();
        foreach ($defaultCurrencies as $currency) {
            Currency::withoutEvents(function () use ($currency, $branch) {
                Currency::create([
                    'id' => (string) new Ulid(),
                    'name' => $currency['name'],
                    'code' => $currency['code'],
                    'symbol' => $currency['symbol'],
                    'format' => $currency['format'],
                    'exchange_rate' => $currency['exchange_rate'],
                    'is_active' => $currency['is_active'],
                    'is_base_currency' => $currency['is_base_currency'] ?? false,
                    'flag' => $currency['flag'],
                    'branch_id' => $branch->id,
                    'created_by' => auth()->user()->id,
                ]);
            });
        }

        $quantities = Quantity::defaultQuantity();
        foreach ($quantities as $quantity) {
            Quantity::withoutEvents(function () use ($quantity, $branch) {
                Quantity::create([
                    'id' => (string) new Ulid(),
                    'quantity' => $quantity['quantity'],
                    'unit' => $quantity['unit'],
                    'symbol' => $quantity['symbol'],
                    'slug' => $quantity['slug'],
                    'branch_id' => $branch->id,
                    'created_by' => auth()->user()->id,
                ]);
            });
        }
        $unitMeasures = UnitMeasure::defaultUnitMeasures();
        foreach ($unitMeasures as $unitMeasure) {
            UnitMeasure::withoutEvents(function () use ($unitMeasure, $branch) {
                UnitMeasure::create([
                'id' => (string) new Ulid(),
                'name' => $unitMeasure['name'],
                'unit' => $unitMeasure['unit'],
                'symbol' => $unitMeasure['symbol'],
                'branch_id' => $branch->id,
                'quantity_id' => Quantity::withoutGlobalScopes()
                ->where('branch_id',$branch->id)
                ->where('slug', $unitMeasure['quantity_slug'])
                ->first()->id,
                'is_main' => true,
                'created_by' => auth()->user()->id,
            ]);
            });
        }

        $store = Store::withoutGlobalScopes()->where('is_main', true)->first();
            Store::withoutEvents(function () use ($store, $branch) {
                Store::create([
                    'id' => (string) new Ulid(),
                    'name' => $store['name'],
                    'address' => 'Main store',
                    'branch_id' => $branch->id,
                    'is_main' => true,
                    'created_by' => auth()->user()->id,
                ]);
            });

        Ledger::withoutEvents(function () use ($branch) {
            Ledger::create([
                'id' => (string) new Ulid(),
                'name' => 'Cash customer',
                'code' => 'CASH-CUST',
                'type' => 'customer',
                'branch_id' => $branch->id,
                'created_by' => auth()->user()->id,
            ]);
        });


        return redirect()->route('branches.index')->with('success', 'Branch created successfully.');
    }

    public function show(Request $request, Branch $branch): Response
    {
        return new BranchResource($branch);
    }

    public function update(BranchUpdateRequest $request, Branch $branch)
    {
        $branch->update($request->validated());
        return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
    }

    public function destroy(Request $request, Branch $branch)
    {
        // Prevent deleting the main branch
        if ($branch->is_main) {
            return redirect()->route('branches.index')->with('error', __('You cannot delete the main branch.'));
        }

        // Check for dependencies before deletion
        if (!$branch->canBeDeleted()) {
            $message = $branch->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return redirect()->route('branches.index')->with('error', $message);
        }

        $branch->delete();
        return redirect()->route('branches.index')->with('success', __('general.branch_deleted_successfully'));
    }

    public function restore(Request $request, Branch $branch)
    {
        $branch->restore();
        return redirect()->route('branches.index')->with('success', 'Branch restored successfully.');
    }
}
