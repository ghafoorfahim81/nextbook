<?php

use App\Enums\JournalEntrySource;
use App\Enums\JournalEntryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number')->index();
            $table->date('date')->index();
            $table->enum('status', JournalEntryStatus::values())
                ->default(JournalEntryStatus::Draft->value);
            $table->enum('source', JournalEntrySource::values())
                ->default(JournalEntrySource::Manual->value);
            $table->ulid('financial_period_id')->index();
            $table->string('reference_type')->nullable()->index();
            $table->ulid('reference_id')->nullable()->index();
            $table->timestamp('posted_at')->nullable();
            $table->ulid('posted_by')->nullable()->index();
            $table->ulid('reversal_of_id')->nullable()->index();
            $table->timestamp('reversed_at')->nullable();
            $table->string('post_blocked_reason')->nullable();
            $table->text('description')->nullable();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreign('financial_period_id')->references('id')->on('financial_periods');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('posted_by')->references('id')->on('users');
            $table->foreign('reversal_of_id')->references('id')->on('journal_entries');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
