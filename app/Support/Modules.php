<?php

namespace App\Support;

use App\Models\Administration\Company;

/**
 * Which modules a company has on (phase 6). The list is config/modules.php;
 * whether one is on is the company setting `modules.<key>`, resolved by
 * Settings (defaults → business type → company).
 */
class Modules
{
    /** @return array<string, array{available: bool, paths: list<string>}> */
    public static function all(): array
    {
        return (array) config('modules', []);
    }

    /**
     * Each business type's module switches, for the set-up wizard to apply
     * when a trade is picked. @return array<string, array<string, bool>>
     */
    public static function defaultsByBusinessType(): array
    {
        return collect(\App\Enums\BusinessType::cases())
            ->mapWithKeys(fn ($type) => [
                $type->value => (array) Settings::forCompany(new Company(['business_type' => $type->value]))['modules'],
            ])
            ->all();
    }

    /** The modules that exist, whether on or off. @return list<string> */
    public static function available(): array
    {
        return array_keys(array_filter(self::all(), fn (array $module) => $module['available'] ?? false));
    }

    public static function isEnabled(string $module, ?Company $company = null): bool
    {
        $company ??= CompanyContext::company();

        return in_array($module, self::available(), true)
            && (bool) (Settings::forCompany($company)['modules'][$module] ?? false);
    }

    /** The available modules that are off. @return list<string> */
    public static function disabled(?Company $company = null): array
    {
        $company ??= CompanyContext::company();
        $switches = (array) (Settings::forCompany($company)['modules'] ?? []);

        return array_values(array_filter(self::available(), fn (string $module) => ! ($switches[$module] ?? false)));
    }

    /**
     * The first URL segments that belong to modules that are off, with a
     * leading slash ("/employees"), for the sidebar and the search to leave out.
     *
     * @return list<string>
     */
    public static function disabledPaths(?Company $company = null): array
    {
        return collect(self::disabled($company))
            ->flatMap(fn (string $module) => self::all()[$module]['paths'] ?? [])
            ->map(fn (string $path) => '/'.$path)
            ->values()
            ->all();
    }

    /** The module a URL path belongs to, if any ("employees/12/edit" → hr). */
    public static function forPath(string $path): ?string
    {
        $segment = explode('/', trim($path, '/'))[0] ?? '';

        foreach (self::all() as $key => $module) {
            if (in_array($segment, $module['paths'] ?? [], true)) {
                return $key;
            }
        }

        return null;
    }

    /** Whether a URL sits under a module that is off. */
    public static function blocksUrl(string $url, array $disabledPaths): bool
    {
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        foreach ($disabledPaths as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
