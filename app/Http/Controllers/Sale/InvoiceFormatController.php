<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\Sale\InvoiceFormat;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceFormatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        $formats = InvoiceFormat::where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $formats]);
    }

    public function store(Request $request, ActivityLogService $activityLogService): JsonResponse
    {
        $company = $request->user()->company;

        $validated = $this->validateFormat($request);
        $validated['company_id'] = $company->id;
        $validated['created_by'] = $request->user()->id;

        $format = DB::transaction(function () use ($validated, $company) {
            if (!empty($validated['is_default'])) {
                InvoiceFormat::where('company_id', $company->id)->update(['is_default' => false]);
            }

            return InvoiceFormat::create($validated);
        });

        $activityLogService->logAction(
            eventType: 'create',
            reference: $format,
            module: 'setting',
            description: "Invoice format '{$format->name}' created.",
            metadata: ['action' => 'invoice_format_create'],
        );

        return response()->json(['data' => $format], 201);
    }

    public function update(Request $request, InvoiceFormat $invoiceFormat, ActivityLogService $activityLogService): JsonResponse
    {
        $company = $request->user()->company;
        abort_if($invoiceFormat->company_id !== $company->id, 403);

        $validated = $this->validateFormat($request, $invoiceFormat->id);
        $validated['updated_by'] = $request->user()->id;

        $before = $invoiceFormat->toArray();

        DB::transaction(function () use ($validated, $invoiceFormat, $company) {
            if (!empty($validated['is_default'])) {
                InvoiceFormat::where('company_id', $company->id)
                    ->where('id', '!=', $invoiceFormat->id)
                    ->update(['is_default' => false]);
            }

            $invoiceFormat->update($validated);
        });

        $activityLogService->logUpdate(
            reference: $invoiceFormat,
            before: $before,
            after: $invoiceFormat->fresh()->toArray(),
            module: 'setting',
            description: "Invoice format '{$invoiceFormat->name}' updated.",
            metadata: ['action' => 'invoice_format_update'],
        );

        return response()->json(['data' => $invoiceFormat->fresh()]);
    }

    public function destroy(Request $request, InvoiceFormat $invoiceFormat, ActivityLogService $activityLogService): JsonResponse
    {
        $company = $request->user()->company;
        abort_if($invoiceFormat->company_id !== $company->id, 403);

        $activityLogService->logAction(
            eventType: 'delete',
            reference: $invoiceFormat,
            module: 'setting',
            description: "Invoice format '{$invoiceFormat->name}' deleted.",
            metadata: ['action' => 'invoice_format_delete'],
        );

        $invoiceFormat->delete();

        return response()->json(['message' => 'Invoice format deleted.']);
    }

    public function setDefault(Request $request, InvoiceFormat $invoiceFormat, ActivityLogService $activityLogService): JsonResponse
    {
        $company = $request->user()->company;
        abort_if($invoiceFormat->company_id !== $company->id, 403);

        DB::transaction(function () use ($invoiceFormat, $company) {
            InvoiceFormat::where('company_id', $company->id)->update(['is_default' => false]);
            $invoiceFormat->update(['is_default' => true]);
        });

        $activityLogService->logAction(
            eventType: 'update',
            reference: $invoiceFormat,
            module: 'setting',
            description: "Invoice format '{$invoiceFormat->name}' set as default.",
            metadata: ['action' => 'invoice_format_set_default'],
        );

        return response()->json(['data' => $invoiceFormat->fresh()]);
    }

    public function clone(Request $request, InvoiceFormat $invoiceFormat, ActivityLogService $activityLogService): JsonResponse
    {
        $company = $request->user()->company;
        abort_if($invoiceFormat->company_id !== $company->id, 403);

        $clone = $invoiceFormat->replicate(['is_default', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at']);
        $clone->name = $invoiceFormat->name . ' (Copy)';
        $clone->is_default = false;
        $clone->created_by = $request->user()->id;
        $clone->updated_by = null;
        $clone->save();

        $activityLogService->logAction(
            eventType: 'create',
            reference: $clone,
            module: 'setting',
            description: "Invoice format '{$clone->name}' cloned from '{$invoiceFormat->name}'.",
            metadata: ['action' => 'invoice_format_clone'],
        );

        return response()->json(['data' => $clone], 201);
    }

    private function validateFormat(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name'                                          => ['required', 'string', 'max:100'],
            'is_default'                                    => ['boolean'],
            'paper_size'                                    => ['required', Rule::in(['a4', 'a5', 'letter', 'thermal_80mm'])],
            'paper_orientation'                             => ['required', Rule::in(['portrait', 'landscape'])],
            'language'                                      => ['required', Rule::in(['en', 'fa', 'ps'])],
            'direction'                                     => ['required', Rule::in(['ltr', 'rtl'])],
            'margins'                                       => ['nullable', 'array'],
            'margins.top'                                   => ['nullable', 'numeric', 'min:0', 'max:50'],
            'margins.right'                                 => ['nullable', 'numeric', 'min:0', 'max:50'],
            'margins.bottom'                                => ['nullable', 'numeric', 'min:0', 'max:50'],
            'margins.left'                                  => ['nullable', 'numeric', 'min:0', 'max:50'],
            'header_config'                                 => ['nullable', 'array'],
            'header_config.show_logo'                       => ['boolean'],
            'header_config.show_company_name'               => ['boolean'],
            'header_config.show_company_address'            => ['boolean'],
            'header_config.show_company_phone'              => ['boolean'],
            'header_config.show_invoice_number'             => ['boolean'],
            'header_config.show_date'                       => ['boolean'],
            'header_config.show_due_date'                   => ['boolean'],
            'header_config.show_customer_name'              => ['boolean'],
            'header_config.show_customer_address'           => ['boolean'],
            'header_config.show_customer_phone'             => ['boolean'],
            'header_config.show_store_name'                 => ['boolean'],
            'header_config.logo_max_height'                 => ['nullable', 'integer', 'min:20', 'max:200'],
            'header_config.title_text'                      => ['nullable', 'string', 'max:50'],
            'item_columns'                                  => ['nullable', 'array'],
            'item_columns.visible'                          => ['nullable', 'array'],
            'item_columns.visible.*'                        => [Rule::in(['row', 'code', 'name', 'unit', 'quantity', 'unit_price', 'discount', 'tax', 'total'])],
            'item_columns.column_labels'                    => ['nullable', 'array'],
            'item_columns.column_labels.*'                  => ['nullable', 'string', 'max:50'],
            'item_columns.header_bg_color'                  => ['nullable', 'string', 'max:20'],
            'item_columns.header_text_color'                => ['nullable', 'string', 'max:20'],
            'item_columns.header_font_size'                 => ['nullable', 'integer', 'min:8', 'max:30'],
            'item_columns.row_font_size'                    => ['nullable', 'integer', 'min:8', 'max:30'],
            'item_columns.stripe_rows'                      => ['boolean'],
            'item_columns.stripe_color'                     => ['nullable', 'string', 'max:20'],
            'item_columns.show_borders'                     => ['boolean'],
            'optional_sections'                             => ['nullable', 'array'],
            'optional_sections.show_notes'                  => ['boolean'],
            'optional_sections.show_terms'                  => ['boolean'],
            'optional_sections.show_footer'                 => ['boolean'],
            'optional_sections.show_signature'              => ['boolean'],
            'optional_sections.show_qr'                     => ['boolean'],
            'optional_sections.show_barcode'                => ['boolean'],
            'optional_sections.show_bank_details'           => ['boolean'],
            'optional_sections.show_customer_tax_number'    => ['boolean'],
            'optional_sections.show_thank_you'              => ['boolean'],
            'optional_sections.show_summary_subtotal'       => ['boolean'],
            'optional_sections.show_summary_discount'       => ['boolean'],
            'optional_sections.show_summary_tax'            => ['boolean'],
            'optional_sections.items_per_page'              => ['nullable', 'integer', 'min:0', 'max:100'],
            'optional_sections.tax_display'                 => [Rule::in(['per_item', 'grouped'])],
            'appearance'                                    => ['nullable', 'array'],
            'appearance.bg_color'                           => ['nullable', 'string', 'max:20'],
            'appearance.font_family'                        => ['nullable', 'string', 'max:100'],
            'appearance.font_size'                          => ['nullable', 'integer', 'min:8', 'max:30'],
            'appearance.font_color'                         => ['nullable', 'string', 'max:20'],
            'appearance.border_show'                        => ['boolean'],
            'appearance.border_color'                       => ['nullable', 'string', 'max:20'],
            'appearance.border_width'                       => ['nullable', 'integer', 'min:0', 'max:5'],
            'appearance.summary_bg_color'                   => ['nullable', 'string', 'max:20'],
            'appearance.summary_text_color'                 => ['nullable', 'string', 'max:20'],
            'watermark_text'                                => ['nullable', 'string', 'max:50'],
            'footer_text'                                   => ['nullable', 'string', 'max:500'],
            'bank_details'                                  => ['nullable', 'string', 'max:500'],
            'thank_you_text'                                => ['nullable', 'string', 'max:200'],
            'custom_css'                                    => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
