<?php

use App\Models\Administration\Company;
use App\Models\User;
use App\Support\Preferences\InvoiceThemeOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('invoice_theme', 50)
                ->default(InvoiceThemeOptions::DEFAULT)
                ->after('invoice_description');
        });

        // Company preferences were previously seeded from the earliest company
        // user. Preserve that shared baseline, falling back to the creator only
        // for companies whose preferences were not populated.
        Company::query()->each(function (Company $company): void {
            $theme = data_get($company->preferences, 'sale.invoice_theme')
                ?? data_get(
                    User::query()->find($company->created_by)?->preferences,
                    'sale.invoice_theme'
                );

            if (is_string($theme) && $theme !== '') {
                $company->forceFill(['invoice_theme' => $theme])->saveQuietly();
            }
        });

        User::query()->each(function (User $user): void {
            $preferences = $user->preferences;

            if (! is_array($preferences) || ! isset($preferences['sale']['invoice_theme'])) {
                return;
            }

            unset($preferences['sale']['invoice_theme']);
            $user->forceFill(['preferences' => $preferences])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('invoice_theme');
        });
    }
};
