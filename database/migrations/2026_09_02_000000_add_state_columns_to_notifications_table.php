<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The notification centre only knew "read" and "unread". Users asked to be able
 * to keep the important ones (favourite), clear the noise without losing history
 * (archive), and permanently remove a few (soft delete, so a mis-click is
 * recoverable and the dedupe history is unaffected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_read');
            $table->timestamp('favorited_at')->nullable()->after('archived_at');
            $table->softDeletes();

            $table->index(['user_id', 'archived_at'], 'notifications_user_archived_idx');
            $table->index(['user_id', 'favorited_at'], 'notifications_user_favorited_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_archived_idx');
            $table->dropIndex('notifications_user_favorited_idx');
            $table->dropColumn(['archived_at', 'favorited_at', 'deleted_at']);
        });
    }
};
