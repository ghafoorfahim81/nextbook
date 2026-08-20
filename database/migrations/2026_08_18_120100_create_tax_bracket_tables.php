<?php

use App\Enums\TaxPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wage tax brackets, stored as effective-dated data rather than code.
 *
 * Rates change by statute. Holding them in a table with an effective-from date
 * means a change is an edit rather than a deploy, AND — more importantly — that
 * re-running an old period reproduces the tax that was actually withheld at the
 * time, because the run records which bracket set it used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('tax_bracket_sets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('jurisdiction', 10)->default('AF');
            $table->enum('period', TaxPeriod::values())->default(TaxPeriod::Monthly->value);

            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable();

            $table->ulid('currency_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            // Seeded by branch provisioning. Flagged so the UI can warn before
            // someone edits the statutory table rather than adding their own.
            $table->boolean('is_system')->default(false);
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'period', 'effective_from']);
        });

        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tax_bracket_set_id')->index();
            $table->smallInteger('sequence')->default(0);

            $table->decimal('from_amount', 18, 4)->default(0);
            // Null means the open-ended top band.
            $table->decimal('to_amount', 18, 4)->nullable();
            // The cumulative tax owed at from_amount, so each band only has to
            // compute the marginal part above its own floor.
            $table->decimal('fixed_amount', 18, 4)->default(0);
            $table->decimal('rate', 10, 4)->default(0);

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tax_bracket_set_id', 'sequence', 'deleted_at'], 'tax_bracket_sequence_unique');
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('tax_bracket_sets', function (Blueprint $table) {
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('tax_brackets', function (Blueprint $table) {
            $table->foreign('tax_bracket_set_id')->references('id')->on('tax_bracket_sets')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
        Schema::dropIfExists('tax_bracket_sets');
    }
};
