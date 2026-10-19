<?php

namespace App\Http\Resources;

use App\Services\DateConversionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class ActivityLogResource extends JsonResource
{
    /**
     * Which record a foreign-key field points at, so the detail page shows
     * "AFN" or "Cash in hand" instead of a ULID.
     */
    protected const LOOKUPS = [
        'created_by' => \App\Models\User::class,
        'updated_by' => \App\Models\User::class,
        'deleted_by' => \App\Models\User::class,
        'posted_by' => \App\Models\User::class,
        'reversed_by' => \App\Models\User::class,
        'approved_by' => \App\Models\User::class,
        'rejected_by' => \App\Models\User::class,
        'cancelled_by' => \App\Models\User::class,
        'voided_by' => \App\Models\User::class,
        'user_id' => \App\Models\User::class,
        'branch_id' => \App\Models\Administration\Branch::class,
        'customer_id' => \App\Models\Ledger\Ledger::class,
        'supplier_id' => \App\Models\Ledger\Ledger::class,
        'ledger_id' => \App\Models\Ledger\Ledger::class,
        'counterpart_ledger_id' => \App\Models\Ledger\Ledger::class,
        'currency_id' => \App\Models\Administration\Currency::class,
        'base_currency' => \App\Models\Administration\Currency::class,
        'base_currency_id' => \App\Models\Administration\Currency::class,
        'home_currency_id' => \App\Models\Administration\Currency::class,
        'account_id' => \App\Models\Account\Account::class,
        'account_type_id' => \App\Models\Account\AccountType::class,
        'warehouse_id' => \App\Models\Administration\Warehouse::class,
        'from_warehouse_id' => \App\Models\Administration\Warehouse::class,
        'to_warehouse_id' => \App\Models\Administration\Warehouse::class,
        'item_id' => \App\Models\Inventory\Item::class,
        'variant_id' => \App\Models\Inventory\ItemVariant::class,
        'item_variant_id' => \App\Models\Inventory\ItemVariant::class,
        'unit_measure_id' => \App\Models\Administration\UnitMeasure::class,
        'measure_id' => \App\Models\Administration\UnitMeasure::class,
        'quantity_id' => \App\Models\Administration\Quantity::class,
        'category_id' => \App\Models\Administration\Category::class,
        'brand_id' => \App\Models\Administration\Brand::class,
        'size_id' => \App\Models\Administration\Size::class,
        'journal_class_id' => \App\Models\JournalEntry\JournalClass::class,
        'employee_id' => \App\Models\Hr\Employee::class,
        'manager_id' => \App\Models\Hr\Employee::class,
        'department_id' => \App\Models\Administration\Department::class,
        'designation_id' => \App\Models\Administration\Designation::class,
        'owner_id' => \App\Models\Owner\Owner::class,
        'payment_term_id' => \App\Models\Administration\PaymentTerm::class,
        'customer_group_id' => \App\Models\Administration\CustomerGroup::class,
        'transaction_id' => \App\Models\Transaction\Transaction::class,
        'sale_id' => \App\Models\Sale\Sale::class,
        'purchase_id' => \App\Models\Purchase\Purchase::class,
        'sale_order_id' => \App\Models\Sale\SaleOrder::class,
        'purchase_order_id' => \App\Models\Purchase\PurchaseOrder::class,
    ];

    /** Plumbing that means nothing to a reader. */
    protected const HIDDEN_KEYS = [
        'id', 'posting_payload', 'remember_token', 'source', 'model', 'related',
        'two_factor_secret', 'two_factor_recovery_codes', 'password',
    ];

    /** Enum translation groups to try for a field's value, e.g. status=posted. */
    protected const ENUM_GROUPS = [
        'status' => ['transaction_status', 'stock_status', 'sale_order_status', 'purchase_order_status', 'leave_request_status', 'payroll_status', 'loan_status'],
        'type' => ['sales_purchase_type', 'ledger_type'],
        'sale_type' => ['sales_purchase_type'],
        'purchase_type' => ['sales_purchase_type'],
        'discount_type' => ['discount_type'],
        'item_type' => ['item_type'],
        'payment_mode' => ['payment_mode'],
        'payment_status' => ['payment_status'],
        'costing_method' => ['costing_method'],
        'calendar_type' => ['calendar_type'],
        'transaction_type' => ['transaction_type'],
        'reason' => ['stock_adjustment_reason', 'sale_return_reason', 'purchase_return_reason'],
        'locale' => ['locale'],
        'gender' => ['gender'],
        'allocation_method' => ['landed_cost_allocation_method'],
    ];

    /** Resolved names for the ids above, keyed by class then id. */
    protected array $names = [];

    public function toArray(Request $request): array
    {
        $isDetail = $request->routeIs('activity-logs.show');
        $related = is_array($this->metadata['related'] ?? null) ? $this->metadata['related'] : [];

        if ($isDetail) {
            $this->resolveNames($related);
        }

        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'module' => $this->module,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'description' => $this->description,
            'display_old_values' => $isDetail ? $this->formatEntries($this->old_values, $this->reference_type) : null,
            'display_new_values' => $isDetail ? $this->formatEntries($this->new_values, $this->reference_type) : null,
            'display_metadata' => $isDetail ? $this->formatEntries($this->metadata, $this->reference_type) : null,
            // Everything else the same action saved: the transfer's transaction,
            // an item's variants and opening stock, a sale's lines.
            'related' => $isDetail ? array_map(fn (array $entry) => $this->formatRelated($entry), $related) : null,
            'related_count' => count($related),
            // What the record is called (#S-0012, a name, a code), so the UI can
            // write the description in the reader's language.
            'subject' => $this->subjectFrom([$this->new_values, $this->old_values], $this->description),
            'created_at' => $this->created_at?->toISOString(),
            // Date in the company's calendar (Jalali or Gregorian) plus time.
            'created_at_display' => $this->displayDateTime($this->created_at),
            'user' => [
                'id' => $this->user_id,
                'name' => $this->whenLoaded('user', fn () => $this->user?->name),
            ],
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            ],
            'request' => [
                'ip_address' => $this->ip_address,
                'user_agent' => $this->user_agent,
            ],
        ];
    }

    protected function formatRelated(array $entry): array
    {
        $old = is_array($entry['old_values'] ?? null) ? $entry['old_values'] : [];
        $new = is_array($entry['new_values'] ?? null) ? $entry['new_values'] : [];
        $type = $entry['reference_type'] ?? null;

        // An edit reads best as "before → after"; a create or delete as its values.
        $fields = [];
        foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $key) {
            if (in_array($key, self::HIDDEN_KEYS, true)) {
                continue;
            }

            $fields[] = [
                'key' => (string) $key,
                'label' => $this->humanizeKey((string) $key),
                'value' => $this->humanizeValue((string) $key, $new[$key] ?? ($new === [] ? ($old[$key] ?? null) : null), $type),
                'old' => $new !== [] && $old !== [] && array_key_exists($key, $old)
                    ? $this->humanizeValue((string) $key, $old[$key], $type)
                    : null,
            ];
        }

        return [
            'event_type' => $entry['event_type'] ?? null,
            'module' => $entry['module'] ?? null,
            'subject' => $this->subjectFrom([$new, $old], $entry['description'] ?? null),
            'fields' => $fields,
        ];
    }

    /**
     * Look up the names behind every id in the log and its related records,
     * one query per model.
     */
    protected function resolveNames(array $related): void
    {
        $ids = [];

        // Recursive: edited child data (an opening's transaction lines, a
        // sale's items) is stored nested and carries its own ids.
        $collect = function (?array $values, ?string $referenceType) use (&$ids, &$collect) {
            foreach ($values ?? [] as $key => $value) {
                if (is_array($value)) {
                    $collect($value, $referenceType);
                    continue;
                }

                $class = is_string($key) ? $this->lookupClass($key, $referenceType) : null;
                if ($class && is_scalar($value) && $value !== '') {
                    $ids[$class][] = (string) $value;
                }
            }
        };

        $collect($this->old_values, $this->reference_type);
        $collect($this->new_values, $this->reference_type);
        $collect($this->metadata, $this->reference_type);
        foreach ($related as $entry) {
            $collect($entry['old_values'] ?? null, $entry['reference_type'] ?? null);
            $collect($entry['new_values'] ?? null, $entry['reference_type'] ?? null);
        }

        foreach ($ids as $class => $classIds) {
            try {
                // Without global scopes: the record may since have been deleted
                // or belong to a scope the viewer is not in; the id is already
                // in this branch's log, so showing its name leaks nothing.
                $this->names[$class] = $class::withoutGlobalScopes()
                    ->whereIn((new $class)->getKeyName(), array_values(array_unique($classIds)))
                    ->get()
                    ->mapWithKeys(fn (Model $model) => [(string) $model->getKey() => $this->nameOf($model)])
                    ->all();
            } catch (\Throwable) {
                $this->names[$class] = [];
            }
        }
    }

    protected function lookupClass(string $key, ?string $referenceType): ?string
    {
        // parent_id and reversal_of point at a record of the same kind.
        if (in_array($key, ['parent_id', 'reversal_of', 'reversal_of_id', 'original_id'], true)) {
            return $this->classFor($referenceType);
        }

        if (isset(self::LOOKUPS[$key])) {
            return self::LOOKUPS[$key];
        }

        // bank_account_id, capital_account_id, expense_account_id, ...
        if (Str::endsWith($key, '_account_id')) {
            return \App\Models\Account\Account::class;
        }

        return null;
    }

    protected function classFor(?string $referenceType): ?string
    {
        if (! $referenceType) {
            return null;
        }

        $class = Relation::getMorphedModel($referenceType) ?? $referenceType;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    protected function nameOf(Model $model): string
    {
        foreach (['name', 'full_name', 'number', 'voucher_number', 'code', 'title', 'email'] as $attribute) {
            $value = $model->getAttribute($attribute);
            if ($value !== null && $value !== '') {
                return $attribute === 'number' ? '#' . $value : (string) $value;
            }
        }

        return (string) $model->getKey();
    }

    protected function formatEntries(mixed $payload, ?string $referenceType): array
    {
        if (! is_array($payload) || $payload === []) {
            return [];
        }

        $entries = [];

        foreach ($payload as $key => $value) {
            if (in_array($key, self::HIDDEN_KEYS, true)) {
                continue;
            }

            $entries[] = [
                'key' => (string) $key,
                'label' => $this->humanizeKey((string) $key),
                'value' => $this->humanizeValue((string) $key, $value, $referenceType),
            ];
        }

        return $entries;
    }

    /**
     * The record's identifier as a person would say it, from the logged values
     * or, for older rows, the "#…" in the stored description.
     */
    protected function subjectFrom(array $valueSets, ?string $description): ?string
    {
        foreach ($valueSets as $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach (['number', 'reference', 'application_number', 'contract_number'] as $key) {
                if (isset($values[$key]) && is_scalar($values[$key]) && $values[$key] !== '') {
                    return '#' . ltrim((string) $values[$key], '#');
                }
            }

            foreach (['full_name', 'name', 'title', 'voucher_number', 'code', 'sku'] as $key) {
                if (isset($values[$key]) && is_scalar($values[$key]) && $values[$key] !== '') {
                    return (string) $values[$key];
                }
            }
        }

        if (is_string($description) && preg_match('/#([^\s,]+?)(?=[\s,.]|$)/u', $description, $match)) {
            return '#' . $match[1];
        }

        return null;
    }

    protected function displayDateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            $moment = Carbon::parse($value)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return is_string($value) ? $value : null;
        }

        $date = app(DateConversionService::class)->toDisplay($moment->toDateString()) ?? $moment->toDateString();

        // A bare date (or a date stored at midnight) has no meaningful time.
        return $moment->format('H:i:s') === '00:00:00'
            ? $date
            : $date . ' ' . $moment->format('H:i');
    }

    protected function humanizeKey(string $key): string
    {
        // Labels come from the language files so the detail page reads in
        // the user's language; the stored key is only a last resort.
        foreach (["messages.activity.fields.{$key}", "validation.attributes.{$key}"] as $translationKey) {
            if (Lang::has($translationKey)) {
                return __($translationKey);
            }
        }

        if (Str::endsWith($key, '_id')) {
            $base = Str::replaceLast('_id', '', $key);

            foreach (["messages.activity.fields.{$base}", "validation.attributes.{$base}"] as $translationKey) {
                if (Lang::has($translationKey)) {
                    return __($translationKey);
                }
            }
        }

        return Str::headline(Str::replaceLast('_id', '', $key));
    }

    /**
     * Nested child data as readable text: one line per field, one block per
     * row (a line of a sale, a debit/credit line), indented by depth. The
     * page renders it with whitespace preserved.
     */
    protected function humanizeStructure(string $key, array $value, ?string $referenceType, int $depth): string
    {
        if ($value === []) {
            return '—';
        }

        $indent = str_repeat('    ', $depth);

        if (array_is_list($value)) {
            // Scalars stay on one line; rows of fields become blocks.
            if (! collect($value)->contains(fn ($item) => is_array($item))) {
                return collect($value)->map(fn ($item) => $this->humanizeValue($key, $item, $referenceType))->join(', ');
            }

            return collect($value)
                ->map(fn ($item) => $indent . '• ' . ltrim(
                    is_array($item)
                        ? $this->humanizeStructure($key, $item, $referenceType, $depth + 1)
                        : (string) $this->humanizeValue($key, $item, $referenceType)
                ))
                ->join("\n");
        }

        $lines = [];
        foreach ($value as $itemKey => $item) {
            if (in_array($itemKey, self::HIDDEN_KEYS, true) || $item === null || $item === '' || $item === []) {
                continue;
            }

            $label = $this->humanizeKey((string) $itemKey);
            $lines[] = is_array($item)
                ? $indent . $label . ":\n" . $this->humanizeStructure((string) $itemKey, $item, $referenceType, $depth + 1)
                : $indent . $label . ': ' . $this->humanizeValue((string) $itemKey, $item, $referenceType);
        }

        return $lines === [] ? '—' : implode("\n", $lines);
    }

    protected function humanizeValue(string $key, mixed $value, ?string $referenceType = null): mixed
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? __('messages.activity.yes') : __('messages.activity.no');
        }

        if (is_array($value)) {
            return $this->humanizeStructure($key, $value, $referenceType, 0);
        }

        $class = $this->lookupClass($key, $referenceType);
        if ($class && isset($this->names[$class][(string) $value])) {
            return $this->names[$class][(string) $value];
        }

        if (is_string($value) && isset(self::ENUM_GROUPS[$key])) {
            foreach (self::ENUM_GROUPS[$key] as $group) {
                if (Lang::has("enums.{$group}.{$value}")) {
                    return __("enums.{$group}.{$value}");
                }
            }
        }

        if (is_string($value) && ($key === 'date' || Str::endsWith($key, ['_at', '_date'])) && strtotime($value) !== false) {
            return $this->displayDateTime($value) ?? $value;
        }

        return $value;
    }
}
