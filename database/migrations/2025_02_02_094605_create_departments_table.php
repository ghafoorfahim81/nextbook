<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('departments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name')->index();
            $table->string('code')->index();
            $table->text('remark')->nullable();
            $table->char('parent_id',26)->nullable()->index();
            $table->char('created_by',26)->index();
            $table->char('updated_by',26)->nullable();
            $table->char('deleted_by',26)->nullable();
            $table->char('branch_id',26)->index();
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
