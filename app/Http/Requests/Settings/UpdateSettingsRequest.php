<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'appearance.sidebar_menus' => 'sometimes|array',

            // Item Management
            'item_management' => 'sometimes|array',
            'item_management.visible_fields' => 'sometimes|array',
            'item_management.visible_fields.*' => 'sometimes|boolean',

            // Sales Settings
            'sales' => 'sometimes|array',
            'sales.general_fields' => 'sometimes|array',
            'sales.general_fields.*' => 'sometimes|boolean',
            'sales.item_columns' => 'sometimes|array',
            'sales.item_columns.*' => 'sometimes|boolean',
            'sales.invoice_prefix' => 'sometimes|string|max:20',
            'sales.start_number' => 'sometimes|integer|min:1',
            'sales.terms' => 'sometimes|nullable|string|max:1000',
            'sales.due_days' => 'sometimes|integer|min:0|max:365',
            'sales.auto_reminders' => 'sometimes|boolean',
            'sales.reminder_days' => 'sometimes|integer|min:1|max:90',
            'sales.late_fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'sales.tax_percentage' => 'sometimes|numeric|min:0|max:100',
            'sales.auto_calculate_tax' => 'sometimes|boolean',
            'sales.show_ledger_transactions' => 'sometimes|boolean',
            'sales.show_item_transactions' => 'sometimes|boolean',

            // Sales Order
            'sales_order' => 'sometimes|array',
            'sales_order.general_fields' => 'sometimes|array',
            'sales_order.item_columns' => 'sometimes|array',
            'sales_order.invoice_prefix' => 'sometimes|string|max:20',
            'sales_order.start_number' => 'sometimes|integer|min:1',
            'sales_order.terms' => 'sometimes|nullable|string|max:1000',
            'sales_order.due_days' => 'sometimes|integer|min:0|max:365',

            // Sales Return
            'sales_return' => 'sometimes|array',
            'sales_return.general_fields' => 'sometimes|array',
            'sales_return.item_columns' => 'sometimes|array',
            'sales_return.invoice_prefix' => 'sometimes|string|max:20',
            'sales_return.start_number' => 'sometimes|integer|min:1',

            // Sales Quotation
            'sales_quotation' => 'sometimes|array',
            'sales_quotation.general_fields' => 'sometimes|array',
            'sales_quotation.item_columns' => 'sometimes|array',
            'sales_quotation.invoice_prefix' => 'sometimes|string|max:20',
            'sales_quotation.start_number' => 'sometimes|integer|min:1',

            // Purchase Settings
            'purchases' => 'sometimes|array',
            'purchases.general_fields' => 'sometimes|array',
            'purchases.general_fields.*' => 'sometimes|boolean',
            'purchases.item_columns' => 'sometimes|array',
            'purchases.item_columns.*' => 'sometimes|boolean',
            'purchases.invoice_prefix' => 'sometimes|string|max:20',
            'purchases.start_number' => 'sometimes|integer|min:1',
            'purchases.terms' => 'sometimes|nullable|string|max:1000',
            'purchases.due_days' => 'sometimes|integer|min:0|max:365',
            'purchases.auto_reminders' => 'sometimes|boolean',
            'purchases.reminder_days' => 'sometimes|integer|min:1|max:90',
            'purchases.late_fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'purchases.show_ledger_transactions' => 'sometimes|boolean',
            'purchases.show_item_transactions' => 'sometimes|boolean',

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

            // Tax & Currency
            'tax_currency' => 'sometimes|array',
            'tax_currency.tax_plus' => 'sometimes|boolean',
            'tax_currency.tax_minus' => 'sometimes|boolean',
            'tax_currency.multi_currency_opening' => 'sometimes|boolean',
            'tax_currency.spec_text' => 'sometimes|nullable|string|max:500',

            // Notifications
            'notifications' => 'sometimes|array',
            'notifications.email_notifications' => 'sometimes|boolean',
            'notifications.low_balance_alert' => 'sometimes|boolean',
            'notifications.overdue_invoice_alert' => 'sometimes|boolean',
            'notifications.new_transaction_alert' => 'sometimes|boolean',
            'notifications.daily_summary_report' => 'sometimes|boolean',
            'notifications.weekly_financial_summary' => 'sometimes|boolean',

            // Security
            'security' => 'sometimes|array',
            'security.session_timeout' => 'sometimes|integer|min:5|max:1440',
            'security.password_min_length' => 'sometimes|integer|min:6|max:32',
            'security.password_special_chars' => 'sometimes|boolean',
            'security.two_factor_auth' => 'sometimes|boolean',
            'security.login_attempts_limit' => 'sometimes|integer|min:3|max:10',
            'security.lock_reports' => 'sometimes|boolean',
            'security.lock_password' => 'sometimes|nullable|string|max:100',

            // Backup
            'backup' => 'sometimes|array',
            'backup.auto_backup' => 'sometimes|in:none,daily,weekly,monthly',
            'backup.backup_retention_days' => 'sometimes|integer|min:1|max:365',
            'backup.cloud_backup' => 'sometimes|boolean',
            'backup.cloud_provider' => 'sometimes|nullable|in:aws,google,azure',
            'backup.export_pdf' => 'sometimes|boolean',
            'backup.export_excel' => 'sometimes|boolean',
            'backup.export_csv' => 'sometimes|boolean',

            // Localization
            'localization' => 'sometimes|array',
            'localization.language' => 'sometimes|in:en,fa,ps',
            'localization.date_format' => 'sometimes|in:Y-m-d,d/m/Y,m/d/Y,d-m-Y',
            'localization.time_format' => 'sometimes|in:12h,24h',
            'localization.timezone' => 'sometimes|string|max:50',
            'localization.number_format' => 'sometimes|in:1,000.00,1.000,00',
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

