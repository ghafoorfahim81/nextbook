const DISPLAY_THEME_MAP = {
    light: 'light',
    dark: 'dark',
    system: 'auto',
}

const COLOR_PALETTES = [
    'system',
    'red',
    'orange',
    'amber',
    'yellow',
    'lime',
    'green',
    'emerald',
    'teal',
    'cyan',
    'sky',
    'blue',
    'indigo',
    'violet',
    'violet-900',
    'purple',
    'fuchsia',
    'pink',
    'rose',
    'slate',
    'gray',
    'zinc',
    'neutral',
    'stone',
    'taupe',
    'mauve',
    'mist',
    'olive',
]
const CUSTOM_APPEARANCE_THEMES = COLOR_PALETTES.filter((palette) => palette !== 'system')
const ACCENT_COLORS = COLOR_PALETTES.filter((palette) => palette !== 'violet-900')

export function resolveAppearanceTheme(preferences) {
    const appearanceTheme = preferences?.appearance?.color_palette ?? preferences?.appearance?.theme

    if (appearanceTheme === 'violet-900') {
        return 'violet'
    }

    return CUSTOM_APPEARANCE_THEMES.includes(appearanceTheme) ? appearanceTheme : 'default'
}

export function resolveDisplayColorMode(preferences) {
    const appearanceTheme = preferences?.appearance?.theme
    const displayTheme = preferences?.display?.theme

    // Older preferences stored light/dark/system in appearance.theme.
    if (!displayTheme && appearanceTheme && DISPLAY_THEME_MAP[appearanceTheme]) {
        return DISPLAY_THEME_MAP[appearanceTheme]
    }

    return DISPLAY_THEME_MAP[displayTheme] ?? 'auto'
}

export function resolveColorPalette(preferences) {
    const palette = preferences?.appearance?.color_palette ?? preferences?.appearance?.theme

    if (palette === 'violet-900') {
        return 'violet'
    }

    return COLOR_PALETTES.includes(palette) ? palette : 'system'
}

export function resolveAccentColor(preferences) {
    const accentColor = preferences?.appearance?.accent_color

    return ACCENT_COLORS.includes(accentColor) ? accentColor : 'system'
}

export function applyAppearanceTheme(preferences) {
    if (typeof document === 'undefined') return

    document.documentElement.dataset.theme = resolveAppearanceTheme(preferences)
    document.documentElement.dataset.accentColor = resolveAccentColor(preferences)
}
