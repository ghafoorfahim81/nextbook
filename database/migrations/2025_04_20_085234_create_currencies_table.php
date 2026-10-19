<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->ulid('company_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->unique(['company_id', 'id']);
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial: unique([..., 'deleted_at']) never fires in PostgreSQL,
        // because deleted_at is NULL on every live row and NULL != NULL.
        DB::statement('CREATE UNIQUE INDEX currencies_company_id_name_live_unique ON currencies (company_id, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX currencies_company_id_code_live_unique ON currencies (company_id, code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX currencies_company_id_flag_live_unique ON currencies (company_id, flag) WHERE deleted_at IS NULL AND flag IS NOT NULL');

        Schema::enableForeignKeyConstraints();

        Schema::table('currencies', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
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
