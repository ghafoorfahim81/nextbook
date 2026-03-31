const DISPLAY_THEME_MAP = {
    light: 'light',
    dark: 'dark',
    system: 'auto',
}

const CUSTOM_APPEARANCE_THEMES = ['cyan', 'violet-900']

export function resolveAppearanceTheme(preferences) {
    const appearanceTheme = preferences?.appearance?.theme

    return CUSTOM_APPEARANCE_THEMES.includes(appearanceTheme) ? appearanceTheme : 'default'
}

export function resolveDisplayColorMode(preferences) {
    const appearanceTheme = preferences?.appearance?.theme
    if (appearanceTheme && DISPLAY_THEME_MAP[appearanceTheme]) {
        return DISPLAY_THEME_MAP[appearanceTheme]
    }

    const storedTheme = preferences?.display?.theme
    return DISPLAY_THEME_MAP[storedTheme] ?? 'auto'
}

export function applyAppearanceTheme(preferences) {
    if (typeof document === 'undefined') return

    document.documentElement.dataset.theme = resolveAppearanceTheme(preferences)
}
