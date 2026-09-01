<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The dedupe key used to live inside the `data` JSON blob, which meant every
 * duplicate check ran an unindexed `data->>'dedupe_key'` comparison over the
 * whole table. Promote it to a real column with a composite index so the check
 * stays cheap as the table grows.
 *
 * The index is deliberately NOT unique: the 'day' dedupe window re-sends the
 * same key on a later date (a low-stock item is still low tomorrow), so the
 * same (user, type, key) triple legitimately repeats across days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('dedupe_key')->nullable()->after('type');
            $table->index(['user_id', 'type', 'dedupe_key'], 'notifications_dedupe_idx');
            $table->index(['created_at'], 'notifications_created_at_idx');
        });

        // Backfill from the JSON blob so existing dedupe history keeps working
        // and today's scheduled checks do not re-notify everyone.
        DB::table('notifications')
            ->whereNull('dedupe_key')
            ->update(['dedupe_key' => DB::raw("data::jsonb->>'dedupe_key'")]);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_dedupe_idx');
            $table->dropIndex('notifications_created_at_idx');
            $table->dropColumn('dedupe_key');
        });
    }
};
