<?php

namespace App\Enums;

/**
 * Groups the individual notification `type` strings into the ERP module they
 * belong to, so the notification centre can filter and summarise by module.
 */
enum NotificationCategory: string
{
    case Inventory = 'inventory';
    case Purchases = 'purchases';
    case Sales = 'sales';
    case Accounting = 'accounting';
    case Hr = 'hr';
    case Reports = 'reports';
    case System = 'system';

    /**
     * The notification types that belong to each module. `System` is the
     * catch-all for anything unmapped, so it deliberately owns no types here.
     *
     * @return array<int, string>
     */
    public function types(): array
    {
        return match ($this) {
            self::Inventory => ['low_stock', 'nearest_expiry'],
            self::Purchases => ['overdue_purchase', 'purchase_paid'],
            self::Sales => ['overdue_sale', 'overdue_invoice', 'sale_paid'],
            self::Accounting => ['low_balance', 'new_transaction'],
            self::Hr => [
                'contract_expiring',
                'document_expiring',
                'probation_ending',
                'leave_request_pending',
                'leave_approved',
                'leave_rejected',
            ],
            self::Reports => ['daily_summary', 'weekly_summary'],
            self::System => [],
        };
    }

    public static function forType(?string $type): self
    {
        foreach (self::cases() as $case) {
            if (in_array($type, $case->types(), true)) {
                return $case;
            }
        }

        return self::System;
    }

    /**
     * Every type that maps to a real module (everything except System).
     *
     * @return array<int, string>
     */
    public static function mappedTypes(): array
    {
        return array_merge(...array_map(
            fn (self $case) => $case->types(),
            self::cases(),
        ));
    }
}
