<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->ulid('posted_by')->nullable()->after('status');
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->ulid('reversal_of_id')->nullable()->after('posted_at');
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');
        });

        DB::statement("ALTER TABLE purchases ALTER COLUMN status SET DEFAULT 'draft'");

        Schema::table('sales', function (Blueprint $table) {
            $table->ulid('posted_by')->nullable()->after('status');
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->ulid('reversal_of_id')->nullable()->after('posted_at');
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');
        });

        DB::statement("ALTER TABLE sales ALTER COLUMN status SET DEFAULT 'draft'");
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['posted_by', 'posted_at', 'reversal_of_id', 'reversed_at']);
        });

        DB::statement("ALTER TABLE purchases ALTER COLUMN status SET DEFAULT 'posted'");

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['posted_by', 'posted_at', 'reversal_of_id', 'reversed_at']);
        });

        DB::statement("ALTER TABLE sales ALTER COLUMN status SET DEFAULT 'posted'");
    }
};
