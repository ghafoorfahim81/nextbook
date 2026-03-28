<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

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
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Reports/Index', [
            ...$this->reportService->getPageData($request->user(), $filters),
            'reportSelected' => $request->filled('report'),
        ]);
    }

    public function export(Request $request): StreamedResponse
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

        return response()->streamDownload(function () use ($export) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $export['headings']);

            foreach ($export['rows'] as $row) {
                fputcsv($handle, collect($row)->only($export['headings'])->all());
            }

            fclose($handle);
        }, $export['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
