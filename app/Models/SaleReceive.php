<?php

namespace App\Models;

use Database\Factories\Sale\SaleReceiveFactory;

class SaleReceive extends \App\Models\Sale\SaleReceive
{
    /**
     * Reuse the sale receive factory for the root model alias.
     */
    protected static function newFactory(): SaleReceiveFactory
    {
        return SaleReceiveFactory::new();
    }
}
