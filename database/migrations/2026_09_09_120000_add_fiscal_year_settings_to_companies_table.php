<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a company's financial year begins.
 *
 * Stored as a month/day pair rather than a fixed rule because the answer is not
 * the same for everyone here. Afghanistan's state fiscal year has run from
 * 1 Jadi since 1391; businesses that predate the change, and NGOs reporting on
 * a donor calendar, still keep books on 1 Hamal or on 1 January. Hardcoding any
 * one of them means the other two cannot close a year at all.
 *
 * The pair is read in the company's OWN calendar (companies.calendar_type), so
 * month 10 means Jadi for a Jalali company and October for a Gregorian one.
 *
 * NULL — the default — means "whatever is normal for that calendar": 1 Jadi for
 * a Jalali company, 1 January for a Gregorian one. A single numeric default
 * cannot do that. Defaulting the column to 10 gives Jalali companies the right
 * answer and Gregorian ones an October-to-September year nobody asked for, so
 * the calendar has to be consulted and null is what lets that happen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedTinyInteger('fiscal_year_start_month')->nullable()->after('calendar_type');
            $table->unsignedTinyInteger('fiscal_year_start_day')->nullable()->after('fiscal_year_start_month');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['fiscal_year_start_month', 'fiscal_year_start_day']);
        });
    }
};
