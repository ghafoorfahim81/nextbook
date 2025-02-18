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
        Schema::disableForeignKeyConstraints();

        Schema::create('designations', function (Blueprint $table) {
            $table->uuid('id');
//            $table->string('name')->unique();
//            $table->text('remark')->nullable();
//            $table->foreignUuid('created_by')->constrained('users');
//            $table->foreignUuid('updated_by')->nullable()->constrained('users');
//            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
