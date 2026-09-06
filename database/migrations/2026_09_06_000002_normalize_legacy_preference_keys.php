<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->select(['id', 'preferences'])
            ->whereNotNull('preferences')
            ->orderBy('id')
            ->each(function (object $user): void {
                $preferences = json_decode((string) $user->preferences, true);

                if (! is_array($preferences)) {
                    return;
                }

                $changed = false;

                foreach (['sale', 'sale_order', 'sale_return', 'sale_quotation', 'purchase', 'purchase_order', 'purchase_return', 'purchase_quotation'] as $module) {
                    $fields = &$preferences[$module]['general_fields'];

                    if (is_array($fields) && array_key_exists('store', $fields) && ! array_key_exists('warehouse', $fields)) {
                        $fields['warehouse'] = $fields['store'];
                        unset($fields['store']);
                        $changed = true;
                    }

                    unset($fields);
                }

                $itemFields = &$preferences['item_management']['visible_fields'];
                if (is_array($itemFields) && array_key_exists('file_upload', $itemFields) && ! array_key_exists('photo', $itemFields)) {
                    $itemFields['photo'] = $itemFields['file_upload'];
                    $changed = true;
                }
                unset($itemFields);

                if ($changed) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['preferences' => json_encode($preferences)]);
                }
            });
    }

    public function down(): void
    {
        // The replacement keys are backwards-compatible; do not reintroduce
        // legacy keys on rollback.
    }
};
