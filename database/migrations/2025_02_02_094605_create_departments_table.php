<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('remark')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();

            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('departments')
                ->onDelete('CASCADE');

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
