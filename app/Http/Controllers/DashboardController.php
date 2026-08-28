<?php

namespace App\Http\Controllers;

use App\Models\AccountTransfer\AccountTransfer;  // ← Remove the extra /AccountTransfer
use App\Models\ActivityLog;
use App\Models\JournalEntry\JournalClass;
use App\Models\Expense\Expense;  // ← Remove the extra /Expense
use App\Models\Expense\ExpenseDetail;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Payment\Payment;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Receipt\Receipt;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function index(Request $request): Response
    {
        // AccountTransfer::query()->forceDelete();
        // JournalClass::query()->forceDelete();
        // ExpenseDetail::query()->forceDelete();
        // Expense::query()->forceDelete();
        // PurchaseItem::query()->forceDelete();
        // Purchase::query()->forceDelete();
        // SaleItem::query()->forceDelete();
        // Sale::query()->forceDelete();
        // TransactionLine::query()->forceDelete();
        // Transaction::query()->forceDelete();
        // Receipt::query()->forceDelete();
        // Payment::query()->forceDelete();
        // ActivityLog::query()->forceDelete();
        // StockBalance::query()->forceDelete();
        // StockMovement::query()->forceDelete();
        // Item::query()->forceDelete();
        // Item::query()->update([
        //     'avg_cost' => 0,
        // ]); 
        $period = $this->resolvePeriod($request);

        return Inertia::render('Dashboard', [
            'dashboard' => $this->dashboardService->getDashboardData($request->user(), $period),
            'dashboardDataUrl' => route('dashboard.data'),
            'period' => $period,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboardService->getDashboardData($request->user(), $this->resolvePeriod($request))
        );
    }

    private function resolvePeriod(Request $request): string
    {
        $period = (string) $request->query('period', DashboardService::DEFAULT_PERIOD);

        return in_array($period, DashboardService::PERIODS, true) ? $period : DashboardService::DEFAULT_PERIOD;
    }
}
