<?php

namespace App\Http\Controllers\JournalEntry;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JournalEntry\JournalEntry;
use App\Http\Resources\JournalEntry\JournalEntryResource;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Ledger\LedgerResource;
use App\Http\Resources\Currency\CurrencyResource;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Http\Requests\JournalEntry\JournalEntryStoreRequest;
use App\Http\Requests\JournalEntry\JournalEntryUpdateRequest;
use App\Services\TransactionService;
use App\Services\AttachmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Support\Inertia\CacheKey;
use App\Models\User;
use App\Models\JournalEntry\JournalClass;
use App\Http\Resources\JournalEntry\JournalClassResource;
use App\Services\DateConversionService;
use App\Services\ActivityLogService;
use App\Enums\TransactionStatus;
use Illuminate\Support\Facades\Auth;

class JournalEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function __construct()
    {
        $this->authorizeResource(JournalEntry::class, 'journalEntry');
    }
    public function index( Request $request )
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $journalEntries = JournalEntry::with('transaction', 'transaction.lines.account')
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('JournalEntry/JournalEntries/Index', [
            'journalEntries' => JournalEntryResource::collection($journalEntries),
            'filterOptions' => [
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
                'journalClasses' => JournalClass::orderBy('name')->get(['id', 'name']),
                'ledgers' => Ledger::orderBy('name')->get(['id', 'name']),
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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $latestNumber = (string) ((int) JournalEntry::max('number') + 1);
        return inertia('JournalEntry/JournalEntries/Create', [
            'accounts' => AccountResource::collection(Account::query()->orderBy('created_at', 'desc')->get()),
            'ledgers' => LedgerResource::collection(Ledger::query()->orderBy('created_at', 'desc')->get()),
            'journalClasses' => JournalClass::all(),
            'latestNumber' => $latestNumber,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(JournalEntryStoreRequest $request, ActivityLogService $activityLogService, AttachmentService $attachmentService)
    {
        DB::transaction(function () use ($request, $activityLogService, $attachmentService) {
            $validated = $request->validated();
            $postImmediately = (bool) user_preference('transaction.journal_entry_post_immediately', true);
            $documentStatus = $postImmediately ? TransactionStatus::POSTED->value : TransactionStatus::DRAFT->value;
            $journalEntry = JournalEntry::create([
                'number' => $validated['number'],
                'date' => $validated['date'],
                'status' => $documentStatus,
                'posted_at' => $postImmediately ? now() : null,
                'posted_by' => $postImmediately ? Auth::id() : null,
                'currency_id' => $validated['currency_id'],
                'rate' => $validated['rate'],
                'remark' => $validated['remarks'],
            ]);

            if ($request->hasFile('attachments')) {
                $attachmentService->store($journalEntry, $request->file('attachments'));
            }

            $transactionService = app(TransactionService::class);
            $lines = collect($validated['lines'])
                ->map(fn ($line) => [
                    'account_id'  => $line['account_id'],
                    'ledger_id'   => $line['ledger_id'] ?? null,
                    'debit'       => $line['debit'] ?? 0,
                    'credit'      => $line['credit'] ?? 0,
                    'remark'      => $line['remark'] ?? $validated['remarks'],
                    'remark_fa'   => $line['remark'] ?? $validated['remarks'],
                    'remark_ps'   => $line['remark'] ?? $validated['remarks'],
                    'journal_class_id' => $line['journal_class_id'] ?? null,
                ])
                ->toArray();
            $totalDebit = collect($lines)->sum('debit');
            $totalCredit = collect($lines)->sum('credit');

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => $validated['rate'],
                    'voucher_number' =>  'journal #'.$validated['number'],
                    'date' => $validated['date'],
                    'remark' => $validated['remarks'],
                    'reference_type' => JournalEntry::class,
                    'reference_id' => $journalEntry->id,
                    'status' => $documentStatus,
                ],
                lines: $lines,
            );

            $activityLogService->logAction(
                eventType: 'posted',
                reference: $journalEntry,
                module: 'journal_entry',
                description: "Journal entry #{$journalEntry->number} posted.",
                newValues: [
                    'number' => $journalEntry->number,
                    'date' => $validated['date'],
                    'narration' => $validated['remarks'],
                    'total_debit' => (float) $totalDebit,
                    'total_credit' => (float) $totalCredit,
                ],
                metadata: [
                    'action' => 'journal_entry_post',
                    'transaction_id' => $transaction->id,
                ],
            );
        });
        if ($request->input('create_and_new')) {
            return redirect()->route('journal-entries.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.journal_entry')]));
        }
        return redirect()->route('journal-entries.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.journal_entry')]));

    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, JournalEntry $journalEntry)
    {
        $journalEntry->load([
            'transaction.currency',
            'transaction.lines.account',
            'transaction.lines.journalClass',
            'transaction.lines.ledger',
            'attachments',
        ]);
        if ($request->wantsJson()) {
            return response()->json([
                'data' => new JournalEntryResource($journalEntry),
            ]);
        }
        return inertia('JournalEntry/JournalEntries/Show', [
            'journalEntry' => new JournalEntryResource($journalEntry),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, JournalEntry $journalEntry)
    {
        $journalEntry->load(['transaction.currency', 'transaction.lines.account', 'transaction.lines.journalClass','transaction.lines.ledger', 'attachments']);
        return inertia('JournalEntry/JournalEntries/Edit', [
            'journalEntry' => new JournalEntryResource($journalEntry),
            'accounts' => AccountResource::collection(Account::all()),
            'ledgers' => LedgerResource::collection(Ledger::all()),
            'journalClasses' => JournalClassResource::collection(JournalClass::all()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        JournalEntryUpdateRequest $request,
        JournalEntry $journalEntry,
        ActivityLogService $activityLogService,
        AttachmentService $attachmentService
    )
    {
        if ($journalEntry->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $validated = $request->validated();
        $beforeState = [
            'number' => $journalEntry->number,
            'date' => $journalEntry->date?->toDateString(),
            'status' => $journalEntry->status,
            'remark' => $journalEntry->remark,
            'branch_id' => $journalEntry->branch_id,
            'currency_id' => $journalEntry->transaction?->currency_id,
            'rate' => $journalEntry->transaction?->rate,
            'line_count' => $journalEntry->transaction?->lines()->count() ?? 0,
        ];

        DB::transaction(function () use ($request, $validated, $journalEntry, $beforeState, $activityLogService, $attachmentService) {
            if ($request->hasFile('attachments')) {
                $attachmentService->store($journalEntry, $request->file('attachments'));
            }

            // Update journal entry details
            $date =  $validated['date'] ? app(DateConversionService::class)->toGregorian($validated['date']) : null;
            $journalEntry->update([
                'number' => $validated['number'],
                'date' => $date,
                'status' => TransactionStatus::DRAFT->value,
                'currency_id' => $validated['currency_id'],
                'rate' => $validated['rate'],
                'remarks' => $validated['remarks'],
            ]);

            // Remove existing transaction + lines (hard delete), then recreate with store logic
            $transaction = $journalEntry->transaction()->withTrashed()->first();
            if ($transaction) {
                $transaction->lines()->withTrashed()->forceDelete();
                $transaction->forceDelete();
            }
            $lines = [];


            // Post/Update transaction (same posting logic as store after lines created)
            $transactionService = app(TransactionService::class);
            $lines = collect($validated['lines'])
            ->map(fn ($line) => [
                'account_id'  => $line['account_id'],
                'ledger_id'   => $line['ledger_id'] ?? null,
                'debit'       => $line['debit'] ?? 0,
                'credit'      => $line['credit'] ?? 0,
                'remark'      => $line['remark'] ?? $validated['remarks'],
                'remark_fa'   => $line['remark'] ?? $validated['remarks'],
                'remark_ps'   => $line['remark'] ?? $validated['remarks'],
                'journal_class_id' => $line['journal_class_id'] ?? null,
            ])
            ->toArray();
            $totalDebit = collect($lines)->sum('debit');
            $totalCredit = collect($lines)->sum('credit');
            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => $validated['rate'],
                    'date' => $date,
                    'remark' => $validated['remarks'],
                    'reference_type' => JournalEntry::class,
                    'reference_id' => $journalEntry->id,
                    'status' => TransactionStatus::DRAFT->value,
                ],
                lines: $lines,
            );

            $afterState = [
                'number' => $journalEntry->number,
                'date' => $journalEntry->date?->toDateString(),
                'narration' => $validated['remarks'] ?? null,
                'total_debit' => (float) $totalDebit,
                'total_credit' => (float) $totalCredit,
            ];

            $activityLogService->logUpdate(
                reference: $journalEntry,
                before: $beforeState,
                after: $afterState,
                module: 'journal_entry',
                description: "Journal entry #{$journalEntry->number} updated and reposted.",
                metadata: [
                    'action' => 'journal_entry_update',
                    'transaction_id' => $transaction->id,
                ],
            );
        });

        return redirect()->route('journal-entries.index')
            ->with('success', __('general.updated_successfully', ['resource' => __('general.resource.journal_entry')]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, JournalEntry $journalEntry, ActivityLogService $activityLogService)
    {
        if ($journalEntry->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be deleted.');
        }

        $oldValues = [
            'number' => $journalEntry->number,
            'date' => $journalEntry->date?->toDateString(),
            'narration' => $journalEntry->remarks,
            'total_debit' => (float) ($journalEntry->transaction?->lines?->sum('debit') ?? 0),
            'total_credit' => (float) ($journalEntry->transaction?->lines?->sum('credit') ?? 0),
        ];

        DB::transaction(function () use ($journalEntry, $oldValues, $activityLogService) {
            $transaction = $journalEntry->transaction()->first();
            if ($transaction) {
                $transaction->lines()->delete();
                $transaction->delete();
            }
            $journalEntry->delete();

            $activityLogService->logDelete(
                reference: $journalEntry,
                module: 'journal_entry',
                description: "Journal entry #{$journalEntry->number} deleted.",
                oldValues: $oldValues,
                metadata: [
                    'action' => 'journal_entry_delete',
                    'transaction_id' => $transaction?->id,
                ],
            );
        });
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        return redirect()->route('journal-entries.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.journal_entry')]));
    }

    public function post(JournalEntry $journalEntry, TransactionService $transactionService)
    {
        $this->authorize('update', $journalEntry);

        if ($journalEntry->status !== TransactionStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        DB::transaction(function () use ($journalEntry, $transactionService) {
            $transaction = $journalEntry->transaction()->firstOrFail();
            $transactionService->postDraft($transaction);

            $journalEntry->update([
                'status' => TransactionStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);
        });

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.journal_entry')]));
    }

    public function reverse(Request $request, JournalEntry $journalEntry, TransactionService $transactionService)
    {
        $this->authorize('update', $journalEntry);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($journalEntry->status !== TransactionStatus::POSTED->value) {
            abort(422, 'Only posted documents can be reversed.');
        }

        DB::transaction(function () use ($journalEntry, $transactionService, $validated) {
            $transaction = $journalEntry->transaction()->firstOrFail();
            $transactionService->reverse($transaction, $validated['reason'], $journalEntry->number, JournalEntry::class);

            $journalEntry->update([
                'status' => TransactionStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversal_reason' => $validated['reason'],
                'reversal_of_id' => $journalEntry->id,
            ]);
        });

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.journal_entry')]));
    }

    public function restore(Request $request, JournalEntry $journalEntry, ActivityLogService $activityLogService)
    {
        $journalEntry->restore();
        $transaction = $journalEntry->transaction()->withTrashed()->first();
        if ($transaction) {
            $transaction->restore();
            $transaction->lines()->withTrashed()->restore();
        }

        $activityLogService->logAction(
            eventType: 'restored',
            reference: $journalEntry,
            module: 'journal_entry',
            description: "Journal entry #{$journalEntry->number} restored.",
            newValues: [
                'number' => $journalEntry->number,
                'date' => $journalEntry->date?->toDateString(),
                'narration' => $journalEntry->remarks,
                'total_debit' => (float) ($journalEntry->transaction?->lines?->sum('debit') ?? 0),
                'total_credit' => (float) ($journalEntry->transaction?->lines?->sum('credit') ?? 0),
            ],
            metadata: [
                'action' => 'journal_entry_restore',
                'transaction_id' => $transaction?->id,
            ],
        );

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        return redirect()->route('journal-entries.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.journal_entry')]));
    }

    public function forceDelete(Request $request, JournalEntry $journalEntry)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('journal_entries', (string) $journalEntry->id);

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('journal-entries.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.journal_entry')]));
    }

    public function export(Request $request, \App\Services\SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', JournalEntry::class);

        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $entries = JournalEntry::with(['transaction', 'transaction.lines'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->get();

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $company = $request->user()?->company;
        $companyName = match (app()->getLocale()) {
            'fa'    => $company?->name_fa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            'ps'    => $company?->name_pa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            default => $company?->name_en ?: $company?->abbreviation ?: $company?->name_fa ?: $company?->name_pa ?: config('app.name'),
        };
        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);
        $dateService = app(\App\Services\DateConversionService::class);

        $rows = $entries->map(fn ($e) => [
            'number' => $e->number,
            'remark' => $e->remark ?? '-',
            'amount' => (float) ($e->transaction?->lines->sum('debit') > 0
                ? $e->transaction->lines->sum('debit')
                : $e->transaction?->lines->sum('credit') ?? 0),
            'date'   => $e->date ? $dateService->toDisplay($e->date) : '-',
            'status' => (string) $e->status,
        ])->all();

        $label = $t('sidebar', 'journal_entry.journal_entries', 'Journal Entries');

        return $exporter->download([
            'filename'           => 'journal-entries-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'         => $label,
            'sheet_title'        => $label,
            'title'              => $label,
            'company_name'       => $companyName,
            'exported_on'        => now()->format('Y m d'),
            'rtl'                => $rtl,
            'include_row_number' => true,
            'row_number_label'   => $t('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'number', 'label' => $t('general', 'number', 'Number'), 'width' => 10],
                ['key' => 'remark', 'label' => $t('general', 'remark', 'Remark'), 'width' => 24],
                ['key' => 'amount', 'label' => $t('general', 'amount', 'Amount'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'date',   'label' => $t('general', 'date', 'Date'), 'width' => 14],
                ['key' => 'status', 'label' => $t('general', 'status', 'Status'), 'width' => 12],
            ],
            'rows' => $rows,
        ]);
    }
}
