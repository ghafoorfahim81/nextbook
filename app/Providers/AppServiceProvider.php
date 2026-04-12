<?php

namespace App\Providers;

use App\Models\Transaction\Transaction;
use App\Observers\TransactionObserver;
use App\Observers\ModelActivityObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Transaction::observe(TransactionObserver::class);

        Relation::enforceMorphMap([
            'user' => 'App\Models\User',
            'role' => 'App\Models\Role',
            'permission' => 'App\Models\Permission',
            'account' => 'App\Models\Account\Account',
            'ledger' => 'App\Models\Ledger\Ledger',
            'ledger_opening' => 'App\Models\Ledger\LedgerOpening',
            'ledger_transaction' => 'App\Models\Ledger\LedgerTransaction',
            'purchase' => 'App\Models\Purchase\Purchase',
            'sale' => 'App\Models\Sale\Sale',
            'expense' => 'App\Models\Expense\Expense',
            'income' => 'App\Models\Income\Income',
            'transfer' => 'App\Models\Transfer\Transfer',
            'item_transfer' => 'App\Models\ItemTransfer\ItemTransfer',
            'stock_adjustment' => 'App\Models\Adjustment\Adjustment',
            'opening' => 'App\Models\Inventory\StockOpening',
            'stock_out' => 'App\Models\Inventory\StockOut',
            'item' => 'App\Models\Inventory\Item',
            'owner' => 'App\Models\Owner\Owner',
            'drawing' => 'App\Models\Owner\Drawing',
            'journal_entry' => 'App\Models\JournalEntry\JournalEntry',

        ]);

        foreach (config('activity_log.observer.models', []) as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(ModelActivityObserver::class);
            }
        }
    }
}
