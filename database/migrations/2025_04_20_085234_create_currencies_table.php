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

        Schema::create('currencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->index();
            $table->string('code')->index();
            $table->string('symbol');
            $table->string('format')->nullable();
            $table->decimal('exchange_rate');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_base_currency')->default(false);
            $table->string('flag')->index()->nullable();
            $table->boolean('is_main')->default(false);
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'name', 'deleted_at']);
            $table->unique(['branch_id', 'code', 'deleted_at']);
            $table->unique(['branch_id', 'flag', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('currencies', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('CASCADE');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
