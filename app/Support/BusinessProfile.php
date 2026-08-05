<?php

namespace App\Support;

use App\Enums\BusinessType;
use App\Models\Administration\Company;
use Illuminate\Support\Arr;

/**
 * Resolves the item-module shape for a company's trade.
 *
 * Layers, lowest first:
 *   1. config('business_profiles.base')          — sensible defaults
 *   2. config('business_profiles.profiles.<type>') — what this trade differs on
 *   3. company.preferences.item_management        — what the owner overrode
 *
 * Company preferences win, so a supermarket that wants batch numbers as well
 * as expiry dates can simply switch them on without touching the profile.
 */
class BusinessProfile
{
    public function __construct(
        private readonly array $resolved,
        public readonly ?BusinessType $businessType,
    ) {
    }

    public static function for(?Company $company): self
    {
        $type = $company?->business_type;

        $base = (array) config('business_profiles.base', []);
        $profile = (array) config(
            'business_profiles.profiles.' . ($type?->value ?? ''),
            []
        );

        $resolved = static::mergeLayer($base, $profile);

        $overrides = Arr::get((array) ($company?->preferences ?? []), 'item_management', []);

        if (! empty($overrides)) {
            $resolved = static::mergeLayer($resolved, static::normaliseOverrides($overrides));
        }

        return new self($resolved, $type);
    }

    /**
     * The profile for the signed-in user's company.
     */
    public static function current(): self
    {
        return static::for(auth()->user()?->company);
    }

    /** @return array<string, bool> */
    public function fields(): array
    {
        return (array) ($this->resolved['fields'] ?? []);
    }

    /** @return array<string, bool> */
    public function sections(): array
    {
        return (array) ($this->resolved['sections'] ?? []);
    }

    /** @return array<string, bool> */
    public function defaults(): array
    {
        return (array) ($this->resolved['defaults'] ?? []);
    }

    /**
     * What this trade calls a lot: "batch" for a pharmacy, "expiry" for a
     * bakery, "lot" for a factory.
     */
    public function specLabel(): string
    {
        return (string) ($this->resolved['spec_label'] ?? 'batch');
    }

    public function showsField(string $field): bool
    {
        return (bool) ($this->fields()[$field] ?? false);
    }

    public function showsSection(string $section): bool
    {
        return (bool) ($this->sections()[$section] ?? false);
    }

    /**
     * Shape shared with Inertia so the Vue forms render the same decision.
     */
    public function toArray(): array
    {
        return [
            'business_type' => $this->businessType?->value,
            'spec_label' => $this->specLabel(),
            'fields' => $this->fields(),
            'sections' => $this->sections(),
            'defaults' => $this->defaults(),
        ];
    }

    /**
     * Merge one layer over another, replacing scalars but merging the three
     * known maps key-by-key so a profile need only list what it changes.
     */
    private static function mergeLayer(array $under, array $over): array
    {
        $result = $under;

        foreach (['fields', 'sections', 'defaults'] as $group) {
            $result[$group] = array_merge(
                (array) ($under[$group] ?? []),
                (array) ($over[$group] ?? []),
            );
        }

        if (isset($over['spec_label'])) {
            $result['spec_label'] = $over['spec_label'];
        }

        return $result;
    }

    /**
     * The stored preferences use `visible_fields`; the profile uses `fields`.
     * Translate so existing company preferences keep working unchanged.
     */
    private static function normaliseOverrides(array $overrides): array
    {
        $normalised = [];

        if (isset($overrides['visible_fields']) && is_array($overrides['visible_fields'])) {
            $normalised['fields'] = $overrides['visible_fields'];
        }

        foreach (['sections', 'defaults'] as $group) {
            if (isset($overrides[$group]) && is_array($overrides[$group])) {
                $normalised[$group] = $overrides[$group];
            }
        }

        if (isset($overrides['spec_text']) && filled($overrides['spec_text'])) {
            $normalised['spec_label'] = $overrides['spec_text'];
        }

        return $normalised;
    }
}
