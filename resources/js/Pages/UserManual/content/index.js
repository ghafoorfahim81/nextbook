import en from './en'
import fa from './fa'
import ps from './ps'

const manuals = { en, fa, ps }

export const MANUAL_LOCALES = [
    { id: 'en', label: 'English', dir: 'ltr' },
    { id: 'fa', label: 'دری', dir: 'rtl' },
    { id: 'ps', label: 'پښتو', dir: 'rtl' },
]

export function getManual(locale) {
    return manuals[locale] || manuals.en
}
