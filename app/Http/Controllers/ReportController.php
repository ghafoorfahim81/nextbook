<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\SpreadsheetExportService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'report' => ['nullable', 'string', Rule::in(ReportService::REPORT_KEYS)],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'string', 'exists:branches,id'],
            'ledger_id' => ['nullable', 'string', 'exists:ledgers,id'],
            'customer_id' => ['nullable', 'string', 'exists:ledgers,id'],
            'supplier_id' => ['nullable', 'string', 'exists:ledgers,id'],
            'item_id' => ['nullable', 'string', 'exists:items,id'],
            'account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'warehouse_id' => ['nullable', 'string', 'exists:warehouses,id'],
            'type' => ['nullable', 'string'],
            'view_type' => ['nullable', 'string', Rule::in(['general', 'itemwise'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Reports/Index', [
            ...$this->reportService->getPageData($request->user(), $filters),
            'reportSelected' => $request->filled('report'),
        ]);
    }

    public function export(
        Request $request,
        SpreadsheetExportService $spreadsheetExportService,
    ): BinaryFileResponse
    {
        $filters = $request->validate([
            'report' => ['nullable', 'string', Rule::in(ReportService::REPORT_KEYS)],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'string', 'exists:branches,id'],
            'ledger_id' => ['nullable', 'string', 'exists:ledgers,id'],
            'customer_id' => ['nullable', 'string', 'exists:ledgers,id'],
            'supplier_id' => ['nullable', 'string', 'exists:ledgers,id'],
            'item_id' => ['nullable', 'string', 'exists:items,id'],
            'account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'warehouse_id' => ['nullable', 'string', 'exists:warehouses,id'],
            'type' => ['nullable', 'string'],
            'view_type' => ['nullable', 'string', Rule::in(['general', 'itemwise'])],
        ]);

        $export = $this->reportService->getExportData($request->user(), $filters);

        app(ActivityLogService::class)->logAction(
            eventType: 'export',
            reference: null,
            module: 'report',
            description: 'Report export generated.',
            metadata: [
                'action' => 'report_export',
                'report' => $filters['report'] ?? null,
                'filename' => $export['filename'],
                'filters' => $filters,
                'row_count' => count($export['rows']),
            ],
        );

        return $spreadsheetExportService->download($export);
    }
}
