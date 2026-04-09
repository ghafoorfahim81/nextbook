<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\OwnerStoreRequest;
use App\Http\Requests\Owner\OwnerUpdateRequest;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Owner\OwnerResource;
use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Models\Owner\Owner;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Models\User;
class OwnerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Owner::class, 'owner');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $owners = Owner::query()
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Owners/Owners/Index', [
            'owners' => OwnerResource::collection($owners),
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'filterOptions' => [
                'users' => User::query()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'filters' => $filters,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $accountModel = new Account();

        return inertia('Owners/Owners/Create', [
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'capitalAccounts' => $accountModel->getAccountsByAccountTypeSlug('equity'),
            'drawingAccounts' => $accountModel->getAccountsByAccountTypeSlug('equity'),
            'bankAccounts' => $accountModel->getAccountsByAccountTypeSlug('cash-or-bank'),
        ]);
    }

    public function store(OwnerStoreRequest $request, TransactionService $transactionService)
    {
        $validated = $request->validated();
        // dd($validated);
        DB::transaction(function () use ($validated, $transactionService) {
            $owner = Owner::create([
                'name' => $validated['name'],
                'father_name' => $validated['father_name'],
                'nic' => $validated['nic'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'share_percentage' => $validated['share_percentage'] ?? 100,
                'profit_share_percentage' => $validated['profit_share_percentage'] ?? 100,
                'is_active' => $validated['is_active'] ?? true,
                'capital_account_id' => $validated['capital_account_id'],
                'drawing_account_id' => $validated['drawing_account_id'],
            ]);

            // Create financial transactions
            $amount = (float) $validated['amount'];
            $currencyId = $validated['opening_currency_id'];
            $rate = (float) $validated['rate'];
            $today = now()->toDateString();

            // Credit owner's capital account (capital contribution)
            if($amount > 0 && $currencyId && $rate) {
                $transactionService->post(
                   header: [
                       'currency_id' => $currencyId,
                       'rate' => $rate,
                       'date' => $today,
                       'reference_type' => Owner::class,
                       'reference_id' => $owner->id,
                   ],
                   lines: [
                       [
                           'account_id' => $validated['bank_account_id'],
                           'debit' => $amount,
                           'credit' => 0,
                       ],
                       [
                           'account_id' => $validated['capital_account_id'],
                           'debit' => 0,
                           'credit' => $amount,
                       ],
                   ],
               );
            }
        });

        if ($request->boolean('create_and_new')) {
            return redirect()->route('owners.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.owner')]));
        }
        return redirect()->route('owners.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.owner')]));
    }

    public function show(Request $request, Owner $owner)
    {
        $owner->load([
            'capitalAccount',
            'drawingAccount',
            'transaction.currency',
            'transaction.lines.account',
        ]);

        return response()->json([
            'data' => new OwnerResource($owner),
        ]);
    }

    public function edit(Request $request, Owner $owner): Response
    {
        $accountModel = new Account();
        $owner->load(['transaction.currency', 'transaction.lines.account', 'capitalAccount', 'drawingAccount']);

        return inertia('Owners/Owners/Edit', [
            'owner' => new OwnerResource($owner),
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'capitalAccounts' => $accountModel->getAccountsByAccountTypeSlug('equity'),
            'drawingAccounts' => $accountModel->getAccountsByAccountTypeSlug('equity'),
            'bankAccounts' => $accountModel->getAccountsByAccountTypeSlug('cash-or-bank'),
        ]);
    }

    public function update(OwnerUpdateRequest $request, Owner $owner)
    { 
        $validated = $request->validated();
        // dd($validated);
        DB::transaction(function () use ($owner, $validated) {
            $amount = (float) ($validated['amount'] ?? 0);
            $currencyId = $validated['currency_id'] ?? $owner->transaction?->currency_id;
            $rate = (float) ($validated['rate'] ?? $owner->transaction?->rate ?? 1);
            $transaction = $owner->transaction()->with('lines')->first();

            $owner->update([
                'name' => $validated['name'],
                'father_name' => $validated['father_name'],
                'nic' => $validated['nic'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'share_percentage' => $validated['share_percentage'] ?? 100,
                'profit_share_percentage' => $validated['profit_share_percentage'] ?? 100,
                'is_active' => $validated['is_active'] ?? true,
                'capital_account_id' => $validated['capital_account_id'] ?? null,
                'drawing_account_id' => $validated['drawing_account_id'] ?? null,
            ]);

            if ($transaction) {
                TransactionLine::where('transaction_id', $transaction->id)->forceDelete();
                $transaction->forceDelete();
            }

            if ($amount > 0 && $currencyId && $rate) {
                app(TransactionService::class)->post(
                    header: [
                        'currency_id' => $currencyId,
                        'rate' => $rate,
                        'date' => now()->toDateString(),
                        'reference_type' => Owner::class,
                        'reference_id' => $owner->id,
                        'remark' => "Owner contribution for {$owner->name}",
                    ],
                    lines: [
                        [
                            'account_id' => $validated['bank_account_id'],
                            'debit' => $amount,
                            'credit' => 0,
                        ],
                        [
                            'account_id' => $validated['capital_account_id'],
                            'debit' => 0,
                            'credit' => $amount,
                        ],
                    ],
                );
            }
        });
        return redirect()->route('owners.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.owner')]));
    }

    public function destroy(Request $request, Owner $owner)
    {
        $owner->load(['transaction']);

        if ($owner->transaction) {
            $owner->transaction->lines()->delete();
            $owner->transaction->delete();
        }
        $owner->delete();
        return redirect()->route('owners.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.owner')]));
    }

    public function restore(Request $request, Owner $owner)
    {
        $owner->restore();
        $transaction = $owner->transaction()->withTrashed()->first();
        if ($transaction) {
            $transaction->restore();
            $transaction->lines()->withTrashed()->restore();
        }
        return redirect()->route('owners.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.owner')]));
    }
}
