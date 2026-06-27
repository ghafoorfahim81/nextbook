export function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
}

export const HIGHLIGHT_MARK_CLASS = 'bg-primary/25 text-primary rounded-[2px] font-medium not-italic'
export const HIGHLIGHT_MARK_ON_PRIMARY_CLASS = 'bg-white/30 text-white font-bold rounded-[2px] not-italic'

/**
 * Returns HTML with matching substrings wrapped in <mark> tags.
 */
export function highlightSearchText(text, query, markClass = HIGHLIGHT_MARK_CLASS) {
    const raw = String(text ?? '')
    const q = String(query ?? '').trim()
    if (!q) {
        return escapeHtml(raw)
    }

    const lowerText = raw.toLowerCase()
    const lowerQuery = q.toLowerCase()
    if (!lowerText.includes(lowerQuery)) {
        return escapeHtml(raw)
    }

    let out = ''
    let last = 0
    let idx = lowerText.indexOf(lowerQuery, last)

    while (idx !== -1) {
        out += escapeHtml(raw.slice(last, idx))
        out += `<mark class="${markClass}">${escapeHtml(raw.slice(idx, idx + q.length))}</mark>`
        last = idx + q.length
        idx = lowerText.indexOf(lowerQuery, last)
    }

    out += escapeHtml(raw.slice(last))
    return out
}

export function textHasSearchMatch(text, query) {
    const q = String(query ?? '').trim()
    if (!q) {
        return false
    }

    return String(text ?? '').toLowerCase().includes(q.toLowerCase())
}
