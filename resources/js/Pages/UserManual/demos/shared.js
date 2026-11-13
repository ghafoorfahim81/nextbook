/** Small helpers shared by the live demos in the user manual. */

export const toNum = (value) => {
    const n = Number(String(value ?? '').replace(/[,٬]/g, ''))
    return Number.isFinite(n) ? n : 0
}

export const cents = (value) => Math.round((value + Number.EPSILON) * 100) / 100

/** A money amount in the locale the guide is shown in, at two decimals. */
export const money = (amount, locale = 'en') => toNum(amount).toLocaleString(locale, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})

/** The same input styling in every demo; options get the card colours too, so a native list stays readable in dark mode. */
export const FIELD = 'w-full rounded-lg border border-border bg-background px-2 py-1.5 text-sm text-foreground outline-none focus:border-primary focus:ring-1 focus:ring-primary [&>option]:bg-card [&>option]:text-foreground'

export const BUTTON = 'inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-sm text-foreground hover:bg-muted'
export const PRIMARY = 'inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90'
