<?php

namespace App\Models;

use Database\Factories\Purchase\PurchasePaymentFactory;

class PurchasePayment extends \App\Models\Purchase\PurchasePayment
{
    /**
     * Reuse the purchase payment factory for the root model alias.
     */
    protected static function newFactory(): PurchasePaymentFactory
    {
        return PurchasePaymentFactory::new();
    }
}
