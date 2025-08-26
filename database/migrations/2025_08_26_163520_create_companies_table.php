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

        Schema::create('companies', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name_en')->index();
            $table->string('name_fa')->nullable()->index();
            $table->string('name_pa')->nullable()->index();
            $table->string('abbreviation')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('logo')->nullable();
            $table->string('calendar_type')->nullable();
            $table->string('working_style')->nullable();
            $table->string('business_type')->nullable();
            $table->string('locale')->nullable();
            $table->char('currency_id', 26)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('invoice_description')->nullable();
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('currency_id')->references('id')->on('currencies'); 
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
