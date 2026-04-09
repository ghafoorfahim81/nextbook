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
        return inertia('Owners/Drawings/Create', [
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

            $drawing = Drawing::create([
                'owner_id' => $owner->id,
                'date' => $validated['date'],
                'narration' => $validated['narration'] ?? null,
            ]);

            $amount = (float) $validated['amount'];

            $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => $validated['rate'],
                    'date' => $validated['date'],
                    'reference_type' => Drawing::class,
                    'reference_id' => $drawing->id,
                    'remark' => $validated['narration'] ?? "Drawing by {$owner->name}",
                ],
                lines: [
                    [
                        'account_id' => $validated['bank_account_id'],
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                    [
                        'account_id' => $owner->drawing_account_id,
                        'debit' => $amount,
                        'credit' => 0,
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

        return response()->json([
            'data' => new DrawingResource($drawing),
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

            $drawing->update([
                'owner_id' => $owner->id,
                'date' => $validated['date'],
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
                    'date' => $validated['date'],
                    'reference_type' => Drawing::class,
                    'reference_id' => $drawing->id,
                    'remark' => $validated['narration'] ?? "Drawing by {$owner->name}",
                ],
                lines: [
                    [
                        'account_id' => $validated['bank_account_id'],
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                    [
                        'account_id' => $owner->drawing_account_id,
                        'debit' => $amount,
                        'credit' => 0,
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
