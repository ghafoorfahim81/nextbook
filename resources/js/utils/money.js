/**
 * Money formatting for settlement screens.
 *
 * Amounts are decimal(19,4) on the server and arrive as strings. Number() on a
 * string like "12000.0000" is safe, but formatting must not re-introduce float
 * noise, so everything goes through a fixed fraction count.
 */

const DISPLAY_PRECISION = 2

/** The afghani sign, U+060B. */
export const AFN_SYMBOL = '؋'

/**
 * Format an amount for display. Trailing zeros are kept so columns line up.
 */
export const formatMoney = (value, precision = DISPLAY_PRECISION) => {
  const amount = Number(value ?? 0)

  if (!Number.isFinite(amount)) return '0.00'

  return amount.toLocaleString(undefined, {
    minimumFractionDigits: precision,
    maximumFractionDigits: precision,
  })
}

/**
 * How a currency is labelled in dense tables.
 *
 * The CODE, not the symbol — deliberately.
 *
 * Poppins is the UI font and it has no glyph for U+060B, so the browser falls
 * back per-character to whatever system font it finds. On Windows that fallback
 * commonly lands on a face whose glyph at that slot reads as a division sign,
 * which is where the "AFN shows as ÷" bug comes from. It is a font-coverage
 * problem, not a data problem: the character is correct, the face rendering it
 * is not.
 *
 * Codes sidestep it entirely and stay unambiguous next to USD and other
 * currencies. Where the symbol itself is wanted, use afghaniSymbol() below,
 * which forces a font that actually contains the glyph.
 */
export const currencyLabel = (code) => (code ? String(code).toUpperCase() : '')

/**
 * The afghani sign wrapped so it renders in a face that has the glyph.
 *
 * `.currency-symbol` (resources/css/app.css) puts the Persian faces the app
 * already ships — Dana and iranyekan, both of which cover Arabic-script
 * currency signs — ahead of Poppins for this span only.
 */
export const afghaniSymbol = () => `<span class="currency-symbol">${AFN_SYMBOL}</span>`

/**
 * Signed FX result to a display pair.
 *
 * Positive is a GAIN on both the receivable and the payable side — the server
 * normalises the sign, so nothing here needs to know which side it is on.
 */
export const forexParts = (value) => {
  const amount = Number(value ?? 0)

  if (Math.abs(amount) < 0.00001) {
    return { kind: 'none', amount: 0 }
  }

  return { kind: amount > 0 ? 'gain' : 'loss', amount: Math.abs(amount) }
}
