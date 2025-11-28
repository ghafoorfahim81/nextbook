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

        Schema::create('accounts', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name')->index();
            $table->string('number')->index();
            $table->char('account_type_id', 26);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_main')->default(false);
            $table->string('slug')->unique();
            $table->char('branch_id', 26);
            $table->text('remark')->nullable();
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by',26)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'number', 'deleted_at']);
            $table->unique(['branch_id', 'name', 'deleted_at']);
            $table->unique(['branch_id', 'slug', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
        Schema::table('accounts', function (Blueprint $table) {

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
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
