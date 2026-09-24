<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

/**
 * Turning a record off without deleting it.
 *
 * Categories, unit measures, warehouses, discount rules and users all carry a
 * status that decides whether they still show up in pickers, but nothing in the
 * UI ever flipped it — a warehouse you stopped using could only be deleted,
 * which the dependency guard refuses once anything references it.
 *
 * Most of them store a plain `is_active` boolean. Users store a `status` enum
 * as well, because they can also be blocked, which is not the same as inactive
 * and is not what this toggle does.
 */
trait TogglesRecordStatus
{
    protected function toggleRecordStatus(Model $model, string $resourceKey): RedirectResponse
    {
        $this->authorize('update', $model);

        // Which of the two columns this model actually has. `users` carries the
        // enum only and everything else carries the boolean only, so writing
        // both unconditionally puts a column that does not exist into the
        // UPDATE. Route-model binding hands us the whole row, so its attribute
        // keys are the table's columns.
        $attributes = $model->getAttributes();
        $hasFlag = array_key_exists('is_active', $attributes);
        $hasStatus = array_key_exists('status', $attributes);

        $active = $this->recordIsActive($model, $hasFlag);

        if ($hasFlag) {
            $model->is_active = ! $active;
        }

        // A blocked user stays blocked: unblocking is a separate decision from
        // reactivating, and this toggle is not the place to make it.
        if ($hasStatus && $this->statusValue($model) !== UserStatus::BLOCKED->value) {
            $model->status = $active ? UserStatus::INACTIVE->value : UserStatus::ACTIVE->value;
        }

        $model->save();

        $message = $active
            ? __('general.deactivated_successfully', ['resource' => __($resourceKey)])
            : __('general.activated_successfully', ['resource' => __($resourceKey)]);

        return redirect()->back()->with('success', $message);
    }

    /**
     * Records predating the `is_active` migrations have it null, which is not
     * the same as "off" — they were in use before the column existed.
     */
    private function recordIsActive(Model $model, bool $hasFlag): bool
    {
        $value = $hasFlag ? $model->getAttribute('is_active') : null;

        if ($value === null) {
            $status = $this->statusValue($model);

            return $status === null || $status === UserStatus::ACTIVE->value;
        }

        return (bool) $value;
    }

    private function statusValue(Model $model): ?string
    {
        $status = $model->getAttribute('status');

        return $status instanceof UserStatus ? $status->value : $status;
    }
}
