<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Administration\BranchResource;
use App\Http\Requests\Owner\DrawingStoreRequest;
use App\Http\Requests\Owner\DrawingUpdateRequest;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Owner\DrawingResource;
use App\Http\Resources\Owner\OwnerResource;
use App\Models\Account\Account;
use App\Models\Administration\Branch;
use App\Models\Administration\Currency;
use App\Models\Owner\Drawing;
use App\Models\Owner\Owner;
use App\Models\Transaction\TransactionLine;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use App\Services\DateConversionService;
class DrawingController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Drawing::class, 'drawing');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $drawings = Drawing::query()
            ->with(['owner.drawingAccount', 'transaction.currency', 'transaction.lines.account'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Owners/Drawings/Index', [
            'drawings' => DrawingResource::collection($drawings),
            'filterOptions' => [
                'owners' => Owner::query()
                    ->with('drawingAccount')
                    ->whereNotNull('drawing_account_id')
                    ->orderBy('name')
                    ->get(['id', 'name', 'drawing_account_id']),
                'branches' => BranchResource::collection(
                    Branch::query()->orderBy('name')->get()
                ),
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
        $latestNumber = (string) ((int) Drawing::max('number') + 1);
        return inertia('Owners/Drawings/Create', [
            'latestNumber' => $latestNumber,
            'owners' => OwnerResource::collection(
                Owner::query()
                    ->with('drawingAccount')
                    ->whereNotNull('drawing_account_id')
                    ->orderBy('name')
                    ->get()
            ),
           'bankAccounts' => $accountModel->getAccountsByAccountTypeSlug('cash-or-bank'),
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'homeCurrency' => CurrencyResource::make(
                Currency::query()->where('is_base_currency', true)->first()
            ),
        ]);
    }

    public function store(DrawingStoreRequest $request, TransactionService $transactionService)
    {
        $validated = $request->validated(); 
        DB::transaction(function () use ($validated, $transactionService) {
            $owner = Owner::with('drawingAccount')->findOrFail($validated['owner_id']);
            abort_unless($owner->drawing_account_id, 422, 'Selected owner does not have a drawing account.');
            $dateConversionService = app(DateConversionService::class);
            $date = $validated['date'] ? $dateConversionService->toGregorian($validated['date']) : null;
            $drawing = Drawing::create([
                'number' => $validated['number'] ?? (string) ((int) Drawing::max('number') + 1),
                'owner_id' => $owner->id,
                'date' => $date,
                'narration' => $validated['narration'] ?? null,
            ]);
            $amount = (float) $validated['amount'];

            $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => $validated['rate'],
                    'date' => $date,
                    'reference_type' => Drawing::class,
                    'reference_id' => $drawing->id,
                    'remark' => $validated['narration'] ?? "Drawing by {$owner->name}",
                ],
                lines: [
                    [
                        'account_id' => $validated['bank_account_id'],
                        'debit' => 0,
                        'credit' => $amount,
                        'remark' => "Drawing by {$owner->name}",
                        'remark_fa' => "برداشت توسط {$owner->name}",
                        'remark_ps' => "د". ' '. $owner->name.' '.'لخوا انځورګري ',
                    ],
                    [
                        'account_id' => $owner->drawing_account_id,
                        'debit' => $amount,
                        'credit' => 0,
                        'remark' => "Drawing by {$owner->name}",
                        'remark_fa' => "برداشت توسط {$owner->name}",
                        'remark_ps' => "د". ' '. $owner->name.' '.'لخوا انځورګري',
                    ],
                ],
            );
        });

        if ($request->boolean('create_and_new')) {
            return back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.drawing')]));
        }

        return redirect()->route('drawings.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.drawing')]));
    }

    public function show(Request $request, Drawing $drawing)
    {
        $drawing->load([
            'owner.drawingAccount',
            'transaction.currency',
            'transaction.lines.account',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => new DrawingResource($drawing),
            ]);
        }
        return inertia('Owners/Drawings/Show', [
            'drawing' => new DrawingResource($drawing),
        ]);
    }

    public function edit(Request $request, Drawing $drawing): Response
    {
        $drawing->load([
            'owner.drawingAccount',
            'transaction.currency',
            'transaction.lines.account',
        ]);

        return inertia('Owners/Drawings/Edit', [
            'drawing' => new DrawingResource($drawing),
            'owners' => OwnerResource::collection(
                Owner::query()
                    ->with('drawingAccount')
                    ->whereNotNull('drawing_account_id')
                    ->orderBy('name')
                    ->get()
            ),
            'bankAccounts' => AccountResource::collection(
                Account::query()
                    ->whereHas('accountType', fn ($query) => $query->whereIn('slug', ['cash-or-bank']))
                    ->with(['opening.transaction.currency', 'opening.transaction.lines.account'])
                    ->orderBy('name')
                    ->get()
            ),
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'homeCurrency' => CurrencyResource::make(
                Currency::query()->where('is_base_currency', true)->first()
            ),
        ]);
    }

    public function update(DrawingUpdateRequest $request, Drawing $drawing, TransactionService $transactionService)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($drawing, $validated, $transactionService) {
            $owner = Owner::with('drawingAccount')->findOrFail($validated['owner_id']);
            abort_unless($owner->drawing_account_id, 422, 'Selected owner does not have a drawing account.');

            $amount = (float) $validated['amount'];
            $transaction = $drawing->transaction()->with('lines')->first();
            $dateConversionService = app(DateConversionService::class);
            $date = $validated['date'] ? $dateConversionService->toGregorian($validated['date']) : null;
            $drawing->update([
                'number' => $validated['number'] ?? $drawing->number,
                'owner_id' => $owner->id,
                'date' => $date,
                'narration' => $validated['narration'] ?? null,
            ]);

            if ($transaction) {
                TransactionLine::where('transaction_id', $transaction->id)->forceDelete();
                $transaction->forceDelete();
            }

            $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => $validated['rate'],
                    'date' => $date,
                    'reference_type' => Drawing::class,
                    'reference_id' => $drawing->id,
                    'remark' => $validated['narration'] ?? "Drawing by {$owner->name}",
                ],
                lines: [
                    [
                        'account_id' => $validated['bank_account_id'],
                        'debit' => 0,
                        'credit' => $amount,
                        'remark' => "Drawing by {$owner->name}",
                        'remark_fa' => "برداشت توسط {$owner->name}",
                        'remark_ps' => "د". ' '. $owner->name.' '.'لخوا انځورګري',
                    ],
                    [
                        'account_id' => $owner->drawing_account_id,
                        'debit' => $amount,
                        'credit' => 0,
                        'remark' => "Drawing by {$owner->name}",
                        'remark_fa' => "برداشت توسط {$owner->name}",
                        'remark_ps' => "د". ' '. $owner->name.' '.'لخوا انځورګري',
                    ],
                ],
            );
        });

        return redirect()->route('drawings.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.drawing')]));
    }

    public function destroy(Request $request, Drawing $drawing)
    {
        DB::transaction(function () use ($drawing) {
            $transaction = $drawing->transaction()->first();
            if ($transaction) {
                $transaction->lines()->delete();
                $transaction->delete();
            }

            $drawing->delete();
        });

        return redirect()->route('drawings.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.drawing')]));
    }

    public function restore(Request $request, Drawing $drawing)
    {
        DB::transaction(function () use ($drawing) {
            $drawing->restore();

            $transaction = $drawing->transaction()->withTrashed()->first();
            if ($transaction) {
                $transaction->restore();
                $transaction->lines()->withTrashed()->restore();
            }
        });

        return redirect()->route('drawings.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.drawing')]));
    }

    public function reverse(Request $request, Drawing $drawing, TransactionService $transactionService)
    {
        $this->authorize('update', $drawing);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $transaction = $drawing->transaction()->firstOrFail();

        if ($transaction->status !== 'posted') {
            abort(422, __('general.only_posted_can_be_reversed'));
        }

        $transactionService->reverse($transaction, $validated['reason'], $drawing->number, Drawing::class);

        return back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.drawing')]));
    }

    public function forceDelete(Request $request, Drawing $drawing)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('drawings', (string) $drawing->id);

        return redirect()->route('drawings.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.drawing')]));
    }

    public function export(Request $request, \App\Services\SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', Drawing::class);

        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $drawings = Drawing::query()
            ->with(['owner', 'transaction.lines.account'])
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

        $rows = $drawings->map(function ($d) use ($dateService, $t) {
            $lines = $d->transaction?->lines ?? collect();
            $debitLine  = $lines->firstWhere(fn ($l) => (float) $l->debit > 0);
            $creditLine = $lines->firstWhere(fn ($l) => (float) $l->credit > 0);
            $amount = $creditLine
                ? ((float) $creditLine->credit > 0 ? $creditLine->credit : $creditLine->debit)
                : ($debitLine ? ($debitLine->debit ?: $debitLine->credit) : 0);

            return [
                'date'            => $d->date ? $dateService->toDisplay($d->date) : '-',
                'owner'           => $d->owner?->name ?? '-',
                'bank_account'    => $creditLine?->account?->name ?? '-',
                'drawing_account' => $debitLine?->account?->name ?? '-',
                'amount'          => (float) $amount,
                'narration'       => $d->narration ?? '-',
            ];
        })->all();

        $label = $t('sidebar', 'owners.drawing', 'Drawings');

        return $exporter->download([
            'filename'           => 'drawings-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'         => $label,
            'sheet_title'        => $label,
            'title'              => $label,
            'company_name'       => $companyName,
            'exported_on'        => now()->format('Y m d'),
            'rtl'                => $rtl,
            'include_row_number' => true,
            'row_number_label'   => $t('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'date',            'label' => $t('general', 'date', 'Date'), 'width' => 12],
                ['key' => 'owner',           'label' => $t('owner', 'owner', 'Owner'), 'width' => 18],
                ['key' => 'bank_account',    'label' => $t('general', 'bank_account', 'Bank Account'), 'width' => 18],
                ['key' => 'drawing_account', 'label' => $t('owner', 'drawing_account', 'Drawing Account'), 'width' => 18],
                ['key' => 'amount',          'label' => $t('general', 'amount', 'Amount'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'narration',       'label' => $t('general', 'remarks', 'Remarks'), 'width' => 20],
            ],
            'rows' => $rows,
        ]);
    }

    /**
     * @return array{0:string,1:float}
     */
    private function resolveCurrencyAndRate(Account $bankAccount): array
    {
        $bankAccount->loadMissing(['opening.transaction.currency']);

        $openingTransaction = $bankAccount->opening?->transaction;
        $currencyId = $openingTransaction?->currency_id;
        $rate = (float) ($openingTransaction?->rate ?? 1);

        if ($currencyId) {
            return [$currencyId, $rate];
        }

        $homeCurrency = Cache::get('home_currency');
        if (! $homeCurrency instanceof Currency) {
            $homeCurrency = Currency::query()
                ->where('is_base_currency', true)
                ->first()
                ?? Currency::query()->orderBy('name')->first();
        }

        abort_unless($homeCurrency, 422, 'No currency is configured for this company.');

        return [$homeCurrency?->id, (float) ($homeCurrency?->exchange_rate ?? 1)];
    }
}
