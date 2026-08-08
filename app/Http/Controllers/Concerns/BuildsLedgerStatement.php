<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Shared statement wiring for the customer and supplier controllers: both show
 * pages expose the same per-currency statement, with the same filters and the
 * same spreadsheet layout.
 */
trait BuildsLedgerStatement
{
    /**
     * Dates may arrive as either Gregorian or Jalali `YYYY-MM-DD`; the
     * DateConversionService decides which by inspecting the year, so the
     * validation here only has to guarantee a parseable shape.
     */
    protected function statementFilters(Request $request): array
    {
        $validated = $request->validate([
            'statement_from' => ['nullable', 'string', 'regex:/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/'],
            'statement_to' => ['nullable', 'string', 'regex:/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/'],
            'statement_currency' => ['nullable', 'string', 'exists:currencies,id'],
        ]);

        return [
            'date_from' => $validated['statement_from'] ?? null,
            'date_to' => $validated['statement_to'] ?? null,
            'currency_id' => $validated['statement_currency'] ?? null,
        ];
    }

    /**
     * Flatten the per-currency sections into one sheet. Every row carries its
     * currency code so the sections stay distinguishable once flattened, and
     * each section contributes its opening and closing balance as bracketing rows.
     */
    protected function statementExportRows(array $statement, callable $translate): array
    {
        $rows = [];

        foreach ($statement['sections'] ?? [] as $section) {
            $rows[] = [
                'date' => $statement['meta']['date_from_display'] ?? '-',
                'voucher_number' => '-',
                'document_type' => $translate('report', 'columns.opening_balance', 'Opening Balance'),
                'description' => $section['currency_name'] ?? '',
                'currency' => $section['currency_code'] ?? '',
                'rate' => '-',
                'debit' => $section['opening_balance'] > 0 ? $section['opening_balance'] : 0,
                'credit' => $section['opening_balance'] < 0 ? abs($section['opening_balance']) : 0,
                'balance' => $section['opening_label'] ?? '',
            ];

            foreach ($section['rows'] ?? [] as $row) {
                $rows[] = [
                    'date' => $row['date_display'] ?? $row['date'],
                    'voucher_number' => $row['voucher_number'],
                    'document_type' => $row['document_type'],
                    'description' => $row['description'],
                    'currency' => $section['currency_code'] ?? '',
                    'rate' => $row['rate'],
                    'debit' => $row['debit'],
                    'credit' => $row['credit'],
                    'balance' => $row['balance_label'],
                ];
            }

            $rows[] = [
                'date' => $statement['meta']['date_to_display'] ?? '-',
                'voucher_number' => '-',
                'document_type' => $translate('report', 'columns.closing_balance', 'Closing Balance'),
                'description' => $section['currency_name'] ?? '',
                'currency' => $section['currency_code'] ?? '',
                'rate' => '-',
                'debit' => $section['total_debit'] ?? 0,
                'credit' => $section['total_credit'] ?? 0,
                'balance' => $section['closing_label'] ?? '',
            ];
        }

        return $rows;
    }

    protected function statementExportColumns(callable $translate): array
    {
        return [
            ['key' => 'date', 'label' => $translate('general', 'date', 'Date'), 'width' => 14],
            ['key' => 'voucher_number', 'label' => $translate('general', 'number', 'Number'), 'width' => 16],
            ['key' => 'document_type', 'label' => $translate('general', 'type', 'Type'), 'width' => 18],
            ['key' => 'description', 'label' => $translate('general', 'description', 'Description'), 'width' => 30],
            ['key' => 'currency', 'label' => $translate('admin', 'currency.currency', 'Currency'), 'width' => 12],
            ['key' => 'rate', 'label' => $translate('general', 'rate', 'Rate'), 'align' => 'right', 'width' => 10],
            ['key' => 'debit', 'label' => $translate('general', 'debit', 'Debit'), 'type' => 'money', 'align' => 'right', 'width' => 14],
            ['key' => 'credit', 'label' => $translate('general', 'credit', 'Credit'), 'type' => 'money', 'align' => 'right', 'width' => 14],
            ['key' => 'balance', 'label' => $translate('general', 'balance', 'Balance'), 'align' => 'right', 'width' => 16],
        ];
    }
}
