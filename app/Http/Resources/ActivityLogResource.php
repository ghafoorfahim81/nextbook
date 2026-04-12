<?php

namespace App\Http\Resources;

use App\Models\Account\Account;
use App\Models\Administration\Branch;
use App\Models\Administration\Currency;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Ledger\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $shouldFormatValues = $request->routeIs('activity-logs.show');
        $displayLookups = $shouldFormatValues
            ? $this->resolveDisplayLookups($this->old_values, $this->new_values, $this->metadata)
            : ['users' => [], 'branches' => []];

        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'module' => $this->module,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'description' => $this->description,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'metadata' => $this->metadata,
            'display_old_values' => $shouldFormatValues
                ? $this->formatDisplayEntries($this->old_values, $displayLookups)
                : null,
            'display_new_values' => $shouldFormatValues
                ? $this->formatDisplayEntries($this->new_values, $displayLookups)
                : null,
            'display_metadata' => $shouldFormatValues
                ? $this->formatDisplayEntries($this->metadata, $displayLookups)
                : null,
            'created_at' => $this->created_at?->toISOString(),
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

    protected function resolveDisplayLookups(?array ...$payloads): array
    {
        $userIds = [];
        $branchIds = [];
        $ledgerIds = [];
        $currencyIds = [];
        $accountIds = [];
        $warehouseIds = [];
        $itemIds = [];

        foreach ($payloads as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            foreach ($payload as $key => $value) {
                if (! is_scalar($value) || $value === '') {
                    continue;
                }

                $normalizedKey = (string) $key;
                $normalizedValue = (string) $value;

                if (in_array($normalizedKey, ['created_by', 'updated_by', 'deleted_by', 'user_id'], true)) {
                    $userIds[] = $normalizedValue;
                }

                if ($normalizedKey === 'branch_id') {
                    $branchIds[] = $normalizedValue;
                }

                if (in_array($normalizedKey, ['customer_id', 'supplier_id', 'ledger_id'], true)) {
                    $ledgerIds[] = $normalizedValue;
                }

                if ($normalizedKey === 'currency_id') {
                    $currencyIds[] = $normalizedValue;
                }

                if (in_array($normalizedKey, ['account_id', 'bank_account_id', 'capital_account_id', 'drawing_account_id', 'from_account_id', 'to_account_id'], true)) {
                    $accountIds[] = $normalizedValue;
                }

                if ($normalizedKey === 'warehouse_id') {
                    $warehouseIds[] = $normalizedValue;
                }

                if ($normalizedKey === 'item_id') {
                    $itemIds[] = $normalizedValue;
                }
            }
        }

        $users = User::query()
            ->whereIn('id', array_values(array_unique($userIds)))
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $branches = Branch::query()
            ->whereIn('id', array_values(array_unique($branchIds)))
            ->get(['id', 'name'])
            ->keyBy('id');

        $ledgers = Ledger::query()
            ->whereIn('id', array_values(array_unique($ledgerIds)))
            ->get(['id', 'name'])
            ->keyBy('id');

        $currencies = Currency::query()
            ->whereIn('id', array_values(array_unique($currencyIds)))
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $accounts = Account::query()
            ->whereIn('id', array_values(array_unique($accountIds)))
            ->get(['id', 'name', 'number'])
            ->keyBy('id');

        $warehouses = Warehouse::query()
            ->whereIn('id', array_values(array_unique($warehouseIds)))
            ->get(['id', 'name'])
            ->keyBy('id');

        $items = Item::query()
            ->whereIn('id', array_values(array_unique($itemIds)))
            ->get(['id', 'name', 'code'])
            ->keyBy('id');

        return [
            'users' => $users,
            'branches' => $branches,
            'ledgers' => $ledgers,
            'currencies' => $currencies,
            'accounts' => $accounts,
            'warehouses' => $warehouses,
            'items' => $items,
        ];
    }

    protected function formatDisplayEntries(mixed $payload, array $lookups): array
    {
        if (! is_array($payload) || $payload === []) {
            return [];
        }

        $entries = [];

        foreach ($payload as $key => $value) {
            $entries[] = [
                'key' => (string) $key,
                'label' => $this->humanizeKey((string) $key),
                'value' => $this->humanizeValue((string) $key, $value, $lookups),
            ];
        }

        return $entries;
    }

    protected function humanizeKey(string $key): string
    {
        return match ($key) {
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'deleted_by' => 'Deleted By',
            'branch_id' => 'Branch',
            default => Str::headline(Str::replaceLast('_id', '', $key)),
        };
    }

    protected function humanizeValue(string $key, mixed $value, array $lookups): mixed
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            if ($value === []) {
                return '—';
            }

            if ($this->isListArray($value)) {
                return collect($value)
                    ->map(fn ($item) => $this->humanizeValue($key, $item, $lookups))
                    ->join(', ');
            }

            return collect($value)
                ->map(fn ($item, $itemKey) => $this->humanizeKey((string) $itemKey) . ': ' . $this->humanizeValue((string) $itemKey, $item, $lookups))
                ->join(', ');
        }

        if (in_array($key, ['created_by', 'updated_by', 'deleted_by', 'user_id'], true) && isset($lookups['users'][$value])) {
            $user = $lookups['users'][$value];

            return $user->name ?: $user->email ?: $value;
        }

        if ($key === 'branch_id' && isset($lookups['branches'][$value])) {
            return $lookups['branches'][$value]->name ?: $value;
        }

        if (in_array($key, ['customer_id', 'supplier_id', 'ledger_id'], true) && isset($lookups['ledgers'][$value])) {
            return $lookups['ledgers'][$value]->name ?: $value;
        }

        if ($key === 'currency_id' && isset($lookups['currencies'][$value])) {
            return $lookups['currencies'][$value]->code ?: $lookups['currencies'][$value]->name ?: $value;
        }

        if (in_array($key, ['account_id', 'bank_account_id', 'capital_account_id', 'drawing_account_id', 'from_account_id', 'to_account_id'], true) && isset($lookups['accounts'][$value])) {
            $account = $lookups['accounts'][$value];

            return $account->name ?: $account->number ?: $value;
        }

        if ($key === 'warehouse_id' && isset($lookups['warehouses'][$value])) {
            return $lookups['warehouses'][$value]->name ?: $value;
        }

        if ($key === 'item_id' && isset($lookups['items'][$value])) {
            return $lookups['items'][$value]->name ?: $lookups['items'][$value]->code ?: $value;
        }

        if (is_string($value) && $this->looksLikeDateTime($value) && Str::endsWith($key, ['_at', '_date'])) {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        }

        return $value;
    }

    protected function looksLikeDateTime(string $value): bool
    {
        return strtotime($value) !== false;
    }

    protected function isListArray(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
