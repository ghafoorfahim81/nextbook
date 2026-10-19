import { usePage } from '@inertiajs/vue3'

// One Audio element per resolved URL, reused across plays so rapid-fire
// notifications don't leak elements or refetch the file every time.
const audioCache = new Map()

function getAudio(url) {
    if (!audioCache.has(url)) {
        audioCache.set(url, new Audio(url))
    }
    return audioCache.get(url)
}

export function useSoundPreferences() {
    const page = usePage()

    function soundPrefs() {
        return page.props.user_preferences?.notifications?.sound || {}
    }

    /**
     * Resolve the playable URL for a sound slot (notification/warning/login),
     * honoring a custom upload when the user has chosen one.
     */
    function resolveUrl(category) {
        const prefs = soundPrefs()
        const choice = prefs[`${category}_choice`]

        if (choice === 'custom') {
            return prefs[`${category}_custom_url`] || null
        }

        return choice ? `/sounds/${choice}.mp3` : null
    }

    /**
     * Play the sound for a slot if the user has enabled it for that slot.
     */
    function play(category) {
        const prefs = soundPrefs()
        if (prefs[`${category}_enabled`] === false) return

        const url = resolveUrl(category)
        if (!url) return

        try {
            const audio = getAudio(url)
            audio.currentTime = 0
            audio.play().catch(() => {
                // Autoplay can be blocked outside a user gesture; failing silently
                // is preferable to surfacing a console error to the user.
            })
        } catch {
            // Ignore playback errors (unsupported format, missing file, etc.)
        }
    }

    return { play, resolveUrl }
}
