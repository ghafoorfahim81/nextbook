import { createI18n } from 'vue-i18n'

function loadLocaleMessages() {
    const locales = import.meta.glob('../locales/*/*.json', { eager: true })
    const messages = {}

    for (const path in locales) {
        const match = path.match(/\.\.\/locales\/(\w+)\/(.+)\.json$/)
        if (!match) continue
        const [, locale, file] = match
        messages[locale] = messages[locale] || {}
        messages[locale][file] = locales[path].default || {}
    }

    return messages
}

export function createI18nInstance(locale = 'en') {
    return createI18n({
        legacy: false,
        locale,
        fallbackLocale: 'en',
        missingWarn: false,
        fallbackWarn: false,
        messages: loadLocaleMessages(),
    })
}


