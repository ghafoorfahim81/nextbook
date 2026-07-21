import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { COLOR_OPTIONS } from '@/constants/colors'

/**
 * Shared colour helpers for item/variant pickers and read-only views.
 *
 * `colorOptions` is the full palette shaped for NextSelect ({ id, name, hex }),
 * with names translated into the active locale. `resolveColor` turns a single
 * stored value (e.g. "red") back into its label + swatch for display.
 */
export function useColors() {
    const { t } = useI18n()

    const colorOptions = computed(() =>
        COLOR_OPTIONS.map(o => ({
            id: o.value,
            name: t(`colors.${o.value}`),
            hex: o.hex,
        }))
    )

    const resolveColor = (value) => {
        if (!value) return null
        return {
            value,
            name: t(`colors.${value}`),
            hex: COLOR_OPTIONS.find(c => c.value === value)?.hex ?? '#9ca3af',
        }
    }

    // Convenience for plain-text contexts (exports, tooltips).
    const colorLabel = (value) => (value ? t(`colors.${value}`) : '')

    return { colorOptions, resolveColor, colorLabel }
}
