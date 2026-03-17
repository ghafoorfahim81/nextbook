<?php

namespace App\Observers;

use App\Enums\TransactionStatus;
use App\Models\Transaction\Transaction;
use App\Services\NotificationService;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        if ($transaction->status !== TransactionStatus::POSTED->value) {
            return;
        }

        app(NotificationService::class)->notifySuperAdminsOfNewTransaction($transaction);
    }
}
