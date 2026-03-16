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
            $table->ulid('id')->primary();
            $table->string('name')->index();
            $table->text('remark')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_main')->default(false);
            $table->string('sub_domain')->nullable();
            $table->ulid('parent_id')->nullable()->index(); 
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
