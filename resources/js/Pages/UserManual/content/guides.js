/**
 * Locale-independent metadata for every module guide: display order, the
 * lucide icon name used on the guide card and the table of contents, and the
 * accent colour that tints the card. Titles, summaries and chapter text live
 * in the per-locale files (./en, ./fa, ./ps).
 *
 * The `id` here must match the `id` of the matching guide object in every
 * locale file. A guide missing from a locale simply falls back to English.
 */
export const GUIDE_META = [
    { id: 'overview', icon: 'Compass', accent: 'violet' },
    { id: 'sales', icon: 'ShoppingCart', accent: 'emerald' },
    { id: 'purchases', icon: 'Truck', accent: 'sky' },
    { id: 'inventory', icon: 'Boxes', accent: 'amber' },
    { id: 'accounting', icon: 'Calculator', accent: 'rose' },
    { id: 'cash', icon: 'ArrowLeftRight', accent: 'teal' },
    { id: 'expenses', icon: 'ReceiptText', accent: 'orange' },
    { id: 'hr', icon: 'Users', accent: 'indigo' },
    { id: 'reports', icon: 'BarChart3', accent: 'cyan' },
    { id: 'administration', icon: 'Building2', accent: 'slate' },
]

export function guideMeta(id) {
    return GUIDE_META.find((g) => g.id === id) || GUIDE_META[0]
}

export const GUIDE_ORDER = GUIDE_META.map((g) => g.id)
