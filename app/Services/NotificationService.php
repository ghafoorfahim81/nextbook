<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\UserStatus;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\Transaction\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    private const TYPE_PREFERENCE_MAP = [
        'low_balance' => 'notifications.low_balance_alert',
        'low_stock' => 'notifications.low_item_balance_alert',
        'nearest_expiry' => 'notifications.nearest_expiry_alert',
        'overdue_purchase' => 'notifications.overdue_purchase_alert',
        'overdue_sale' => 'notifications.overdue_sale_alert',
        'overdue_invoice' => 'notifications.overdue_invoice_alert',
        'sale_paid' => 'notifications.sale_paid_alert',
        'purchase_paid' => 'notifications.purchase_paid_alert',
        'new_transaction' => 'notifications.new_transaction_alert',
        'daily_summary' => 'notifications.daily_summary_report',
        'weekly_summary' => 'notifications.weekly_financial_summary',
    ];

    public function getNotificationCenter(User $user, int $limit = 8): array
    {
        $items = Notification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get();

        return [
            'unread_count' => Notification::query()
                ->where('user_id', $user->id)
                ->where('is_read', false)
                ->count(),
            'items' => NotificationResource::collection($items)->resolve(),
        ];
    }

    public function notifySuperAdminsOfNewTransaction(Transaction $transaction): void
    {
        $users = User::query()
            ->role('super-admin')
            ->whereNull('deleted_at')
            ->where('status', UserStatus::ACTIVE->value)
            ->get();

        foreach ($users as $user) {
            $this->notifyUser(
                user: $user,
                type: 'new_transaction',
                title: 'New Posted Transaction',
                message: sprintf(
                    'A posted transaction%s was created on %s.',
                    $transaction->voucher_number ? ' '.$transaction->voucher_number : '',
                    Carbon::parse($transaction->date)->toDateString()
                ),
                data: [
                    'transaction_id' => $transaction->id,
                    'reference_type' => $transaction->reference_type,
                    'reference_id' => $transaction->reference_id,
                    'branch_id' => $transaction->branch_id,
                ],
                dedupeKey: 'transaction:'.$transaction->id,
                dedupeWindow: 'forever',
            );
        }
    }

    public function runLowBalanceCheck(): void
    {
        foreach ($this->usersByBranch('notifications.low_balance_alert') as $branchId => $users) {
            $accounts = $this->lowBalanceAccounts((string) $branchId);

            foreach ($accounts as $account) {
                foreach ($users as $user) {
                    $this->notifyUser(
                        user: $user,
                        type: 'low_balance',
                        title: 'Low Balance Alert',
                        message: sprintf(
                            'Account %s has a negative balance of %s.',
                            $account->name,
                            $this->formatMoney($account->balance)
                        ),
                        data: [
                            'account_id' => $account->id,
                            'branch_id' => $branchId,
                            'balance' => $this->moneyValue($account->balance),
                        ],
                        dedupeKey: 'low-balance:'.$account->id,
                    );
                }
            }
        }
    }

    public function runLowStockCheck(): void
    {
        foreach ($this->usersByBranch('notifications.low_item_balance_alert') as $branchId => $users) {
            $items = $this->lowStockItems((string) $branchId);

            foreach ($items as $item) {
                foreach ($users as $user) {
                    $this->notifyUser(
                        user: $user,
                        type: 'low_stock',
                        title: 'Low Stock Alert',
                        message: sprintf(
                            'Item %s is below minimum stock. Available: %s, minimum: %s.',
                            $item->name,
                            $this->formatQuantity($item->current_quantity),
                            $this->formatQuantity($item->minimum_stock)
                        ),
                        data: [
                            'item_id' => $item->id,
                            'branch_id' => $branchId,
                            'quantity' => $this->quantityValue($item->current_quantity),
                            'minimum_stock' => $this->quantityValue($item->minimum_stock),
                        ],
                        dedupeKey: 'low-stock:'.$item->id,
                    );
                }
            }
        }
    }

    public function runExpiryCheck(): void
    {
        foreach ($this->usersByBranch('notifications.nearest_expiry_alert') as $branchId => $users) {
            $batches = $this->expiringBatches((string) $branchId);

            foreach ($batches as $batch) {
                foreach ($users as $user) {
                    $this->notifyUser(
                        user: $user,
                        type: 'nearest_expiry',
                        title: 'Nearest Expiry Alert',
                        message: sprintf(
                            'Item %s expires on %s%s.',
                            $batch->item_name,
                            $batch->expire_date,
                            $batch->batch ? ' (batch '.$batch->batch.')' : ''
                        ),
                        data: [
                            'stock_balance_id' => $batch->id,
                            'item_id' => $batch->item_id,
                            'warehouse_id' => $batch->warehouse_id,
                            'batch' => $batch->batch,
                            'expire_date' => $batch->expire_date,
                            'branch_id' => $branchId,
                        ],
                        dedupeKey: 'expiry:'.$batch->id,
                    );
                }
            }
        }
    }

    public function runOverdueChecks(): void
    {
        $today = Carbon::today();

        foreach ($this->usersByBranch('notifications.overdue_purchase_alert') as $branchId => $users) {
            $purchases = $this->purchaseSettlementStatus((string) $branchId)
                ->filter(fn (array $purchase) => $purchase['outstanding_amount'] > 0
                    && $purchase['due_date']
                    && Carbon::parse($purchase['due_date'])->lt($today));

            foreach ($purchases as $purchase) {
                foreach ($users as $user) {
                    $this->notifyUser(
                        user: $user,
                        type: 'overdue_purchase',
                        title: 'Overdue Purchase Alert',
                        message: sprintf(
                            'Purchase #%s is overdue with %s still payable.',
                            $purchase['number'],
                            $this->formatMoney($purchase['outstanding_amount'])
                        ),
                        data: $purchase,
                        dedupeKey: 'overdue-purchase:'.$purchase['id'],
                    );
                }
            }
        }

        foreach ($this->usersByBranch('notifications.overdue_sale_alert') as $branchId => $users) {
            $sales = $this->saleSettlementStatus((string) $branchId)
                ->filter(fn (array $sale) => $sale['outstanding_amount'] > 0
                    && $sale['due_date']
                    && Carbon::parse($sale['due_date'])->lt($today));

            foreach ($sales as $sale) {
                foreach ($users as $user) {
                    $this->notifyUser(
                        user: $user,
                        type: 'overdue_sale',
                        title: 'Overdue Sale Alert',
                        message: sprintf(
                            'Sale #%s is overdue with %s still receivable.',
                            $sale['number'],
                            $this->formatMoney($sale['outstanding_amount'])
                        ),
                        data: $sale,
                        dedupeKey: 'overdue-sale:'.$sale['id'],
                    );
                }
            }
        }

        foreach ($this->usersByBranch('notifications.overdue_invoice_alert') as $branchId => $users) {
            $sales = $this->saleSettlementStatus((string) $branchId)
                ->filter(fn (array $sale) => $sale['outstanding_amount'] > 0
                    && $sale['due_date']
                    && Carbon::parse($sale['due_date'])->lt($today));

            foreach ($sales as $sale) {
                foreach ($users as $user) {
                    $this->notifyUser(
                        user: $user,
                        type: 'overdue_invoice',
                        title: 'Overdue Invoice Alert',
                        message: sprintf(
                            'Invoice #%s is overdue with %s unpaid.',
                            $sale['number'],
                            $this->formatMoney($sale['outstanding_amount'])
                        ),
                        data: $sale,
                        dedupeKey: 'overdue-invoice:'.$sale['id'],
                    );
                }
            }
        }
    }

    public function runPaidSaleCheck(): void
    {
        $today = Carbon::today()->toDateString();

        foreach ($this->usersByBranch('notifications.sale_paid_alert') as $branchId => $users) {
            $sales = $this->saleSettlementStatus((string) $branchId)
                ->filter(fn (array $sale) => $sale['paid_on'] === $today);

            foreach ($sales as $sale) {
                foreach ($users as $user) {
                    $this->notifyUser(
                        user: $user,
                        type: 'sale_paid',
                        title: 'Sale Paid',
                        message: sprintf('Sale #%s has been fully paid.', $sale['number']),
                        data: $sale,
                        dedupeKey: 'sale-paid:'.$sale['id'],
                        dedupeWindow: 'forever',
                    );
                }
            }
        }
    }

    public function runPaidPurchaseCheck(): void
    {
        $today = Carbon::today()->toDateString();

        foreach ($this->usersByBranch('notifications.purchase_paid_alert') as $branchId => $users) {
            $purchases = $this->purchaseSettlementStatus((string) $branchId)
                ->filter(fn (array $purchase) => $purchase['paid_on'] === $today);

            foreach ($purchases as $purchase) {
                foreach ($users as $user) {
                    $this->notifyUser(
                        user: $user,
                        type: 'purchase_paid',
                        title: 'Purchase Paid',
                        message: sprintf('Purchase #%s has been fully paid.', $purchase['number']),
                        data: $purchase,
                        dedupeKey: 'purchase-paid:'.$purchase['id'],
                        dedupeWindow: 'forever',
                    );
                }
            }
        }
    }

    public function sendDailyTransactionSummaries(): void
    {
        $today = Carbon::today();
        $dateKey = $today->toDateString();

        foreach ($this->usersByBranch('notifications.daily_summary_report') as $branchId => $users) {
            $summary = $this->dailySummary((string) $branchId, $today);

            foreach ($users as $user) {
                $this->notifyUser(
                    user: $user,
                    type: 'daily_summary',
                    title: 'Daily Transaction Summary',
                    message: sprintf(
                        'Posted transactions: %d. Sales: %s. Purchases: %s. Receipts: %s. Payments: %s.',
                        $summary['transactions_count'],
                        $this->formatMoney($summary['sales_total']),
                        $this->formatMoney($summary['purchases_total']),
                        $this->formatMoney($summary['receipts_total']),
                        $this->formatMoney($summary['payments_total'])
                    ),
                    data: [
                        ...$summary,
                        'branch_id' => $branchId,
                        'date' => $dateKey,
                    ],
                    dedupeKey: 'daily-summary:'.$dateKey,
                    dedupeWindow: 'forever',
                );
            }
        }
    }

    public function sendWeeklyFinancialSummaries(): void
    {
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays(6);
        $periodKey = $startDate->toDateString().'_'.$endDate->toDateString();

        foreach ($this->usersByBranch('notifications.weekly_financial_summary') as $branchId => $users) {
            $summary = $this->weeklySummary((string) $branchId, $startDate, $endDate);

            foreach ($users as $user) {
                $this->notifyUser(
                    user: $user,
                    type: 'weekly_summary',
                    title: 'Weekly Financial Summary',
                    message: sprintf(
                        'Sales: %s. Purchases: %s. Profit: %s.',
                        $this->formatMoney($summary['sales_total']),
                        $this->formatMoney($summary['purchases_total']),
                        $this->formatMoney($summary['profit'])
                    ),
                    data: [
                        ...$summary,
                        'branch_id' => $branchId,
                        'from' => $startDate->toDateString(),
                        'to' => $endDate->toDateString(),
                    ],
                    dedupeKey: 'weekly-summary:'.$periodKey,
                    dedupeWindow: 'forever',
                );
            }
        }
    }

    public function notifyUser(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?string $dedupeKey = null,
        string $dedupeWindow = 'day',
    ): ?Notification {
        if (! $this->userAllows($user, $type)) {
            return null;
        }

        if ($dedupeKey && $this->duplicateExists($user, $type, $dedupeKey, $dedupeWindow)) {
            return null;
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'data' => [
                ...$data,
                'dedupe_key' => $dedupeKey,
            ],
        ]);

        $this->maybeSendEmail($user, $title, $message);

        return $notification;
    }

    protected function userAllows(User $user, string $type): bool
    {
        $preferenceKey = self::TYPE_PREFERENCE_MAP[$type] ?? null;

        if (! $preferenceKey) {
            return true;
        }

        return (bool) $user->getPreference($preferenceKey, false);
    }

    protected function duplicateExists(User $user, string $type, string $dedupeKey, string $dedupeWindow): bool
    {
        $query = Notification::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('data->dedupe_key', $dedupeKey);

        if ($dedupeWindow === 'day') {
            $query->whereDate('created_at', Carbon::today()->toDateString());
        }

        return $query->exists();
    }

    protected function maybeSendEmail(User $user, string $title, string $message): void
    {
        if (! $user->email || ! (bool) $user->getPreference('notifications.email_notifications', true)) {
            return;
        }

        try {
            Mail::raw($message, function ($mail) use ($user, $title) {
                $mail->to($user->email)->subject($title);
            });
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    protected function usersByBranch(string $preferenceKey): Collection
    {
        return User::query()
            ->whereNull('deleted_at')
            ->where('status', UserStatus::ACTIVE->value)
            ->whereNotNull('branch_id')
            ->get()
            ->filter(fn (User $user) => (bool) $user->getPreference($preferenceKey, false))
            ->groupBy('branch_id');
    }

    protected function lowBalanceAccounts(string $branchId): Collection
    {
        $balances = DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($branchId) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $branchId)
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->whereNull('tl.deleted_at')
            ->groupBy('tl.account_id')
            ->selectRaw('tl.account_id, COALESCE(SUM((COALESCE(tl.debit, 0) - COALESCE(tl.credit, 0)) * t.rate), 0) as balance');

        return DB::table('accounts as a')
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('balances.account_id', '=', 'a.id'))
            ->where('a.branch_id', $branchId)
            ->whereNull('a.deleted_at')
            ->where('a.is_active', true)
            ->where('at.slug', 'cash-or-bank')
            ->whereRaw('COALESCE(balances.balance, 0) < 0')
            ->orderBy('a.name')
            ->get([
                'a.id',
                'a.name',
                DB::raw('COALESCE(balances.balance, 0) as balance'),
            ]);
    }

    protected function lowStockItems(string $branchId): Collection
    {
        $stockTotals = DB::table('stock_balances')
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->groupBy('item_id')
            ->selectRaw('item_id, COALESCE(SUM(quantity), 0) as current_quantity');

        return DB::table('items as i')
            ->leftJoinSub($stockTotals, 'stock_totals', fn ($join) => $join->on('stock_totals.item_id', '=', 'i.id'))
            ->where('i.branch_id', $branchId)
            ->whereNull('i.deleted_at')
            ->whereNotNull('i.minimum_stock')
            ->whereRaw('COALESCE(stock_totals.current_quantity, 0) <= i.minimum_stock')
            ->orderBy('i.name')
            ->get([
                'i.id',
                'i.name',
                'i.minimum_stock',
                DB::raw('COALESCE(stock_totals.current_quantity, 0) as current_quantity'),
            ]);
    }

    protected function expiringBatches(string $branchId): Collection
    {
        $today = Carbon::today()->toDateString();
        $cutoff = Carbon::today()->addDays(30)->toDateString();

        return DB::table('stock_balances')
            ->join('items as i', function ($join) {
                $join->on('i.id', '=', 'stock_balances.item_id')
                    ->whereNull('i.deleted_at');
            })
            ->leftJoin('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'stock_balances.warehouse_id')
                    ->whereNull('w.deleted_at');
            })
            ->where('stock_balances.branch_id', $branchId)
            ->whereNull('stock_balances.deleted_at')
            ->where('stock_balances.quantity', '>', 0)
            ->whereBetween('stock_balances.expire_date', [$today, $cutoff])
            ->orderBy('stock_balances.expire_date')
            ->get([
                'stock_balances.id',
                'stock_balances.item_id',
                'stock_balances.warehouse_id',
                'stock_balances.batch',
                'stock_balances.expire_date',
                'i.name as item_name',
                'w.name as warehouse_name',
            ]);
    }

    protected function saleSettlementStatus(string $branchId): Collection
    {
        return $this->buildSettlementStatus(
            documents: $this->salesWithOutstandingAmounts($branchId),
            settlements: $this->receiptLedgerAllocations($branchId),
            ledgerKey: 'ledger_id',
        );
    }

    protected function purchaseSettlementStatus(string $branchId): Collection
    {
        return $this->buildSettlementStatus(
            documents: $this->purchasesWithOutstandingAmounts($branchId),
            settlements: $this->paymentLedgerAllocations($branchId),
            ledgerKey: 'ledger_id',
        );
    }

    protected function salesWithOutstandingAmounts(string $branchId): Collection
    {
        return DB::table('sales as s')
            ->leftJoin('transactions as t', function ($join) use ($branchId) {
                $join->on('t.reference_id', '=', 's.id')
                    ->where('t.reference_type', '=', \App\Models\Sale\Sale::class)
                    ->where('t.branch_id', '=', $branchId)
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->leftJoin('transaction_lines as tl', function ($join) {
                $join->on('tl.transaction_id', '=', 't.id')
                    ->whereNull('tl.deleted_at');
            })
            ->leftJoin('accounts as a', function ($join) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->whereNull('a.deleted_at');
            })
            ->leftJoin('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('s.branch_id', $branchId)
            ->where('s.status', TransactionStatus::POSTED->value)
            ->whereNull('s.deleted_at')
            ->groupBy('s.id', 's.customer_id', 's.number', 's.date', 's.due_date', 's.type')
            ->selectRaw('s.id, s.customer_id as ledger_id, s.number, s.date, s.due_date, s.type')
            ->selectRaw("COALESCE(SUM(CASE WHEN at.slug IN ('cash-or-bank', 'account-receivable') AND COALESCE(tl.debit, 0) > 0 THEN tl.debit * t.rate ELSE 0 END), 0) as total_amount")
            ->selectRaw("COALESCE(SUM(CASE WHEN a.slug = 'account-receivable' AND COALESCE(tl.debit, 0) > 0 THEN tl.debit * t.rate ELSE 0 END), 0) as open_amount")
            ->orderBy('s.date')
            ->orderBy('s.number')
            ->get()
            ->map(fn ($sale) => [
                'id' => $sale->id,
                'ledger_id' => $sale->ledger_id,
                'number' => (string) $sale->number,
                'date' => (string) $sale->date,
                'due_date' => $sale->due_date ? (string) $sale->due_date : null,
                'type' => (string) $sale->type,
                'total_amount' => $this->moneyValue($sale->total_amount),
                'open_amount' => $this->moneyValue($sale->open_amount),
            ]);
    }

    protected function purchasesWithOutstandingAmounts(string $branchId): Collection
    {
        return DB::table('purchases as p')
            ->leftJoin('transactions as t', function ($join) use ($branchId) {
                $join->on('t.reference_id', '=', 'p.id')
                    ->where('t.reference_type', '=', \App\Models\Purchase\Purchase::class)
                    ->where('t.branch_id', '=', $branchId)
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->leftJoin('transaction_lines as tl', function ($join) {
                $join->on('tl.transaction_id', '=', 't.id')
                    ->whereNull('tl.deleted_at');
            })
            ->leftJoin('accounts as a', function ($join) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->whereNull('a.deleted_at');
            })
            ->leftJoin('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('p.branch_id', $branchId)
            ->where('p.status', TransactionStatus::POSTED->value)
            ->whereNull('p.deleted_at')
            ->groupBy('p.id', 'p.supplier_id', 'p.number', 'p.date', 'p.due_date', 'p.type')
            ->selectRaw('p.id, p.supplier_id as ledger_id, p.number, p.date, p.due_date, p.type')
            ->selectRaw("COALESCE(SUM(CASE WHEN at.slug IN ('cash-or-bank', 'account-payable') AND COALESCE(tl.credit, 0) > 0 THEN tl.credit * t.rate ELSE 0 END), 0) as total_amount")
            ->selectRaw("COALESCE(SUM(CASE WHEN a.slug = 'account-payable' AND COALESCE(tl.credit, 0) > 0 THEN tl.credit * t.rate ELSE 0 END), 0) as open_amount")
            ->orderBy('p.date')
            ->orderBy('p.number')
            ->get()
            ->map(fn ($purchase) => [
                'id' => $purchase->id,
                'ledger_id' => $purchase->ledger_id,
                'number' => (string) $purchase->number,
                'date' => (string) $purchase->date,
                'due_date' => $purchase->due_date ? (string) $purchase->due_date : null,
                'type' => (string) $purchase->type,
                'total_amount' => $this->moneyValue($purchase->total_amount),
                'open_amount' => $this->moneyValue($purchase->open_amount),
            ]);
    }

    protected function receiptLedgerAllocations(string $branchId): Collection
    {
        return DB::table('receipts as r')
            ->join('transactions as t', function ($join) use ($branchId) {
                $join->on('t.reference_id', '=', 'r.id')
                    ->where('t.reference_type', '=', \App\Models\Receipt\Receipt::class)
                    ->where('t.branch_id', '=', $branchId)
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('transaction_lines as tl', function ($join) {
                $join->on('tl.transaction_id', '=', 't.id')
                    ->whereNull('tl.deleted_at');
            })
            ->join('accounts as a', function ($join) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->whereNull('a.deleted_at');
            })
            ->where('r.branch_id', $branchId)
            ->whereNull('r.deleted_at')
            ->where('a.slug', 'account-receivable')
            ->where('tl.credit', '>', 0)
            ->groupBy('r.id', 'r.ledger_id', 'r.date')
            ->orderBy('r.date')
            ->orderBy('r.id')
            ->get([
                'r.id',
                'r.ledger_id',
                'r.date',
                DB::raw('COALESCE(SUM(tl.credit * t.rate), 0) as amount'),
            ])
            ->map(fn ($receipt) => [
                'id' => $receipt->id,
                'ledger_id' => $receipt->ledger_id,
                'date' => (string) $receipt->date,
                'amount' => $this->moneyValue($receipt->amount),
            ]);
    }

    protected function paymentLedgerAllocations(string $branchId): Collection
    {
        return DB::table('payments as p')
            ->join('transactions as t', function ($join) use ($branchId) {
                $join->on('t.reference_id', '=', 'p.id')
                    ->where('t.reference_type', '=', \App\Models\Payment\Payment::class)
                    ->where('t.branch_id', '=', $branchId)
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('transaction_lines as tl', function ($join) {
                $join->on('tl.transaction_id', '=', 't.id')
                    ->whereNull('tl.deleted_at');
            })
            ->join('accounts as a', function ($join) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->whereNull('a.deleted_at');
            })
            ->where('p.branch_id', $branchId)
            ->whereNull('p.deleted_at')
            ->where('a.slug', 'account-payable')
            ->where('tl.debit', '>', 0)
            ->groupBy('p.id', 'p.ledger_id', 'p.date')
            ->orderBy('p.date')
            ->orderBy('p.id')
            ->get([
                'p.id',
                'p.ledger_id',
                'p.date',
                DB::raw('COALESCE(SUM(tl.debit * t.rate), 0) as amount'),
            ])
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'ledger_id' => $payment->ledger_id,
                'date' => (string) $payment->date,
                'amount' => $this->moneyValue($payment->amount),
            ]);
    }

    protected function buildSettlementStatus(Collection $documents, Collection $settlements, string $ledgerKey): Collection
    {
        $settlementsByLedger = $settlements
            ->groupBy($ledgerKey)
            ->map(fn (Collection $rows) => $rows
                ->sortBy(fn (array $row) => $row['date'].'|'.$row['id'])
                ->values()
                ->map(fn (array $row) => [
                    ...$row,
                    'remaining' => $this->moneyValue($row['amount']),
                ]));

        return $documents
            ->groupBy($ledgerKey)
            ->flatMap(function (Collection $ledgerDocuments, string $ledgerId) use ($settlementsByLedger) {
                $ledgerSettlements = $settlementsByLedger->get($ledgerId, collect())->values();
                $settlementIndex = 0;

                return $ledgerDocuments
                    ->sortBy(fn (array $row) => $row['date'].'|'.$row['id'])
                    ->values()
                    ->map(function (array $document) use ($ledgerSettlements, &$settlementIndex) {
                        $outstanding = $this->moneyValue($document['open_amount']);
                        $paidOn = null;

                        if ($outstanding <= 0 && $document['total_amount'] > 0) {
                            $paidOn = $document['date'];
                        }

                        while ($outstanding > 0 && $settlementIndex < $ledgerSettlements->count()) {
                            $settlement = $ledgerSettlements[$settlementIndex];
                            $remaining = $this->moneyValue($settlement['remaining'] ?? 0);

                            if ($remaining <= 0) {
                                $settlementIndex++;
                                continue;
                            }

                            $applied = min($outstanding, $remaining);
                            $outstanding = $this->moneyValue($outstanding - $applied);
                            $settlement['remaining'] = $this->moneyValue($remaining - $applied);
                            $ledgerSettlements[$settlementIndex] = $settlement;

                            if ($outstanding <= 0) {
                                $paidOn = max($document['date'], $settlement['date']);
                            }

                            if (($settlement['remaining'] ?? 0) <= 0) {
                                $settlementIndex++;
                            }
                        }

                        return [
                            ...$document,
                            'outstanding_amount' => $this->moneyValue($outstanding),
                            'paid_on' => $paidOn,
                        ];
                    });
            })
            ->values();
    }

    protected function dailySummary(string $branchId, Carbon $date): array
    {
        return [
            'transactions_count' => DB::table('transactions')
                ->where('branch_id', $branchId)
                ->where('status', TransactionStatus::POSTED->value)
                ->whereNull('deleted_at')
                ->whereDate('date', $date->toDateString())
                ->count(),
            'sales_total' => $this->postedReferenceTotal(
                branchId: $branchId,
                referenceType: \App\Models\Sale\Sale::class,
                direction: 'debit',
                accountTypeSlugs: ['cash-or-bank', 'account-receivable'],
                startDate: $date->toDateString(),
                endDate: $date->toDateString(),
            ),
            'purchases_total' => $this->postedReferenceTotal(
                branchId: $branchId,
                referenceType: \App\Models\Purchase\Purchase::class,
                direction: 'credit',
                accountTypeSlugs: ['cash-or-bank', 'account-payable'],
                startDate: $date->toDateString(),
                endDate: $date->toDateString(),
            ),
            'receipts_total' => $this->postedReferenceTotal(
                branchId: $branchId,
                referenceType: \App\Models\Receipt\Receipt::class,
                direction: 'credit',
                accountTypeSlugs: ['account-receivable'],
                startDate: $date->toDateString(),
                endDate: $date->toDateString(),
            ),
            'payments_total' => $this->postedReferenceTotal(
                branchId: $branchId,
                referenceType: \App\Models\Payment\Payment::class,
                direction: 'debit',
                accountTypeSlugs: ['account-payable'],
                startDate: $date->toDateString(),
                endDate: $date->toDateString(),
            ),
        ];
    }

    protected function weeklySummary(string $branchId, Carbon $startDate, Carbon $endDate): array
    {
        $salesTotal = $this->postedReferenceTotal(
            branchId: $branchId,
            referenceType: \App\Models\Sale\Sale::class,
            direction: 'debit',
            accountTypeSlugs: ['cash-or-bank', 'account-receivable'],
            startDate: $startDate->toDateString(),
            endDate: $endDate->toDateString(),
        );

        $purchasesTotal = $this->postedReferenceTotal(
            branchId: $branchId,
            referenceType: \App\Models\Purchase\Purchase::class,
            direction: 'credit',
            accountTypeSlugs: ['cash-or-bank', 'account-payable'],
            startDate: $startDate->toDateString(),
            endDate: $endDate->toDateString(),
        );

        return [
            'sales_total' => $salesTotal,
            'purchases_total' => $purchasesTotal,
            'profit' => $this->moneyValue($salesTotal - $purchasesTotal),
        ];
    }

    protected function postedReferenceTotal(
        string $branchId,
        string $referenceType,
        string $direction,
        array $accountTypeSlugs,
        string $startDate,
        string $endDate,
    ): float {
        $row = DB::table('transactions as t')
            ->join('transaction_lines as tl', function ($join) {
                $join->on('tl.transaction_id', '=', 't.id')
                    ->whereNull('tl.deleted_at');
            })
            ->join('accounts as a', function ($join) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('t.branch_id', $branchId)
            ->where('t.reference_type', $referenceType)
            ->where('t.status', TransactionStatus::POSTED->value)
            ->whereNull('t.deleted_at')
            ->whereBetween('t.date', [$startDate, $endDate])
            ->whereIn('at.slug', $accountTypeSlugs)
            ->where("tl.{$direction}", '>', 0)
            ->selectRaw("COALESCE(SUM(tl.{$direction} * t.rate), 0) as total")
            ->first();

        return $this->moneyValue($row?->total);
    }

    protected function moneyValue(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    protected function quantityValue(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    protected function formatMoney(mixed $value): string
    {
        return number_format($this->moneyValue($value), 2, '.', ',');
    }

    protected function formatQuantity(mixed $value): string
    {
        return number_format($this->quantityValue($value), 2, '.', ',');
    }
}
