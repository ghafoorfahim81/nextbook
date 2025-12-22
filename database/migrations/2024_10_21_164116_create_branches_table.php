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
        Schema::create('branches', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name')->index();
            $table->text('remark')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_main')->default(false);
            $table->string('sub_domain')->nullable();
            $table->char('parent_id',26)->nullable()->index();
            $table->char('created_by',26)->nullable()->index();
            $table->char('updated_by',26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['name', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('branches', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('branches')
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
        Schema::dropIfExists('branches');
    }
};
