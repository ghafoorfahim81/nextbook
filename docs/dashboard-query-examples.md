# Dashboard Query Examples

These are representative PostgreSQL queries for the dashboard metrics. The application implementation lives in `app/Services/DashboardService.php` and applies the same filters with Laravel's query builder.

## Cash / Bank Balance

```sql
SELECT COALESCE(SUM((tl.debit - tl.credit) * t.rate), 0) AS cash_bank_balance
FROM transactions t
JOIN transaction_lines tl ON tl.transaction_id = t.id AND tl.deleted_at IS NULL
JOIN accounts a ON a.id = tl.account_id AND a.deleted_at IS NULL
JOIN account_types at ON at.id = a.account_type_id
WHERE t.branch_id = :branch_id
  AND t.status = 'posted'
  AND t.deleted_at IS NULL
  AND at.slug = 'cash-or-bank';
```

## Sales vs Purchases for Last 30 Days

```sql
SELECT DATE(t.date) AS report_date,
       COALESCE(SUM(tl.debit * t.rate), 0) AS total_sales
FROM transactions t
JOIN transaction_lines tl ON tl.transaction_id = t.id AND tl.deleted_at IS NULL
JOIN accounts a ON a.id = tl.account_id AND a.deleted_at IS NULL
JOIN account_types at ON at.id = a.account_type_id
WHERE t.branch_id = :branch_id
  AND t.reference_type = 'App\\Models\\Sale\\Sale'
  AND t.status = 'posted'
  AND t.deleted_at IS NULL
  AND at.slug IN ('cash-or-bank', 'account-receivable')
  AND tl.debit > 0
  AND t.date BETWEEN :from_date AND :to_date
GROUP BY DATE(t.date)
ORDER BY DATE(t.date);
```

## Ledger Receivable / Payable Balances

```sql
SELECT l.id,
       l.name,
       COALESCE(SUM((COALESCE(tl.debit, 0) - COALESCE(tl.credit, 0)) * t.rate), 0) AS balance
FROM transaction_lines tl
JOIN transactions t ON t.id = tl.transaction_id
JOIN ledgers l ON l.id = tl.ledger_id
WHERE t.branch_id = :branch_id
  AND t.status = 'posted'
  AND t.deleted_at IS NULL
  AND tl.deleted_at IS NULL
  AND l.branch_id = :branch_id
  AND l.type = 'customer'
  AND l.deleted_at IS NULL
GROUP BY l.id, l.name
HAVING COALESCE(SUM((COALESCE(tl.debit, 0) - COALESCE(tl.credit, 0)) * t.rate), 0) > 0
ORDER BY balance DESC
LIMIT 10;
```

## Inventory Overview

```sql
SELECT COALESCE(SUM(quantity), 0) AS total_quantity,
       COALESCE(SUM(quantity * COALESCE(average_cost, 0)), 0) AS total_value
FROM stock_balances
WHERE branch_id = :branch_id
  AND deleted_at IS NULL
  AND status NOT IN ('voided', 'cancelled');
```

## Expiring Batches in Next 30 Days

```sql
SELECT sb.id,
       i.name AS item_name,
       w.name AS warehouse_name,
       sb.batch,
       sb.expire_date,
       sb.quantity
FROM stock_balances sb
JOIN items i ON i.id = sb.item_id AND i.deleted_at IS NULL
JOIN warehouses w ON w.id = sb.warehouse_id AND w.deleted_at IS NULL
WHERE sb.branch_id = :branch_id
  AND sb.deleted_at IS NULL
  AND sb.status NOT IN ('voided', 'cancelled')
  AND sb.quantity > 0
  AND sb.expire_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 day'
ORDER BY sb.expire_date
LIMIT 10;
```
