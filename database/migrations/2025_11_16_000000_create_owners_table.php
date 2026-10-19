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
        Schema::create('owners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->index();
            $table->string('father_name');
            $table->string('nic')->nullable()->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->decimal('share_percentage', 5, 2)->default(100)->nullable();
            $table->decimal('profit_share_percentage', 5, 2)->default(100)->nullable();
            $table->boolean('is_active')->default(true); 
            $table->ulid('capital_account_id')->nullable()->index();
            $table->ulid('drawing_account_id')->nullable()->index();
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
        DB::statement('CREATE UNIQUE INDEX owners_company_id_name_live_unique ON owners (company_id, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX owners_company_id_nic_live_unique ON owners (company_id, nic) WHERE deleted_at IS NULL AND nic IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX owners_company_id_email_live_unique ON owners (company_id, email) WHERE deleted_at IS NULL AND email IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX owners_company_id_phone_number_live_unique ON owners (company_id, phone_number) WHERE deleted_at IS NULL AND phone_number IS NOT NULL');

        Schema::table('owners', function (Blueprint $table) {
            // Foreign keys 
            $table->foreign('capital_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('drawing_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};


