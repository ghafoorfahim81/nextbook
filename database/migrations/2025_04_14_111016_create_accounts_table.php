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

        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->index();
            $table->string('local_name')->nullable();
            $table->string('number')->index();
            $table->ulid('account_type_id')->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_main')->default(false);
            $table->string('slug')->nullable();
            $table->ulid('company_id')->index();
            $table->text('remark')->nullable();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->unique(['company_id', 'id']);
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial: unique([..., 'deleted_at']) never fires in PostgreSQL,
        // because deleted_at is NULL on every live row and NULL != NULL.
        DB::statement('CREATE UNIQUE INDEX accounts_company_id_number_live_unique ON accounts (company_id, number) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX accounts_company_id_name_live_unique ON accounts (company_id, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX accounts_company_id_slug_live_unique ON accounts (company_id, slug) WHERE deleted_at IS NULL AND slug IS NOT NULL');

        Schema::enableForeignKeyConstraints();
        Schema::table('accounts', function (Blueprint $table) {

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('CASCADE');

            $table->foreign('account_type_id')
                ->references('id')
                ->on('account_types')
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
        Schema::dropIfExists('accounts');
    }
};
