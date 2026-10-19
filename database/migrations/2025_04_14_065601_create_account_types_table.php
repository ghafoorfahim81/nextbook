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
        Schema::create('account_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->index();
            $table->string('slug')->nullable();
            $table->string('nature')->nullable();
            $table->text('remark')->nullable();
            $table->boolean('is_main')->default(false);
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->ulid('company_id')->index();
            $table->unique(['company_id', 'id']);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'name', 'deleted_at']);
            $table->unique(['company_id', 'slug', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
        Schema::table('account_types', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_types');
    }
};
