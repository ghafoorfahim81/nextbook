<?php

namespace App\Traits;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    /**
     * Captures each record's deleted_at right before it is restored so that
     * only attachments removed by the cascade (not ones the user deleted
     * individually earlier) are brought back.
     *
     * @var array<string, mixed>
     */
    protected static array $attachmentRestoreThresholds = [];

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Attachments query that ignores the branch global scope, so cascade
     * operations work in any context (web request, console, scheduled job).
     */
    public function attachmentsUnscoped(): MorphMany
    {
        return $this->attachments()->withoutGlobalScope(BranchSpecific::class);
    }

    public static function bootHasAttachments(): void
    {
        // Soft delete the parent -> soft delete its attachments.
        static::deleting(function (Model $model) {
            // Force deletes are handled by the forceDeleted hook below.
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            $model->attachmentsUnscoped()->get()->each->delete();
        });

        // Remember the trashed timestamp before the record is restored.
        static::restoring(function (Model $model) {
            static::$attachmentRestoreThresholds[(string) $model->getKey()] = $model->deleted_at;
        });

        // Restore the parent -> restore the attachments removed alongside it.
        static::restored(function (Model $model) {
            $key = (string) $model->getKey();
            $threshold = static::$attachmentRestoreThresholds[$key] ?? null;
            unset(static::$attachmentRestoreThresholds[$key]);

            $query = $model->attachmentsUnscoped()->onlyTrashed();

            if ($threshold) {
                // Only attachments deleted by the cascade (at or after the
                // parent's deletion), not ones removed earlier on purpose.
                $query->where('deleted_at', '>=', $threshold);
            }

            $query->get()->each->restore();
        });

        // Force delete the parent -> unlink + permanently delete files/rows.
        static::forceDeleted(function (Model $model) {
            app(AttachmentService::class)->purge($model);
        });
    }
}
