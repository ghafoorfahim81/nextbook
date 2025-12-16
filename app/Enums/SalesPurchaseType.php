<?php

namespace App\Enums;

enum SalesPurchaseType: string
{
    case Cash = 'cash';
    case Credit = 'credit';
    case OnLoan = 'on_loan';
    public function getLabel(): string
    {
        return match($this) {
            self::Cash => __('enums.sales_purchase_type.cash'),
            self::Credit => __('enums.sales_purchase_type.credit'),
            self::OnLoan => __('enums.sales_purchase_type.on_loan'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    } 
}
