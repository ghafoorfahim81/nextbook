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
use App\Models\User;
use App\Services\ActivityLogService;
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

    public function store(
        OwnerStoreRequest $request,
        TransactionService $transactionService,
        ActivityLogService $activityLogService
    )
    {
        $validated = $request->validated();
        $sharePercentage = (float) ($validated['share_percentage']
            ?? $validated['ownership_percentage']
            ?? 100);
        $owner = null;
        $transaction = null;

        DB::transaction(function () use (&$owner, &$transaction, $validated, $transactionService, $activityLogService) {
            $sharePercentage = (float) ($validated['share_percentage']
                ?? $validated['ownership_percentage']
                ?? 100);

            $owner = Owner::create([
                'name' => $validated['name'],
                'father_name' => $validated['father_name'],
                'nic' => $validated['nic'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'share_percentage' => $sharePercentage,
                'profit_share_percentage' => $validated['profit_share_percentage'] ?? 100,
                'is_active' => $validated['is_active'] ?? true,
                'capital_account_id' => $validated['capital_account_id'],
                'drawing_account_id' => $validated['drawing_account_id'],
            ]);

            $amount = (float) ($validated['amount'] ?? 0);
            $currencyId = $validated['opening_currency_id'] ?? null;
            $rate = (float) ($validated['rate'] ?? 1);
            $today = now()->toDateString();

            if ($amount > 0 && $currencyId && $rate) {
                $transaction = $transactionService->post(
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

            $activityLogService->logCreate(
                reference: $owner,
                module: 'owner',
                description: "Owner {$owner->name} created.",
                newValues: [
                    'name' => $owner->name,
                    'father_name' => $owner->father_name,
                    'nic' => $owner->nic,
                    'email' => $owner->email,
                    'address' => $owner->address,
                    'phone_number' => $owner->phone_number,
                    'ownership_percentage' => $owner->share_percentage,
                    'profit_share_percentage' => $owner->profit_share_percentage,
                    'is_active' => $owner->is_active,
                    'capital_account_id' => $owner->capital_account_id,
                    'drawing_account_id' => $owner->drawing_account_id,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'bank_account_id' => $validated['bank_account_id'] ?? null,
                ],
                metadata: [
                    'action' => 'owner_create',
                    'transaction_id' => $transaction?->id,
                ],
            );
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

    public function update(OwnerUpdateRequest $request, Owner $owner, ActivityLogService $activityLogService)
    {
        $validated = $request->validated();
        $sharePercentage = (float) ($validated['share_percentage']
            ?? $validated['ownership_percentage']
            ?? $owner->share_percentage
            ?? 100);
        $currentTransaction = $owner->transaction()->with('lines', 'currency')->first();
        $amount = (float) ($validated['amount'] ?? 0);
        $currencyId = $validated['currency_id']
            ?? $request->input('opening_currency_id')
            ?? $currentTransaction?->currency_id;
        $rate = (float) ($validated['rate'] ?? $currentTransaction?->rate ?? 1);

        $beforeState = [
            'name' => $owner->name,
            'father_name' => $owner->father_name,
            'nic' => $owner->nic,
            'email' => $owner->email,
            'address' => $owner->address,
            'phone_number' => $owner->phone_number,
            'ownership_percentage' => $owner->share_percentage,
            'profit_share_percentage' => $owner->profit_share_percentage,
            'is_active' => $owner->is_active,
            'capital_account_id' => $owner->capital_account_id,
            'drawing_account_id' => $owner->drawing_account_id,
            'amount' => $currentTransaction?->lines?->first()?->debit
                ?? $currentTransaction?->lines?->first()?->credit,
            'currency_id' => $currentTransaction?->currency_id,
            'rate' => $currentTransaction?->rate,
            'bank_account_id' => $currentTransaction?->lines?->first()?->account_id,
        ];

        $transaction = null;

        DB::transaction(function () use (
            &$transaction,
            $owner,
            $validated,
            $amount,
            $currencyId,
            $rate,
        ) {
            $transaction = $owner->transaction()->with('lines')->first();

            $owner->update([
                'name' => $validated['name'],
                'father_name' => $validated['father_name'],
                'nic' => $validated['nic'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'share_percentage' => $sharePercentage,
                'profit_share_percentage' => $validated['profit_share_percentage'] ?? 100,
                'is_active' => $validated['is_active'] ?? true,
                'capital_account_id' => $validated['capital_account_id'] ?? null,
                'drawing_account_id' => $validated['drawing_account_id'] ?? null,
            ]);

            if ($transaction) {
                $transaction->lines()->delete();
                $transaction->delete();
            }

            if ($amount > 0 && $currencyId && $rate) {
                $transaction = app(TransactionService::class)->post(
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

        $activityLogService->logUpdate(
            reference: $owner,
            before: $beforeState,
            after: [
                'name' => $owner->name,
                'father_name' => $owner->father_name,
                'nic' => $owner->nic,
                'email' => $owner->email,
                'address' => $owner->address,
                'phone_number' => $owner->phone_number,
                'ownership_percentage' => $owner->share_percentage,
                'profit_share_percentage' => $owner->profit_share_percentage,
                'is_active' => $owner->is_active,
                'capital_account_id' => $owner->capital_account_id,
                'drawing_account_id' => $owner->drawing_account_id,
                'amount' =>  $amount,
                'currency_id' => $transaction?->currency_id ?? $currencyId,
                'rate' => $transaction?->rate ?? $rate,
                'bank_account_id' => $transaction?->lines?->first()?->account_id
                    ?? ($validated['bank_account_id'] ?? $currentTransaction?->lines?->first()?->account_id),
            ],
            module: 'owner',
            description: "Owner {$owner->name} updated.",
            metadata: [
                'action' => 'owner_update',
                'transaction_id' => $transaction?->id,
            ],
        );

        return redirect()->route('owners.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.owner')]));
    }

    public function destroy(Request $request, Owner $owner, ActivityLogService $activityLogService)
    {
        $transaction = $owner->transaction()->with('lines', 'currency')->first();

        $oldValues = [
            'name' => $owner->name,
            'father_name' => $owner->father_name,
            'nic' => $owner->nic,
            'email' => $owner->email,
            'address' => $owner->address,
            'phone_number' => $owner->phone_number,
            'ownership_percentage' => $owner->share_percentage,
            'profit_share_percentage' => $owner->profit_share_percentage,
            'is_active' => $owner->is_active,
            'capital_account_id' => $owner->capital_account_id,
            'drawing_account_id' => $owner->drawing_account_id,
            'amount' => $transaction?->lines?->first()?->debit
                ?? $transaction?->lines?->first()?->credit,
            'currency_id' => $transaction?->currency_id,
            'rate' => $transaction?->rate,
            'bank_account_id' => $transaction?->lines?->first()?->account_id,
        ];

        DB::transaction(function () use ($owner, &$transaction): void {
            if ($transaction) {
                $transaction->lines()->delete();
                $transaction->delete();
            }

            $owner->delete();
        });

        $activityLogService->logDelete(
            reference: $owner,
            module: 'owner',
            description: "Owner {$owner->name} deleted.",
            oldValues: $oldValues,
            metadata: [
                'action' => 'owner_delete',
                'transaction_id' => $transaction?->id,
            ],
        );
        return redirect()->route('owners.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.owner')]));
    }

    public function restore(Request $request, Owner $owner, ActivityLogService $activityLogService)
    {
        $transaction = null;

        DB::transaction(function () use ($owner, &$transaction): void {
            $owner->restore();

            $transaction = $owner->transaction()->withTrashed()->with('lines')->first();
            if ($transaction) {
                $transaction->restore();
                $transaction->lines()->withTrashed()->restore();
            }
        });

        $activityLogService->logAction(
            eventType: 'restored',
            reference: $owner,
            module: 'owner',
            description: "Owner {$owner->name} restored.",
            newValues: [
            'name' => $owner->name,
            'father_name' => $owner->father_name,
            'phone_number' => $owner->phone_number,
            'ownership_percentage' => $owner->share_percentage,
            'capital_account_id' => $owner->capital_account_id,
            'drawing_account_id' => $owner->drawing_account_id,
        ],
            metadata: [
                'action' => 'owner_restore',
                'transaction_id' => $transaction?->id,
            ],
        );
        return redirect()->route('owners.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.owner')]));
    }

    public function forceDelete(Request $request, Owner $owner)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('owners', (string) $owner->id);

        return redirect()->route('owners.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.owner')]));
    }
}
