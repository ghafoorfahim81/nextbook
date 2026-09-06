import en from './en'
import fa from './fa'
import ps from './ps'
import { GUIDE_META, GUIDE_ORDER, guideMeta } from './guides'

const manuals = { en, fa, ps }

export const MANUAL_LOCALES = [
    { id: 'en', label: 'English', dir: 'ltr' },
    { id: 'fa', label: 'دری', dir: 'rtl' },
    { id: 'ps', label: 'پښتو', dir: 'rtl' },
]

export { GUIDE_META, GUIDE_ORDER, guideMeta }

function manualFor(locale) {
    return manuals[locale] || manuals.en
}

export function getMeta(locale) {
    return manualFor(locale).meta
}

/**
 * Every guide for a locale, ordered by GUIDE_ORDER and merged with the shared
 * icon/accent metadata. A guide missing from the locale falls back to English.
 */
export function getGuides(locale) {
    const manual = manualFor(locale)
    const fallback = manuals.en

    return GUIDE_ORDER.map((id) => {
        const guide = manual.guides.find((g) => g.id === id)
            || fallback.guides.find((g) => g.id === id)
        if (!guide) return null
        return { ...guide, ...guideMeta(id) }
    }).filter(Boolean)
}

export function getGuide(locale, guideId) {
    return getGuides(locale).find((g) => g.id === guideId) || null
}
