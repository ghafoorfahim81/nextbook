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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Support\Inertia\CacheKey;
use App\Models\User;
use App\Models\JournalEntry\JournalClass;
use App\Http\Resources\JournalEntry\JournalClassResource;
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
        return inertia('JournalEntry/JournalEntries/Create', [
            'accounts' => AccountResource::collection(Account::all()),
            'ledgers' => LedgerResource::collection(Ledger::all()),
            'journalClasses' => JournalClass::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(JournalEntryStoreRequest $request)
    { 
        DB::transaction(function () use ($request) {
        $validated = $request->validated();
        $journalEntry = JournalEntry::create([
            'number' => $validated['number'],
            'date' => $validated['date'],
            'status' => 'posted',
            'currency_id' => $validated['currency_id'],
            'rate' => $validated['rate'],
            'remarks' => $validated['remarks'],
        ]);
        $transactionService = new TransactionService();
        $lines = collect($validated['lines'])
        ->map(fn ($line) => [
            'account_id'  => $line['account_id'],
            'ledger_id'   => $line['ledger_id'] ?? null,
            'debit'       => $line['debit'] ?? 0,
            'credit'      => $line['credit'] ?? 0,
            'remark'      => $line['remark'] ?? null,
            'journal_class_id' => $line['journal_class_id'] ?? null,
        ])
        ->toArray(); 
        $transactionService->post(
            header: [
                'currency_id' => $validated['currency_id'],
                'rate' => $validated['rate'],
                'date' => $validated['date'],
                'remark' => $validated['remarks'],
                'reference_type' => JournalEntry::class,
                'reference_id' => $journalEntry->id,
                'status' => 'posted',
            ],
            lines: $lines,
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
        ]);
        return response()->json([
            'data' => new JournalEntryResource($journalEntry),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, JournalEntry $journalEntry)
    {
        $journalEntry->load(['transaction.currency', 'transaction.lines.account', 'transaction.lines.journalClass','transaction.lines.ledger']);
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
    public function update(JournalEntryUpdateRequest $request, JournalEntry $journalEntry)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $journalEntry) {
            // Update journal entry details
            $journalEntry->update([
                'number' => $validated['number'],
                'date' => $validated['date'],
                'status' => 'posted',
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
            $transactionService = new TransactionService();
            $lines = collect($validated['lines'])
            ->map(fn ($line) => [
                'account_id'  => $line['account_id'],
                'ledger_id'   => $line['ledger_id'] ?? null,
                'debit'       => $line['debit'] ?? 0,
                'credit'      => $line['credit'] ?? 0,
                'remark'      => $line['remark'] ?? null,
                'journal_class_id' => $line['journal_class_id'] ?? null,
            ])
            ->toArray();
            $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => $validated['rate'],
                    'date' => $validated['date'],
                    'remark' => $validated['remarks'],
                    'reference_type' => JournalEntry::class,
                    'reference_id' => $journalEntry->id,
                    'status' => 'posted',
                ],
                lines: $lines,
            );
        });

        return redirect()->route('journal-entries.index')
            ->with('success', __('general.updated_successfully', ['resource' => __('general.resource.journal_entry')]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, JournalEntry $journalEntry)
    {
        DB::transaction(function () use ($journalEntry) {
            $transaction = $journalEntry->transaction()->first();
            if ($transaction) {
                $transaction->lines()->delete();
                $transaction->delete();
            }
            $journalEntry->delete();
        });
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        return redirect()->route('journal-entries.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.journal_entry')]));
    }

    public function restore(Request $request, JournalEntry $journalEntry)
    {
        $journalEntry->restore();
        $transaction = $journalEntry->transaction()->withTrashed()->first();
        if ($transaction) {
            $transaction->restore();
            $transaction->lines()->withTrashed()->restore();
        }
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        return redirect()->route('journal-entries.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.journal_entry')]));
    }
}
