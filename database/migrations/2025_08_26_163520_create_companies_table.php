<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\CalendarType;
use App\Enums\BusinessType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('companies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name_en')->index();
            $table->string('name_fa')->nullable()->index();
            $table->string('name_pa')->nullable()->index();
            $table->string('abbreviation')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('logo')->nullable();
            $table->enum('calendar_type', CalendarType::values())->nullable()->default(CalendarType::JALALI->value);
            $table->enum('working_style', WorkingStyle::values())->nullable()->default(WorkingStyle::NORMAL->value);
            $table->enum('business_type', BusinessType::values())->nullable()->default(BusinessType::PHARMACY_SHOP->value);
            $table->enum('locale', Locale::values())->nullable()->default(Locale::EN->value);
            $table->ulid('currency_id')->index();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('invoice_description')->nullable();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
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
