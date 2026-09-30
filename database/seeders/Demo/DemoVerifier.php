<?php

namespace Database\Seeders\Demo;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Post-run checks on the books the application produced.
 *
 * Every figure comes straight from the database, so the report says what the
 * system recorded, not what the simulator meant to do.
 */
final class DemoVerifier
{
    /** @var array<int, array{0: string, 1: bool, 2: string}> */
    public array $checks = [];

    public function __construct(private Command $console, private string $branchId)
    {
    }

    public function run(): void
    {
        $this->counts();
        $this->trialBalance();
        $this->stock();
        $this->ledgers();
        $this->settlements();
        $this->mix();

        $this->console->newLine();
        $this->console->info('نتیجهٔ بررسی‌ها');
        $this->console->table(['بررسی', 'نتیجه', 'جزئیات'], array_map(
            fn ($c) => [$c[0], $c[1] ? '✔ درست' : '✘ نادرست', $c[2]],
            $this->checks
        ));
    }

    private function check(string $name, bool $ok, string $detail): void
    {
        $this->checks[] = [$name, $ok, $detail];
    }

    private function counts(): void
    {
        $tables = [
            'users' => 'کاربران', 'accounts' => 'حساب‌ها', 'currency_rate_updates' => 'نرخ ارز',
            'items' => 'اجناس', 'brands' => 'برندها', 'categories' => 'کتگوری اجناس', 'discount_rules' => 'قوانین تخفیف',
            'expense_categories' => 'کتگوری مصارف', 'ledger_openings' => 'بیلانس افتتاحیه',
            'purchase_quotations' => 'پیش‌فاکتور خرید', 'purchase_orders' => 'سفارش خرید', 'purchases' => 'فاکتور خرید',
            'purchase_returns' => 'برگشت خرید', 'sale_quotations' => 'پیش‌فاکتور فروش', 'sale_orders' => 'سفارش فروش',
            'sales' => 'فاکتور فروش', 'sale_returns' => 'برگشت فروش', 'receipts' => 'رسید', 'payments' => 'پرداخت',
            'expenses' => 'مصارف', 'account_transfers' => 'انتقال بین حساب‌ها', 'journal_entries' => 'ژورنال دستی',
            'stock_adjustments' => 'تعدیل موجودی', 'landed_costs' => 'هزینهٔ جانبی خرید',
            'transactions' => 'تراکنش‌ها', 'transaction_lines' => 'سطرهای تراکنش', 'stock_movements' => 'حرکات موجودی',
            'settlements' => 'اختصاص رسید/پرداخت', 'activity_logs' => 'لاگ فعالیت',
        ];

        $rows = [];
        foreach ($tables as $table => $label) {
            $query = DB::table($table);
            $hasStatus = DB::getSchemaBuilder()->hasColumn($table, 'status');
            $total = (clone $query)->count();
            $reversed = $hasStatus ? (clone $query)->where('status', 'reversed')->count() : null;
            $rows[] = [$label, $table, number_format($total), $reversed === null ? '—' : number_format($reversed)];
        }

        $customers = DB::table('ledgers')->where('type', 'customer')->count();
        $suppliers = DB::table('ledgers')->where('type', 'supplier')->count();
        $rows[] = ['مشتریان', 'ledgers', number_format($customers), '—'];
        $rows[] = ['تأمین‌کنندگان', 'ledgers', number_format($suppliers), '—'];

        $this->console->newLine();
        $this->console->info('تعداد ریکاردها');
        $this->console->table(['بخش', 'جدول', 'تعداد', 'باطل‌شده'], $rows);
    }

    private function trialBalance(): void
    {
        $totals = DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->whereNull('tl.deleted_at')->whereNull('t.deleted_at')
            ->whereIn('t.status', ['posted', 'reversed'])
            ->selectRaw('COALESCE(SUM(tl.base_debit),0) as d, COALESCE(SUM(tl.base_credit),0) as c')
            ->first();

        $diff = round((float) $totals->d - (float) $totals->c, 2);
        $this->check('تراز آزمایشی (به افغانی)', abs($diff) < 0.01,
            sprintf('بدهکار %s | بستانکار %s | تفاوت %s', number_format((float) $totals->d, 2), number_format((float) $totals->c, 2), $diff));

        $unbalanced = DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->whereNull('tl.deleted_at')->whereNull('t.deleted_at')
            ->whereIn('t.status', ['posted', 'reversed'])
            ->groupBy('t.id')
            ->havingRaw('ABS(SUM(tl.base_debit) - SUM(tl.base_credit)) > 0.01')
            ->select('t.id')->get()->count();
        $this->check('هر تراکنش به‌تنهایی متوازن', $unbalanced === 0, "{$unbalanced} تراکنش نامتوازن");

        $negativeCash = DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->join('accounts as a', 'a.id', '=', 'tl.account_id')
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('at.slug', 'cash-or-bank')
            ->whereNull('tl.deleted_at')->whereNull('t.deleted_at')->whereIn('t.status', ['posted', 'reversed'])
            ->groupBy('a.id', 'a.name')
            ->selectRaw('a.name, SUM(tl.base_debit - tl.base_credit) as balance')
            ->get()
            ->filter(fn ($r) => (float) $r->balance < -0.01);
        $this->check('هیچ صندوق/بانک منفی نیست', $negativeCash->isEmpty(),
            $negativeCash->isEmpty() ? 'همه مثبت' : $negativeCash->map(fn ($r) => "{$r->name}: " . number_format((float) $r->balance))->implode('، '));
    }

    private function stock(): void
    {
        // Movement quantity is in the unit it was entered in; bring it to the item's unit.
        $movements = DB::select("
            SELECT m.item_id, m.warehouse_id,
                   SUM(CASE WHEN m.movement_type = 'in' THEN 1 ELSE -1 END * m.quantity * mu.unit::numeric / NULLIF(iu.unit::numeric, 0)) AS qty
            FROM stock_movements m
            JOIN items i ON i.id = m.item_id
            JOIN unit_measures mu ON mu.id = m.unit_measure_id
            JOIN unit_measures iu ON iu.id = i.unit_measure_id
            WHERE m.deleted_at IS NULL AND m.status IN ('posted', 'voided') AND m.branch_id = ?
            GROUP BY m.item_id, m.warehouse_id
        ", [$this->branchId]);

        $balances = DB::table('stock_balances')
            ->whereNull('deleted_at')->where('branch_id', $this->branchId)
            ->groupBy('item_id', 'warehouse_id')
            ->selectRaw('item_id, warehouse_id, SUM(quantity) as qty')
            ->get()
            ->keyBy(fn ($r) => $r->item_id . '|' . $r->warehouse_id);

        // Movements are stored at 4 decimals in the unit they were entered in, so
        // a piece taken from a 24-carton layer is 0.0417 carton; converted back,
        // sums can differ from the balance by a few thousandths of a piece.
        $tolerance = 0.01;
        $mismatch = 0;
        $maxDrift = 0.0;
        $examples = [];
        $negative = 0;
        foreach ($movements as $row) {
            $balance = (float) ($balances[$row->item_id . '|' . $row->warehouse_id]->qty ?? 0);
            $maxDrift = max($maxDrift, abs($balance - (float) $row->qty));
            if (abs($balance - (float) $row->qty) > $tolerance) {
                $mismatch++;
                if (count($examples) < 3) {
                    $examples[] = sprintf('%s: دفتر %.3f / بیلانس %.3f', substr($row->item_id, -6), $row->qty, $balance);
                }
            }
            if ((float) $row->qty < -0.0001) {
                $negative++;
            }
        }

        $this->check('موجودی = دفتر موجودی (هر جنس/گدام)', $mismatch === 0,
            count($movements) . " ترکیب جنس/گدام، {$mismatch} مغایرت، بیشترین اختلاف گرد کردن " . round($maxDrift, 4)
            . ($examples ? ' — ' . implode('؛ ', $examples) : ''));

        $negativeBalances = DB::table('stock_balances')->whereNull('deleted_at')->where('quantity', '<', -0.0001)->count();
        $this->check('هیچ موجودی منفی نیست (نهایی)', $negative === 0 && $negativeBalances === 0,
            "دفتر: {$negative} منفی، جدول بیلانس: {$negativeBalances} منفی");

        // Running balance through time, in date order, never below zero.
        $dips = DB::selectOne("
            SELECT COUNT(DISTINCT item_id) AS n FROM (
                SELECT m.item_id,
                       SUM(CASE WHEN m.movement_type = 'in' THEN 1 ELSE -1 END * m.quantity * mu.unit::numeric / NULLIF(iu.unit::numeric, 0))
                           OVER (PARTITION BY m.item_id, m.warehouse_id ORDER BY m.created_at, m.movement_type ASC, m.id) AS running
                FROM stock_movements m
                JOIN items i ON i.id = m.item_id
                JOIN unit_measures mu ON mu.id = m.unit_measure_id
                JOIN unit_measures iu ON iu.id = i.unit_measure_id
                WHERE m.deleted_at IS NULL AND m.status IN ('posted', 'voided') AND m.branch_id = ?
            ) x WHERE running < -0.0001
        ", [$this->branchId]);
        $this->check('موجودی در طول زمان هرگز منفی نشده', (int) $dips->n === 0, "{$dips->n} جنس با موجودی منفی در یک لحظه");
    }

    private function ledgers(): void
    {
        foreach (['customer' => ['account-receivable', 'customer-advances', 'مشتریان'], 'supplier' => ['account-payable', 'supplier-advances', 'تأمین‌کنندگان']] as $type => [$control, $advance, $label]) {
            $subledger = DB::table('transaction_lines as tl')
                ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
                ->join('ledgers as l', 'l.id', '=', 'tl.ledger_id')
                ->where('l.type', $type)
                ->whereNull('tl.deleted_at')->whereNull('t.deleted_at')->whereIn('t.status', ['posted', 'reversed'])
                ->selectRaw('COALESCE(SUM(tl.base_debit - tl.base_credit), 0) as b')
                ->value('b');

            $gl = DB::table('transaction_lines as tl')
                ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
                ->join('accounts as a', 'a.id', '=', 'tl.account_id')
                ->whereIn('a.slug', [$control, $advance])
                ->where('a.branch_id', $this->branchId)
                ->whereNull('tl.deleted_at')->whereNull('t.deleted_at')->whereIn('t.status', ['posted', 'reversed'])
                ->selectRaw('COALESCE(SUM(tl.base_debit - tl.base_credit), 0) as b')
                ->value('b');

            $diff = round((float) $subledger - (float) $gl, 2);
            $this->check("بیلانس {$label} = حساب کنترل در ژورنال", abs($diff) < 0.01,
                sprintf('مجموع حساب‌های %s %s | حساب کل %s | تفاوت %s', $label, number_format((float) $subledger, 2), number_format((float) $gl, 2), $diff));
        }

        // Each ledger's statement (the figure the UI shows) against its posted journal lines.
        $mismatched = DB::selectOne("
            SELECT COUNT(*) AS n FROM (
                SELECT l.id,
                       COALESCE(SUM(tl.base_debit - tl.base_credit), 0) AS statement,
                       COALESCE(SUM(CASE WHEN t.status IN ('posted','reversed') THEN tl.base_debit - tl.base_credit ELSE 0 END), 0) AS posted
                FROM ledgers l
                LEFT JOIN transaction_lines tl ON tl.ledger_id = l.id AND tl.deleted_at IS NULL
                LEFT JOIN transactions t ON t.id = tl.transaction_id AND t.deleted_at IS NULL AND t.branch_id = l.branch_id
                WHERE l.deleted_at IS NULL
                GROUP BY l.id
            ) x WHERE ABS(statement - posted) > 0.01
        ");
        $this->check('صورت‌حساب هر طرف حساب = ژورنال‌های ثبت‌شده', (int) $mismatched->n === 0, "{$mismatched->n} طرف حساب مغایر");
    }

    private function settlements(): void
    {
        $over = DB::selectOne("
            SELECT COUNT(*) AS n FROM (
                SELECT s.target_line_id, SUM(s.amount_applied) AS applied, MAX(GREATEST(tl.debit, tl.credit)) AS claim
                FROM settlements s JOIN transaction_lines tl ON tl.id = s.target_line_id
                WHERE s.deleted_at IS NULL
                GROUP BY s.target_line_id
            ) x WHERE applied > claim + 0.001
        ");
        $this->check('اختصاص هیچ بیلی بیشتر از مبلغ آن نیست', (int) $over->n === 0, "{$over->n} بیل بیش از مبلغ");

        foreach (['sales' => 'فروش', 'purchases' => 'خرید'] as $table => $label) {
            $credit = DB::table($table)->whereIn('type', ['on_loan', 'credit'])->where('status', 'posted')->whereNull('deleted_at');
            $all = (clone $credit)->count();
            $open = (clone $credit)->where('payment_status', '!=', 'paid')->count();
            $this->check("فاکتورهای نسیهٔ {$label} باز تا امروز", true,
                sprintf('%d از %d (%.1f٪)', $open, $all, $all ? 100 * $open / $all : 0));
        }
    }

    private function mix(): void
    {
        $currency = DB::table('transactions as t')
            ->join('currencies as c', 'c.id', '=', 't.currency_id')
            ->whereIn('t.reference_type', ['App\\Models\\Sale\\Sale', 'App\\Models\\Purchase\\Purchase', 'App\\Models\\Receipt\\Receipt',
                'App\\Models\\Payment\\Payment', 'App\\Models\\Expense\\Expense', 'App\\Models\\JournalEntry\\JournalEntry'])
            ->groupBy('c.code')->selectRaw('c.code, COUNT(*) as n')->pluck('n', 'code');
        $sum = max(1, $currency->sum());
        $this->check('توزیع ارز اسناد', true, $currency->map(fn ($n, $code) => sprintf('%s %.1f٪', $code, 100 * $n / $sum))->implode('، '));

        $sales = DB::table('sales')->where('status', 'posted')->whereNull('deleted_at');
        $discounted = (clone $sales)->whereExists(fn ($q) => $q->from('sale_items')->whereColumn('sale_items.sale_id', 'sales.id')->where('sale_items.discount', '>', 0))->count();
        $this->check('فاکتورهای فروش با قانون تخفیف', true, sprintf('%d از %d (%.1f٪)', $discounted, (clone $sales)->count(), 100 * $discounted / max(1, (clone $sales)->count())));

        $byCustomer = DB::table('sales as s')->join('ledgers as l', 'l.id', '=', 's.customer_id')
            ->where('l.code', '!=', 'CASH-CUST')->where('s.status', 'posted')
            ->groupBy('s.customer_id')->selectRaw('COUNT(*) as n')->pluck('n')->sortDesc()->values();
        $customers = DB::table('ledgers')->where('type', 'customer')->where('code', '!=', 'CASH-CUST')->count();
        $top = (int) ceil($customers * 0.2);
        $share = $byCustomer->sum() ? 100 * $byCustomer->take($top)->sum() / $byCustomer->sum() : 0;
        $this->check('سهم ۲۰٪ مشتریان برتر از فروش', true, sprintf('%.1f٪ فاکتورهای مشتریان ثبت‌شده', $share));

        $walkIn = DB::table('sales as s')->join('ledgers as l', 'l.id', '=', 's.customer_id')->where('l.code', 'CASH-CUST')->count();
        $this->check('فروش به مشتری عمومی/نقدی', true, sprintf('%.1f٪ فاکتورها', 100 * $walkIn / max(1, DB::table('sales')->count())));
    }
}
