import { useI18n } from 'vue-i18n'

/**
 * Reader-facing labels for activity log rows.
 *
 * The log stores codes ("posted", "sale_return") and an English sentence
 * written at the time of the action. Both are translated here so the page
 * reads in the user's language — including rows logged before the language
 * was switched. Unknown codes fall back to a readable form of the code.
 */
export function useActivityLogLabels() {
    const { t, te, locale } = useI18n()

    const humanize = (value) =>
        String(value ?? '')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase())

    const labelFor = (group, code) => {
        if (!code) return '-'
        const key = `activity_log.${group}.${code}`
        return te(key) ? t(key) : humanize(code)
    }

    const eventLabel = (code) => labelFor('events', code)
    const moduleLabel = (code) => labelFor('modules', code)

    // English keeps the stored sentence, which can carry extra detail
    // ("created against Sale #12"); other languages get a translated one.
    const describe = (log) => {
        if (!log) return '-'
        if (locale.value === 'en' && log.description) return log.description

        return t('activity_log.description_template', {
            event: eventLabel(log.event_type),
            module: moduleLabel(log.module),
            subject: log.subject ?? '',
        }).replace(/\s+/g, ' ').trim()
    }

    const eventVariant = (code) => {
        const type = String(code || '').toLowerCase()
        if (['deleted', 'delete', 'rejected', 'cancelled', 'reversed', 'voided'].includes(type)) return 'destructive'
        if (['approved', 'posted', 'completed', 'created', 'create', 'paid'].includes(type)) return 'default'
        return 'secondary'
    }

    return { eventLabel, moduleLabel, describe, eventVariant }
}
