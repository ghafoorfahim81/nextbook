/**
 * A sale/purchase `rate` is the foreign-to-home multiplier: with AFN as the home
 * currency, USD at 65.9 means 1 USD = 65.9 AFN.
 *
 * Item prices and costs (purchase_price, sale_price, avg_cost) are always stored in
 * the home currency, while a document line's unit_price is in the document's own
 * currency. So pre-filling a line from an item divides by the rate, and reading a
 * line back into an item price multiplies. Multiplying in both directions turns a
 * 12,000 AFN item into a 790,800 "USD" line.
 */

/**
 * Money columns are decimal(18,4), so results are rounded to 4 places. Without this,
 * binary float error leaks into the form inputs the user actually reads — 12,000 × 65.9
 * renders as 790800.0000000001.
 */
const MONEY_PRECISION = 4

const round = (value) => Number(value.toFixed(MONEY_PRECISION))

/** Home currency -> the document's currency (e.g. 12,000 AFN at 65.9 -> 182.0941 USD). */
export const toDocumentCurrency = (homeAmount, rate) => {
    const amount = Number(homeAmount) || 0
    const divisor = Number(rate) || 1

    return round(divisor > 0 ? amount / divisor : amount)
}

/** The document's currency -> home currency (e.g. 50 USD at 65.9 -> 3,295 AFN). */
export const toHomeCurrency = (documentAmount, rate) => {
    const amount = Number(documentAmount) || 0
    const multiplier = Number(rate) || 1

    return round(multiplier > 0 ? amount * multiplier : amount)
}
