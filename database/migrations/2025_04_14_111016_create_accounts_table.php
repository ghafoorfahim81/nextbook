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
            $table->string('name')->unique();
            $table->string('number')->unique();
            $table->char('account_type_id', 26)->constrained();
            $table->boolean('is_active')->default(true);
            $table->char('branch_id', 26);
            $table->text('remark')->nullable();
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
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
