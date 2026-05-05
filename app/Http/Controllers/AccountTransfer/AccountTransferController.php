<?php

namespace App\Http\Controllers\AccountTransfer;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountTransfer\AccountTransferStoreRequest;
use App\Http\Requests\AccountTransfer\AccountTransferUpdateRequest;
use App\Http\Resources\AccountTransfer\AccountTransferResource;
use App\Models\Account\Account;
use App\Models\AccountTransfer\AccountTransfer;
use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\DateConversionService;
use App\Services\ActivityLogService;
class AccountTransferController extends Controller
{
    private $dateConversionService;
    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(AccountTransfer::class, 'account_transfer');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $transfers = AccountTransfer::with([
                'transaction.lines.account',
                'transaction.currency',
                'createdBy',
                'updatedBy',
            ])
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('AccountTransfers/Index', [
            'transfers' => AccountTransferResource::collection($transfers),
            'filterOptions' => [
                'accounts' => Account::orderBy('name')->get(['id', 'name']),
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
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

    public function create(Request $request)
    {
        return inertia('AccountTransfers/Create');
    }


    public function store(AccountTransferStoreRequest $request, TransactionService $transactionService, ActivityLogService $activityLogService)
    {
        // dd((string) \Symfony\Component\Uid\Ulid::generate());
        DB::transaction(function () use ($request, $transactionService, $activityLogService) {
            $validated = $request->validated();

            $fromAccountId = $validated['from_account_id'];
            $toAccountId = $validated['to_account_id'];
            $amount = (float) $validated['amount'];
            $currencyId = $validated['currency_id'];
            $rate = (float) $validated['rate'];

            // Use any existing ledger to fulfill the non-null FK constraint on transactions table.
            // Since this is an internal transfer, we associate both transactions with the same placeholder ledger.
            $ledger = Ledger::query()->latest()->first();
            if (!$ledger) {
                abort(422, 'No ledger found to associate the transfer transactions.');
            }

            $fromAccount = Account::findOrFail($fromAccountId);
            $toAccount = Account::findOrFail($toAccountId);
            $nature = $fromAccount->accountType->nature ?? 'asset';

            $mappings = [
                'asset'    => ['debit' => $toAccount->id, 'credit' => $fromAccount->id],
                'liability'=> ['debit' => $fromAccount->id, 'credit' => $toAccount->id],
                'equity'   => ['debit' => $fromAccount->id, 'credit' => $toAccount->id],
                'income'   => ['debit' => $fromAccount->id, 'credit' => $toAccount->id],
                'expense'  => ['debit' => $toAccount->id, 'credit' => $fromAccount->id],
            ];
            $map = $mappings[$nature] ?? $mappings['asset'];

            $transfer = AccountTransfer::create([
                'number' => $validated['number'] ?? null,
                'date' => $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null,
                'remark' => $validated['remark'] ?? null,
            ]);
            $transaction = $transactionService->post([
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $transfer->date,
                'remark' => "Transfer #{$transfer->number}.from {$fromAccount->name} to {$toAccount->name}",
            ], [
                [
                    'account_id' => $map['debit'],
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $map['credit'],
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ]);

            $transfer->update([
                'transaction_id' => $transaction->id,
            ]);

            $this->logTransferActivity(
                activityLogService: $activityLogService,
                eventType: 'created',
                transfer: $transfer->loadMissing('transaction.lines.account', 'transaction.currency'),
                values: [
                    'number' => $transfer->number,
                    'date' => $transfer->date?->toDateString(),
                    'from_account_id' => $fromAccount->id,
                    'to_account_id' => $toAccount->id,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                ],
                metadata: [
                    'action' => 'account_transfer_store',
                    'transaction_id' => $transaction->id,
                ],
            );
        });

        if ($request->input('create_and_new')) {
            return redirect()->route('account-transfers.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.account_transfer')]));
        }

        return redirect()->route('account-transfers.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.account_transfer')]));
    }

    public function show(Request $request, AccountTransfer $accountTransfer)
    {
        $accountTransfer->load(['transaction.lines.account', 'transaction.currency', 'createdBy', 'updatedBy']);
        return response()->json([
            'data' => new AccountTransferResource($accountTransfer),
        ]);
    }

    public function edit(Request $request, AccountTransfer $accountTransfer)
    {
        $accountTransfer->load(['transaction.lines.account', 'transaction.currency', 'createdBy', 'updatedBy']);
        return inertia('AccountTransfers/Edit', [
            'data' => new AccountTransferResource($accountTransfer),
        ]);
    }

    public function update(AccountTransferUpdateRequest $request, AccountTransfer $accountTransfer, ActivityLogService $activityLogService)
    {
        // dd($request->all());
        $before = $this->transferSnapshot($accountTransfer->loadMissing('transaction.lines.account', 'transaction.currency'));

        DB::transaction(function () use ($request, $accountTransfer, $activityLogService, $before) {
            $validated = $request->validated();

            $accountTransfer->update([
                'number' => $validated['number'] ?? $accountTransfer->number,
                'date' => $validated['date'] ?? $accountTransfer->date,
                'remark' => $validated['remark'] ?? $accountTransfer->remark,
            ]);

            $amount = isset($validated['amount']) ? (float) $validated['amount'] : ($accountTransfer->transaction?->lines?->first()?->debit ?? $accountTransfer->transaction?->lines?->first()?->credit);
            $currencyId = $validated['currency_id'] ?? ($accountTransfer->transaction?->currency_id);
            $rate = isset($validated['rate']) ? (float) $validated['rate'] : ($accountTransfer->transaction?->rate);
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $accountTransfer->date;
            $fromAccountId = $validated['from_account_id'];
            $toAccountId = $validated['to_account_id'];
            $transactionService = app(TransactionService::class);
            TransactionLine::where('transaction_id', $accountTransfer->transaction_id)->forceDelete();
            Transaction::where('id', $accountTransfer->transaction_id)->forceDelete();
            $fromAccount = Account::findOrFail($fromAccountId);
            $toAccount = Account::findOrFail($toAccountId);
            $nature = $fromAccount->accountType->nature ?? 'asset';

            $mappings = [
                'asset'    => ['debit' => $toAccount->id, 'credit' => $fromAccount->id],
                'liability'=> ['debit' => $fromAccount->id, 'credit' => $toAccount->id],
                'equity'   => ['debit' => $fromAccount->id, 'credit' => $toAccount->id],
                'income'   => ['debit' => $fromAccount->id, 'credit' => $toAccount->id],
                'expense'  => ['debit' => $toAccount->id, 'credit' => $fromAccount->id],
            ];
            $map = $mappings[$nature] ?? $mappings['asset'];
            $transaction = $transactionService->post(
                header: [
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $date,
                'remark' => "Transfer #{$accountTransfer->number}",
            ],
            lines: [
                [
                    'account_id' => $map['debit'],
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $map['credit'],
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ]);
            $accountTransfer->update([
                'transaction_id' => $transaction->id,
            ]);

            $after = [
                'number' => $accountTransfer->number,
                'date' => $date,
                'from_account_id' => $fromAccount->id,
                'to_account_id' => $toAccount->id,
                'amount' => $amount,
                'currency_id' => $currencyId,
                'rate' => $rate,
            ];

            $activityLogService->logUpdate(
                reference: $accountTransfer,
                before: $before,
                after: $after,
                module: 'account_transfer',
                description: "Account transfer #{$accountTransfer->number} updated.",
                metadata: [
                    'action' => 'account_transfer_update',
                    'transaction_id' => $transaction->id,
                ],
            );
        });

        return redirect()->route('account-transfers.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.account_transfer')]));
    }

    public function destroy(AccountTransfer $accountTransfer, ActivityLogService $activityLogService)
    {
        $oldValues = $this->transferSnapshot($accountTransfer->loadMissing('transaction.lines.account', 'transaction.currency'));

        DB::transaction(function () use ($accountTransfer) {
            $accountTransfer->delete();
            $accountTransfer->transaction->lines()->delete();
            $accountTransfer->transaction->delete();
        });

        $activityLogService->logDelete(
            reference: $accountTransfer,
            module: 'account_transfer',
            description: "Account transfer #{$accountTransfer->number} deleted.",
            oldValues: $oldValues,
            metadata: [
                'action' => 'account_transfer_delete',
            ],
        );
        return back()->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.account_transfer')]));
    }

    public function restore(AccountTransfer $accountTransfer, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($accountTransfer) {
            $accountTransfer->restore();
            $transaction = $accountTransfer->transaction()->withTrashed()->first();
            if ($transaction) {
                $transaction->restore();
                $transaction->lines()->withTrashed()->restore();
            }
        });

        $activityLogService->logAction(
            eventType: 'restored',
            reference: $accountTransfer,
            module: 'account_transfer',
            description: "Account transfer #{$accountTransfer->number} restored.",
            newValues: $this->transferSnapshot($accountTransfer->loadMissing('transaction.lines.account', 'transaction.currency')),
            metadata: [
                'action' => 'account_transfer_restore',
            ],
        );
        return back()->with('success', __('general.restored_successfully', ['resource' => __('general.resource.account_transfer')]));
    }

    public function forceDelete(Request $request, AccountTransfer $accountTransfer)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('account_transfers', (string) $accountTransfer->id);

        return back()->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.account_transfer')]));
    }

    private function transferSnapshot(AccountTransfer $accountTransfer): array
    {
        $accountTransfer->loadMissing('transaction.lines.account.accountType', 'transaction.currency');

        $firstLine = $accountTransfer->transaction?->lines?->first();
        $lastLine = $accountTransfer->transaction?->lines?->last();
        $firstNature = $firstLine?->account?->accountType?->nature;
        $debitFirstNature = in_array($firstNature, ['asset', 'expense'], true);

        $fromAccount = $debitFirstNature ? $lastLine?->account : $firstLine?->account;
        $toAccount = $debitFirstNature ? $firstLine?->account : $lastLine?->account;
        $transactionLine = $firstLine;
        $amount = (float) ($transactionLine?->debit ?: $transactionLine?->credit ?: 0);

        return [
            'number' => $accountTransfer->number,
            'date' => $accountTransfer->date?->toDateString(),
            'from_account_id' => $fromAccount?->id,
            'to_account_id' => $toAccount?->id,
            'amount' => $amount,
            'currency_id' => $accountTransfer->transaction?->currency_id,
            'rate' => $accountTransfer->transaction?->rate,
            'remark' => $accountTransfer->remark,
        ];
    }

    private function logTransferActivity(
        ActivityLogService $activityLogService,
        string $eventType,
        AccountTransfer $transfer,
        array $values,
        array $metadata = [],
    ): void {
        $activityLogService->logAction(
            eventType: $eventType,
            reference: $transfer,
            module: 'account_transfer',
            description: "Account transfer #{$transfer->number} {$eventType}.",
            newValues: $eventType === 'created' ? $values : null,
            oldValues: $eventType === 'deleted' ? $values : null,
            metadata: $metadata,
        );
    }
}
