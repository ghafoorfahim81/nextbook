<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Enums\LedgerType;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('ledgers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->index();
            $table->string('code')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable()->index();
            $table->string('phone_no')->nullable();
            $table->string('email')->nullable();
            $table->ulid('currency_id')->nullable()->index();
            $table->ulid('company_id')->nullable()->index();
            $table->boolean('is_main')->default(false); 
            $table->enum('type', LedgerType::values())->default(LedgerType::CUSTOMER->value);
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->unique(['company_id', 'id']);
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial: unique([..., 'deleted_at']) never fires in PostgreSQL,
        // because deleted_at is NULL on every live row and NULL != NULL.
        DB::statement('CREATE UNIQUE INDEX ledgers_company_id_name_live_unique ON ledgers (company_id, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ledgers_company_id_phone_no_live_unique ON ledgers (company_id, phone_no) WHERE deleted_at IS NULL AND phone_no IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX ledgers_company_id_email_live_unique ON ledgers (company_id, email) WHERE deleted_at IS NULL AND email IS NOT NULL');

        Schema::table('ledgers', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
