<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('journal_classes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->index();
            $table->string('code')->nullable()->index();
            $table->text('description')->nullable();
            $table->ulid('company_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable()->index();
            $table->ulid('deleted_by')->nullable()->index();
            $table->softDeletes();
            $table->unique(['company_id', 'id']);
            $table->timestamps();
        });
        Schema::table('journal_classes', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_classes');
    }
};
