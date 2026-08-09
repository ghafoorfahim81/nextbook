<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AttachmentService
{
    /**
     * Store one or more uploaded files against the given model.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function store(Model $model, array $files, string $disk = 'public'): void
    {
        $directory = 'attachments/' . class_basename($model);

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store($directory, $disk);

            $model->attachments()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * Remove a file from disk and soft-delete its record.
     */
    public function destroy(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);

        $attachment->delete();
    }

    /**
     * Permanently delete the file from storage and remove the row.
     */
    public function forceDestroy(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);

        $attachment->forceDelete();
    }

    /**
     * Unlink and permanently delete every attachment (trashed or not)
     * belonging to a model, including the underlying files.
     */
    public function purge(Model $model): void
    {
        if (! method_exists($model, 'attachmentsUnscoped')) {
            return;
        }

        $model->attachmentsUnscoped()->withTrashed()->get()->each(
            fn (Attachment $attachment) => $this->forceDestroy($attachment)
        );
    }
}
