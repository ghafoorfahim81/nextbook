<?php

namespace App\Observers;

use App\Enums\TransactionStatus;
use App\Models\Transaction\Transaction;
use App\Services\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class TransactionObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Transactions are written inside TransactionService::post()'s database
     * transaction. Deferring to after the commit keeps notification work out of
     * that transaction and stops us announcing vouchers that got rolled back.
     */
    public function created(Transaction $transaction): void
    {
        if ($transaction->status !== TransactionStatus::POSTED->value) {
            return;
        }

        app(NotificationService::class)->notifySuperAdminsOfNewTransaction($transaction);
    }
}
