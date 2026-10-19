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

        Schema::create('designations', function (Blueprint $table) {
            $table->ulid('id')->primary();
           $table->string('name')->index();
           $table->text('remark')->nullable();
           $table->ulid('company_id')->index();
           $table->ulid('created_by')->index();
           $table->ulid('updated_by')->nullable();
           $table->unique(['company_id', 'id']);
           $table->timestamps();
           $table->softDeletes();
           $table->ulid('deleted_by')->nullable();
        });

        // Partial: unique([..., 'deleted_at']) never fires in PostgreSQL,
        // because deleted_at is NULL on every live row and NULL != NULL.
        DB::statement('CREATE UNIQUE INDEX designations_company_id_name_live_unique ON designations (company_id, name) WHERE deleted_at IS NULL');

        Schema::enableForeignKeyConstraints();
        Schema::table('designations', function (Blueprint $table) {
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
        Schema::dropIfExists('designations');
    }
};
