<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('departments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->index();
            $table->string('code')->index();
            $table->text('remark')->nullable();
            $table->ulid('parent_id')->nullable()->index();
            $table->char('created_by',26)->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->ulid('company_id')->index();
            $table->unique(['company_id', 'id']);
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial: unique([..., 'deleted_at']) never fires in PostgreSQL,
        // because deleted_at is NULL on every live row and NULL != NULL.
        DB::statement('CREATE UNIQUE INDEX departments_company_id_name_live_unique ON departments (company_id, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX departments_company_id_code_live_unique ON departments (company_id, code) WHERE deleted_at IS NULL');

        Schema::enableForeignKeyConstraints();

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('departments')
                ->onDelete('CASCADE');

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
