<?php

namespace App\Http\Requests\Preferences;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

    class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Appearance
            'appearance' => 'sometimes|array',
            'appearance.font_size' => 'sometimes|integer|min:10|max:24',
            'appearance.theme' => 'sometimes|in:dark,light,system',
            'appearance.decimal_places' => 'sometimes|integer|in:0,2,3',
            'appearance.records_per_page' => 'sometimes|integer|min:1|max:100',
            'appearance.sidebar_font_size' => 'sometimes|integer|min:10|max:24',
            'appearance.heading_font_size' => 'sometimes|integer|min:10|max:24',
            'appearance.table_header_font_size' => 'sometimes|integer|min:10|max:24',
            'appearance.table_content_font_size' => 'sometimes|integer|min:10|max:24',
            'appearance.button_font_size' => 'sometimes|integer|min:10|max:24',
            'appearance.label_font_size' => 'sometimes|integer|min:10|max:24',
            'appearance.input_font_size' => 'sometimes|integer|min:10|max:24',
            'appearance.select_font_size' => 'sometimes|integer|min:10|max:24',
            'appearance.textarea_font_size' => 'sometimes|integer|min:10|max:24',
            'appearance.sidebar_menus' => 'sometimes|array',

            // Item Management
            'item_management' => 'sometimes|array',
            'item_management.visible_fields' => 'sometimes|array',
            'item_management.visible_fields.*' => 'sometimes|boolean',
            'item_management.spec_text' => 'sometimes|nullable|string|max:500',

            // Sale Preferences    
            'sale' => 'sometimes|array',
            'sale.general_fields' => 'sometimes|array',
            'sale.general_fields.*' => 'sometimes|boolean',
            'sale.item_columns' => 'sometimes|array',
            'sale.item_columns.*' => 'sometimes|boolean',
            'sale.invoice_prefix' => 'sometimes|string|max:20',
            'sale.start_number' => 'sometimes|integer|min:1',
            'sale.terms' => 'sometimes|nullable|string|max:1000',
            'sale.due_days' => 'sometimes|integer|min:0|max:365',
            'sale.auto_reminders' => 'sometimes|boolean',
            'sale.reminder_days' => 'sometimes|integer|min:1|max:90',
            'sale.late_fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'sale.tax_percentage' => 'sometimes|numeric|min:0|max:100',
            'sale.auto_calculate_tax' => 'sometimes|boolean',
            'sale.show_ledger_transactions' => 'sometimes|boolean',
            'sale.show_item_transactions' => 'sometimes|boolean',

            // Sale Order
            'sale_order' => 'sometimes|array',
            'sale_order.general_fields' => 'sometimes|array',
            'sale_order.item_columns' => 'sometimes|array',
            'sale_order.invoice_prefix' => 'sometimes|string|max:20',
            'sale_order.start_number' => 'sometimes|integer|min:1',
            'sale_order.terms' => 'sometimes|nullable|string|max:1000',
            'sale_order.due_days' => 'sometimes|integer|min:0|max:365',

            // Sale Return
            'sale_return' => 'sometimes|array',
            'sale_return.general_fields' => 'sometimes|array',
            'sale_return.item_columns' => 'sometimes|array',
            'sale_return.invoice_prefix' => 'sometimes|string|max:20',
            'sale_return.start_number' => 'sometimes|integer|min:1',
            'sale_return.accepted_return_days' => 'sometimes|integer|min:0|max:365',
            // Sale Quotation
            'sale_quotation' => 'sometimes|array',
            'sale_quotation.general_fields' => 'sometimes|array',
            'sale_quotation.item_columns' => 'sometimes|array',
            'sale_quotation.invoice_prefix' => 'sometimes|string|max:20',
            'sale_quotation.start_number' => 'sometimes|integer|min:1',

            // Purchase Preferences
            'purchase' => 'sometimes|array',
            'purchase.general_fields' => 'sometimes|array',
            'purchase.general_fields.*' => 'sometimes|boolean',
            'purchase.item_columns' => 'sometimes|array',
            'purchase.item_columns.*' => 'sometimes|boolean',
            'purchase.invoice_prefix' => 'sometimes|string|max:20',
            'purchase.start_number' => 'sometimes|integer|min:1',
            'purchase.terms' => 'sometimes|nullable|string|max:1000',
            'purchase.due_days' => 'sometimes|integer|min:0|max:365',
            'purchase.auto_reminders' => 'sometimes|boolean',
            'purchase.reminder_days' => 'sometimes|integer|min:1|max:90',
            'purchase.late_fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'purchase.show_ledger_transactions' => 'sometimes|boolean',
            'purchase.show_item_transactions' => 'sometimes|boolean',

            // Purchase Order
            'purchase_order' => 'sometimes|array',
            'purchase_order.general_fields' => 'sometimes|array',
            'purchase_order.item_columns' => 'sometimes|array',
            'purchase_order.invoice_prefix' => 'sometimes|string|max:20',
            'purchase_order.start_number' => 'sometimes|integer|min:1',

            // Purchase Return
            'purchase_return' => 'sometimes|array',
            'purchase_return.general_fields' => 'sometimes|array',
            'purchase_return.item_columns' => 'sometimes|array',
            'purchase_return.invoice_prefix' => 'sometimes|string|max:20',
            'purchase_return.start_number' => 'sometimes|integer|min:1',

            // Purchase Quotation
            'purchase_quotation' => 'sometimes|array',
            'purchase_quotation.general_fields' => 'sometimes|array',
            'purchase_quotation.item_columns' => 'sometimes|array',
            'purchase_quotation.invoice_prefix' => 'sometimes|string|max:20',
            'purchase_quotation.start_number' => 'sometimes|integer|min:1',

            // Receipt & Payment
            'receipt_payment' => 'sometimes|array',
            'receipt_payment.visible_fields' => 'sometimes|array',
            'receipt_payment.visible_fields.*' => 'sometimes|boolean',
            'receipt_payment.default_cash_account' => 'sometimes|nullable|string',
            'receipt_payment.auto_sequence' => 'sometimes|boolean',
            'receipt_payment.require_approval' => 'sometimes|boolean',
            'receipt_payment.lock_after_days' => 'sometimes|integer|min:0|max:365',

            // Tax & Currency Preferences
            'tax_currency' => 'sometimes|array',
            'tax_currency.tax_plus' => 'sometimes|boolean',
            'tax_currency.tax_minus' => 'sometimes|boolean',
            'tax_currency.multi_currency_opening' => 'sometimes|boolean',

            // Notifications Preferences
            'notifications' => 'sometimes|array',
            'notifications.email_notifications' => 'sometimes|boolean',
            'notifications.low_balance_alert' => 'sometimes|boolean',
            'notifications.overdue_invoice_alert' => 'sometimes|boolean',
            'notifications.new_transaction_alert' => 'sometimes|boolean',
            'notifications.daily_summary_report' => 'sometimes|boolean',
            'notifications.weekly_financial_summary' => 'sometimes|boolean',

            // Security Preferences
            'security' => 'sometimes|array',
            'security.session_timeout' => 'sometimes|integer|min:5|max:1440',
            'security.password_min_length' => 'sometimes|integer|min:6|max:32',
            'security.password_special_chars' => 'sometimes|boolean',
            'security.two_factor_auth' => 'sometimes|boolean',
            'security.login_attempts_limit' => 'sometimes|integer|min:3|max:10',
            'security.lock_reports' => 'sometimes|boolean',
            'security.lock_password' => 'sometimes|nullable|string|max:100',

            // Backup Preferences
            'backup' => 'sometimes|array',
            'backup.auto_backup' => 'sometimes|in:none,daily,weekly,monthly',
            'backup.backup_retention_days' => 'sometimes|integer|min:1|max:365',
            'backup.cloud_backup' => 'sometimes|boolean',
            'backup.cloud_provider' => 'sometimes|nullable|in:aws,google,azure',
            'backup.export_pdf' => 'sometimes|boolean',
            'backup.export_excel' => 'sometimes|boolean',
            'backup.export_csv' => 'sometimes|boolean',

            // Localization Preferences
            'localization' => 'sometimes|array',
            'localization.language' => 'sometimes|in:en,fa,ps',
            'localization.date_format' => 'sometimes|in:Y-m-d,d/m/Y,m/d/Y,d-m-Y',
            'localization.time_format' => 'sometimes|in:12h,24h',
            'localization.timezone' => 'sometimes|string|max:50',
            'localization.number_format' => ['sometimes', Rule::in(['1,000.00', '1.000,00'])],
            'localization.first_day_of_week' => 'sometimes|in:sunday,monday,saturday',

            // Display
            'display' => 'sometimes|array',
            'display.theme' => 'sometimes|in:light,dark,system',
            'display.dashboard_charts' => 'sometimes|boolean',
            'display.records_per_page' => 'sometimes|integer|in:10,25,50,100',
            'display.show_currency_symbol' => 'sometimes|boolean',
            'display.compact_view' => 'sometimes|boolean',
            'display.sidebar_collapsed' => 'sometimes|boolean',
        ];
    }
}

