<?php

namespace App\Enums;

enum LedgerType: string
{
    case CUSTOMER = 'customer';
    case SUPPLIER = 'supplier';
    case EMPLOYEE = 'employee';

    public function getLabel(): string
    {
        return match ($this) {
            self::CUSTOMER => __('enums.ledger_type.customer'),
            self::SUPPLIER => __('enums.ledger_type.supplier'),
            self::EMPLOYEE => __('enums.ledger_type.employee'),
        };
    }

    /**
     * Types whose normal balance is a credit — parties the company owes.
     *
     * A supplier we have not paid and an employee whose salary has accrued but
     * not been disbursed both sit on the credit side. Customers are the only
     * party type that normally carries a debit.
     */
    public function isPayableParty(): bool
    {
        return $this === self::SUPPLIER || $this === self::EMPLOYEE;
    }

    /**
     * The two types that make up the commercial party lists.
     *
     * Employees deliberately sit outside this set: every customer/supplier
     * picker, search endpoint and report must keep excluding them.
     */
    public static function commercialValues(): array
    {
        return [self::CUSTOMER->value, self::SUPPLIER->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
