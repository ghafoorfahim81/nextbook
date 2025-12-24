<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->ulid('branch_id')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'name', 'deleted_at']);
            $table->unique(['branch_id', 'code', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('departments')
                ->onDelete('CASCADE');

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
