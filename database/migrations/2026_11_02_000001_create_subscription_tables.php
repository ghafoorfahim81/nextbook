<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10: packages, subscriptions, and the invoices and payments for them —
 * the platform's side of selling the product.
 *
 *  - users.is_platform_admin: whoever runs the platform (not a customer's own
 *    super-admin) and may open the /platform panel. Set from the command line
 *    (php artisan platform:admin), never from a screen.
 *  - packages: what a plan includes — which modules, how many users and
 *    branches — and what it costs. Global, shared by every company.
 *  - subscriptions: one per company; its package and the dates it runs between.
 *    A company with none is not limited (every company that existed before this
 *    keeps working as it did).
 *  - subscription_invoices / subscription_payments: money is recorded by hand
 *    (cash, hawala, bank); there is no payment gateway.
 *
 * These tables carry a company_id but are deliberately NOT under the company
 * row level security policy. The panel reads every company's subscription in
 * one list, which a policy keyed on the acting company would hide, and the
 * application — not the database — keeps them to the platform admin
 * (EnsurePlatformAdmin) and to a company's own banner. They hold the vendor's
 * billing, not a customer's books. RowLevelSecurityTest lists them as exempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false);
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // Module keys from config/modules.php this package includes.
            $table->json('modules');
            // Null means no limit.
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->decimal('price', 19, 4)->default(0);
            $table->string('currency', 3)->default('AFN');
            // The length of one paid period; renewing adds this many months.
            $table->unsignedSmallInteger('period_months')->default(12);
            $table->boolean('is_active')->default(true);
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('company_id')->unique();
            $table->ulid('package_id');
            $table->date('starts_on');
            $table->date('ends_on');
            // Blocked is a decision (non-payment, abuse); expired is only the
            // calendar, worked out from ends_on.
            $table->timestamp('blocked_at')->nullable();
            $table->string('block_reason')->nullable();
            $table->text('notes')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('package_id')->references('id')->on('packages');
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number', 30)->unique();
            $table->ulid('company_id');
            $table->ulid('subscription_id')->nullable();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            // The period this invoice pays for.
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('amount', 19, 4);
            $table->string('currency', 3)->default('AFN');
            $table->string('description')->nullable();
            // open or void; whether it is paid follows from its payments.
            $table->string('status', 10)->default('open');
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'issued_on']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('subscription_invoice_id');
            $table->ulid('company_id');
            $table->decimal('amount', 19, 4);
            $table->date('paid_on');
            // cash, hawala, bank or other.
            $table->string('method', 20)->default('cash');
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('subscription_invoice_id')->references('id')->on('subscription_invoices')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('packages');

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_admin'));
    }
};
