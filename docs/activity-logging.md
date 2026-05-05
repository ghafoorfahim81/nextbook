# Activity Logging

This audit layer is for business activity logging only. It is separate from exception logs, queue failures, Laravel logs, or infrastructure telemetry.

## Why this structure fits ERP audit logs

- `module`, `event_type`, `reference_type`, and `reference_id` make logs readable for finance, inventory, and operational workflows.
- `old_values` and `new_values` are JSONB, so PostgreSQL can store structured business deltas without forcing full snapshots on updates.
- `user_id`, `branch_id`, request IP, and user agent add accountability without mixing in technical stack traces.
- The design keeps logging explicit in business flows like posting, approvals, stock changes, and payments instead of hiding audit decisions inside observers.

## Core usage

### Sale store

```php
$activityLogService->logCreate(
    reference: $sale,
    module: 'sale',
    description: "Sale #{$sale->number} created and posted.",
    newValues: [
        'number' => $sale->number,
        'customer_id' => $sale->customer_id,
        'date' => optional($sale->date)->toDateString(),
        'status' => $sale->status,
        'warehouse_id' => $validated['warehouse_id'],
        'currency_id' => $validated['currency_id'],
        'item_count' => count($validated['item_list']),
        'transaction_total' => (float) $validated['transaction_total'],
    ],
    metadata: [
        'action' => 'sale_store',
        'sale_type' => $validated['type'],
        'create_and_new' => (bool) ($request->create_and_new ?? false),
    ],
);
```

### Purchase store

```php
$activityLogService->logCreate(
    reference: $purchase,
    module: 'purchase',
    description: "Purchase #{$purchase->number} created.",
    newValues: [
        'number' => $purchase->number,
        'supplier_id' => $purchase->supplier_id,
        'date' => optional($purchase->date)->toDateString(),
        'status' => $purchase->status,
        'warehouse_id' => $validated['warehouse_id'],
        'currency_id' => $validated['currency_id'],
        'item_count' => count($validated['item_list']),
        'transaction_total' => (float) $validated['transaction_total'],
    ],
    metadata: [
        'action' => 'purchase_store',
        'purchase_type' => $validated['type'],
    ],
);
```

### Journal entry post

```php
$activityLogService->logAction(
    eventType: 'posted',
    reference: $journalEntry,
    module: 'journal_entry',
    description: "Journal entry #{$journalEntry->number} posted.",
    newValues: [
        'number' => $journalEntry->number,
        'date' => $validated['date'],
        'status' => 'posted',
        'currency_id' => $validated['currency_id'],
        'rate' => (float) $validated['rate'],
        'line_count' => count($validated['lines']),
    ],
    metadata: [
        'action' => 'journal_entry_post',
        'transaction_id' => $transaction->id,
    ],
);
```

### Journal entry update

```php
$activityLogService->logUpdate(
    reference: $journalEntry,
    module: 'journal_entry',
    before: $beforeState,
    after: $afterState,
    description: "Journal entry #{$journalEntry->number} updated and reposted.",
    metadata: [
        'action' => 'journal_entry_update',
        'transaction_id' => $transaction->id,
    ],
);
```

### Stock adjustment service example

```php
public function adjust(
    StockBalance $stockBalance,
    float $newQuantity,
    string $reason,
    ActivityLogService $activityLogService,
): void {
    $before = [
        'quantity' => (float) $stockBalance->quantity,
        'average_cost' => (float) $stockBalance->average_cost,
        'warehouse_id' => $stockBalance->warehouse_id,
    ];

    $stockBalance->update([
        'quantity' => $newQuantity,
    ]);

    $after = [
        'quantity' => (float) $stockBalance->quantity,
        'average_cost' => (float) $stockBalance->average_cost,
        'warehouse_id' => $stockBalance->warehouse_id,
    ];

    $activityLogService->logAction(
        eventType: 'adjusted',
        reference: StockBalance::class,
        referenceId: $stockBalance->id,
        module: 'stock',
        description: "Stock adjusted for item {$stockBalance->item_id}.",
        oldValues: $before,
        newValues: $after,
        branchId: $stockBalance->branch_id,
        metadata: [
            'reason' => $reason,
            'item_id' => $stockBalance->item_id,
        ],
    );
}
```

## Helper trait example

For lighter controller or service code, the `App\Traits\LogsActivity` trait wraps the service without changing the architecture. Use it as a convenience layer, not as hidden automatic logging.

## Querying and filtering

### Controller listing example

```php
$logs = ActivityLog::query()
    ->with(['user:id,name', 'branch:id,name'])
    ->betweenDates($request->input('from'), $request->input('to'))
    ->forModule($request->input('module'))
    ->forUser($request->input('user_id'))
    ->forReference($request->input('reference_type'), $request->input('reference_id'))
    ->orderByDesc('created_at')
    ->paginate(25);

return ActivityLogResource::collection($logs);
```

### Example filters

- `from=2026-03-01&to=2026-03-31`
- `module=sale`
- `user_id=01HR...`
- `reference_type=sale&reference_id=01HT...`

## PostgreSQL scale path

### Monthly partitioning

- Convert `activity_logs` into a partitioned parent table by range on `created_at`.
- Create monthly child tables such as `activity_logs_2026_03`.
- Keep the same logical schema and indexes on each partition.

### Archive strategy

- Keep hot data in the main partition set, for example 12 to 24 months.
- Move older partitions to cheaper storage or a dedicated reporting database.
- Export archived partitions to parquet or compressed SQL dumps for long-term retention.

### Queue-based async logging

- Keep synchronous logging for critical financial actions like posting, approval, payment, and deletion.
- Queue non-critical events such as print, export, login history enrichment, and read-only activity feeds.
- Use a lightweight job payload so the queue stores already-normalized audit data rather than whole models.
