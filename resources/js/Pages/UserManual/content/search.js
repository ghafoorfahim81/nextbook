/**
 * Search over the user-manual content, shared by the global search on the
 * guide picker and the per-guide search in the reader.
 *
 * Matching is done on a normalised form of the text so that what a user types
 * finds what the guides actually contain:
 *   - Arabic ي / ك / ة and Persian ی / ک / ه are treated as the same letter
 *   - the ZWNJ half-space (می‌کند) matches a normal space (می کند)
 *   - Persian and Arabic-Indic digits match Latin digits, and thousands
 *     separators inside numbers are ignored (۳٬۹۰۰ ≙ 3,900 ≙ 3900)
 *   - harakat, tatweel, case and punctuation are ignored
 * A query is split into words and every word must appear somewhere in the
 * chapter (in any order), so "customer invoice" finds a chapter that mentions
 * both without the two being adjacent.
 */

const DIGITS = {
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
}

const LETTERS = {
    'ي': 'ی', 'ى': 'ی', 'ئ': 'ی', 'ې': 'ی', 'ۍ': 'ی',
    'ك': 'ک',
    'ة': 'ه', 'ۀ': 'ه', 'ہ': 'ه',
    'أ': 'ا', 'إ': 'ا', 'آ': 'ا', 'ٱ': 'ا',
    'ؤ': 'و',
}

const DROP = /[ً-ٰٟۖ-ۭـ‎‏]/ // harakat, tatweel, LRM/RLM
const SPACE_LIKE = /[\s‌‍ ]/ // whitespace, ZWNJ, ZWJ, nbsp
const THOUSANDS = /[,٬]/
const DIGIT = /[0-9]/
const WORD = /[\p{L}\p{N}]/u

/**
 * Normalise `raw` for matching, and record for every output character the
 * index of the raw character it came from — so a match found in the
 * normalised text can be mapped back onto the original for highlighting.
 */
export function normalizeWithMap(raw) {
    const source = String(raw ?? '')
    let out = ''
    const map = []

    const pushSpace = (index) => {
        if (out.length && out[out.length - 1] !== ' ') {
            out += ' '
            map.push(index)
        }
    }

    for (let i = 0; i < source.length; i++) {
        let ch = source[i]

        if (DROP.test(ch)) continue

        if (SPACE_LIKE.test(ch)) {
            pushSpace(i)
            continue
        }

        ch = DIGITS[ch] ?? ch

        // 12,500 / ۱۲٬۵۰۰ — a separator between two digits is not a word break.
        if (THOUSANDS.test(ch)) {
            const prev = out[out.length - 1]
            const next = DIGITS[source[i + 1]] ?? source[i + 1]
            if (prev && DIGIT.test(prev) && next && DIGIT.test(next)) continue
        }

        ch = LETTERS[ch] ?? ch

        for (const part of ch.normalize('NFKC').toLowerCase()) {
            if (WORD.test(part)) {
                out += part
                map.push(i)
            } else {
                pushSpace(i)
            }
        }
    }

    if (out.endsWith(' ')) {
        out = out.slice(0, -1)
        map.pop()
    }

    return { text: out, map }
}

export function normalize(raw) {
    return normalizeWithMap(raw).text
}

/** The distinct words of a query, longest first, empties dropped. */
export function queryTerms(query) {
    const terms = normalize(query).split(' ').filter(Boolean)
    return [...new Set(terms)].sort((a, b) => b.length - a.length)
}

/** Every piece of readable text in a block, whatever its type. */
export function blockTexts(block) {
    if (!block) return []
    const parts = []
    for (const key of ['label', 'text', 'caption', 'hint']) {
        if (block[key]) parts.push(String(block[key]))
    }
    for (const key of ['items', 'steps', 'headers']) {
        if (Array.isArray(block[key])) parts.push(...block[key].map(String))
    }
    // A whole row reads as one sentence ("IBAN — International Bank Account
    // Number…"), which makes a far better search snippet than a lone cell.
    if (Array.isArray(block.rows)) {
        for (const row of block.rows) parts.push(row.map(String).join(' — '))
    }
    return parts
}

function chapterSegments(chapter) {
    return [chapter.title, ...chapter.blocks.flatMap(blockTexts)].filter(Boolean)
}

/** Does every term of `query` appear somewhere in this chapter? */
export function chapterMatches(chapter, query) {
    const terms = queryTerms(query)
    if (!terms.length) return true
    const haystack = chapterSegments(chapter).map(normalize).join(' ')
    return terms.every((term) => haystack.includes(term))
}

/**
 * Split `raw` into runs of plain and matched text for rendering, matching on
 * the normalised form so highlights line up with what search actually found.
 */
export function highlightSegments(raw, query) {
    const text = String(raw ?? '')
    const terms = queryTerms(query)
    if (!text || !terms.length) return [{ text, hit: false }]

    const { text: norm, map } = normalizeWithMap(text)
    const ranges = []
    const mark = (needle) => {
        let from = 0
        while (from <= norm.length) {
            const at = norm.indexOf(needle, from)
            if (at === -1) break
            ranges.push([map[at], map[at + needle.length - 1] + 1])
            from = at + needle.length
        }
    }

    // The whole phrase first, as one span — so "می کند" lights up "می‌کند"
    // including its half-space. Then the individual words, except that in a
    // multi-word query a 1–2 letter word (می، و، to, of…) is only shown as
    // part of the phrase: on its own it would light up half the paragraph.
    const phrase = terms.length > 1 ? normalize(query) : null
    if (phrase) mark(phrase)
    for (const term of terms) {
        if (phrase && term.length <= 2) continue
        mark(term)
    }
    if (!ranges.length) return [{ text, hit: false }]

    ranges.sort((a, b) => a[0] - b[0])
    const merged = [ranges[0]]
    for (const [start, end] of ranges.slice(1)) {
        const last = merged[merged.length - 1]
        if (start <= last[1]) last[1] = Math.max(last[1], end)
        else merged.push([start, end])
    }

    const parts = []
    let cursor = 0
    for (const [start, end] of merged) {
        if (start > cursor) parts.push({ text: text.slice(cursor, start), hit: false })
        parts.push({ text: text.slice(start, end), hit: true })
        cursor = end
    }
    if (cursor < text.length) parts.push({ text: text.slice(cursor), hit: false })
    return parts
}

/* ---------------------------------------------------------------------------
   Global index — every guide and chapter, in every language
   --------------------------------------------------------------------------- */

const SNIPPET_LENGTH = 170

/**
 * Build one entry per guide (its title/summary, opening the guide at the top)
 * and one per chapter (opening the guide at that chapter), for every locale.
 * `guidesByLocale` is `{ en: [...guides], fa: [...], ps: [...] }`.
 */
export function buildSearchIndex(guidesByLocale) {
    const entries = []

    for (const [locale, guides] of Object.entries(guidesByLocale)) {
        for (const guide of guides) {
            const guideTitleNorm = normalize(guide.title)

            const guideSegments = [guide.title, guide.subtitle, guide.summary].filter(Boolean)
            entries.push({
                key: `${locale}:${guide.id}`,
                locale,
                guide,
                chapter: null,
                segments: guideSegments,
                segmentsNorm: guideSegments.map(normalize),
                titleNorm: guideTitleNorm,
                guideTitleNorm,
            })

            for (const chapter of guide.chapters) {
                const segments = chapterSegments(chapter)
                entries.push({
                    key: `${locale}:${guide.id}:${chapter.id}`,
                    locale,
                    guide,
                    chapter,
                    segments,
                    segmentsNorm: segments.map(normalize),
                    titleNorm: normalize(chapter.title),
                    guideTitleNorm,
                })
            }
        }
    }

    for (const entry of entries) entry.haystack = entry.segmentsNorm.join(' ')
    return entries
}

function countOccurrences(haystack, term) {
    let count = 0
    let from = 0
    while (count < 6) {
        const at = haystack.indexOf(term, from)
        if (at === -1) break
        count++
        from = at + term.length
    }
    return count
}

/** Cut a readable window of the best-matching segment around its first hit. */
function snippetFor(entry, terms) {
    let best = -1
    let bestScore = -1
    entry.segmentsNorm.forEach((seg, index) => {
        const score = terms.reduce((sum, term) => sum + (seg.includes(term) ? term.length : 0), 0)
        // Segment 0 is the title, already shown on the result — prefer body text.
        const adjusted = index === 0 ? score - 0.5 : score
        if (adjusted > bestScore) {
            bestScore = adjusted
            best = index
        }
    })
    // Only the title matched: show the first body line as context rather
    // than repeating the title back.
    if (best <= 0) best = entry.segments.length > 1 ? 1 : 0

    const raw = entry.segments[best] ?? entry.segments[0] ?? ''
    if (raw.length <= SNIPPET_LENGTH) return raw

    const { text: norm, map } = normalizeWithMap(raw)
    let firstHit = Infinity
    for (const term of terms) {
        const at = norm.indexOf(term)
        if (at !== -1) firstHit = Math.min(firstHit, map[at])
    }
    if (!Number.isFinite(firstHit)) firstHit = 0

    let start = Math.max(0, firstHit - Math.floor(SNIPPET_LENGTH / 3))
    const end = Math.min(raw.length, start + SNIPPET_LENGTH)
    start = Math.max(0, end - SNIPPET_LENGTH)

    // Don't start or end mid-word.
    let from = start
    if (from > 0) {
        const space = raw.indexOf(' ', from)
        if (space !== -1 && space - from < 20) from = space + 1
    }
    let to = end
    if (to < raw.length) {
        const space = raw.lastIndexOf(' ', to)
        if (space > from && to - space < 20) to = space
    }

    return `${from > 0 ? '… ' : ''}${raw.slice(from, to).trim()}${to < raw.length ? ' …' : ''}`
}

/**
 * Rank every entry against `query`. Every term must match somewhere in the
 * entry; title hits, whole-phrase hits and the preferred locale rank higher.
 * Returns the top `limit` items plus the true total, so the UI can say
 * "showing 60 of 140" instead of pretending 60 is everything.
 */
export function searchIndex(index, query, { locales = null, preferLocale = null, limit = 60 } = {}) {
    const terms = queryTerms(query)
    if (!terms.length) return { items: [], total: 0, guides: 0 }
    const phrase = normalize(query)

    const results = []
    for (const entry of index) {
        if (locales && !locales.includes(entry.locale)) continue
        if (!terms.every((term) => entry.haystack.includes(term))) continue

        let score = 0
        for (const term of terms) {
            if (entry.titleNorm.includes(term)) score += 12
            if (entry.guideTitleNorm.includes(term)) score += 4
            score += countOccurrences(entry.haystack, term)
        }
        if (terms.length > 1) {
            if (entry.titleNorm.includes(phrase)) score += 25
            else if (entry.haystack.includes(phrase)) score += 10
        }
        if (entry.titleNorm === phrase) score += 30
        if (!entry.chapter) score += 3
        if (preferLocale && entry.locale === preferLocale) score += 2

        results.push({ entry, score })
    }

    results.sort((a, b) => b.score - a.score)

    return {
        total: results.length,
        guides: new Set(results.map(({ entry }) => `${entry.locale}:${entry.guide.id}`)).size,
        items: results.slice(0, limit).map(({ entry, score }) => ({
            key: entry.key,
            locale: entry.locale,
            guide: entry.guide,
            chapter: entry.chapter,
            score,
            snippet: snippetFor(entry, terms),
        })),
    }
}
