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
use App\Models\Currency\Currency;
use App\Http\Requests\JournalEntry\JournalEntryStoreRequest;
use App\Services\TransactionService;
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
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');

        $journalEntries = JournalEntry::with('transaction', 'lines')->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('JournalEntries/Index', [
            'journalEntries' => JournalEntryResource::collection($journalEntries),
        ]);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('JournalEntries/Create', [
            'accounts' => AccountResource::collection(Account::all()),
            'ledgers' => LedgerResource::collection(Ledger::all()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(JournalEntryStoreRequest $request)
    {
        $validated = $request->validated();
        dd($validated);
        $journalEntry = JournalEntry::create([
            'number' => $validated['number'],
            'date' => $validated['date'],
            'currency_id' => $validated['currency_id'],
            'rate' => $validated['rate'],
            'remarks' => $validated['remarks'],
            'created_by' => Auth::id(),
        ]);

        $transactionService = new TransactionService();
        $transactionService->post(
            header: [
                'currency_id' => $validated['currency_id'],
                'rate' => $validated['rate'],
                'date' => $validated['date'],
                'remark' => $validated['remarks'],
                'status' => 'posted',
            ],
            lines: [
                [
                    'account_id' => $validated['lines']['account_id'],
                    'ledger_id' => $validated['lines']['ledger_id'],
                    'debit' => $validated['lines']['debit'],
                    'credit' => 0,
                    'remark' => $validated['lines']['remark'],
                    'bill_number' => $validated['lines']['bill_number'],
                ],
                [
                    'account_id' => $validated['lines']['account_id'],
                    'debit' => 0,
                    'credit' => $validated['lines']['credit'],
                    'remark' => $validated['lines']['remark'],
                    'ledger_id' => $validated['lines']['ledger_id'],
                    'bill_number' => $validated['lines']['bill_number'],
                ],
            ],
        );

        return redirect()->route('journal-entries.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.journal_entry')]));

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
