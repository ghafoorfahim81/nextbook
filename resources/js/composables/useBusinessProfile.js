import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * The item-module shape for the signed-in user's trade.
 *
 * Resolved server-side (config/business_profiles.php -> App\Support\BusinessProfile)
 * and shared on every Inertia response as `business_profile`, so the Vue forms
 * make the same decision the backend validation does.
 *
 * A page may pass its own `businessProfile` prop to override the shared one —
 * useful when previewing another trade's layout.
 */
export function useBusinessProfile(pageProfile = null) {
    const page = usePage()

    const profile = computed(() => {
        return pageProfile?.value
            ?? pageProfile
            ?? page.props.business_profile
            ?? { fields: {}, sections: {}, defaults: {}, spec_label: 'batch' }
    })

    const fields = computed(() => profile.value.fields ?? {})
    const sections = computed(() => profile.value.sections ?? {})
    const defaults = computed(() => profile.value.defaults ?? {})
    const businessType = computed(() => profile.value.business_type ?? null)

    /** Whether a given item field is shown for this trade. */
    const showsField = (name) => Boolean(fields.value[name])

    /** Whether a whole section (variants, lots, pieces, openings) applies. */
    const showsSection = (name) => Boolean(sections.value[name])

    /**
     * What this trade calls a lot: "batch" for a pharmacy, "expiry" for a
     * bakery, "lot" for a factory. Falls back to the raw key so a custom
     * spec_text set in preferences still displays.
     */
    const specLabel = computed(() => profile.value.spec_label ?? 'batch')

    /**
     * Merge the trade's tracking defaults into a fresh form, without
     * clobbering anything the user has already set.
     */
    const applyDefaults = (form) => {
        Object.entries(defaults.value).forEach(([flag, value]) => {
            if (form[flag] === undefined || form[flag] === null || form[flag] === false) {
                form[flag] = value
            }
        })
    }

    return {
        profile,
        fields,
        sections,
        defaults,
        businessType,
        specLabel,
        showsField,
        showsSection,
        applyDefaults,
    }
}
