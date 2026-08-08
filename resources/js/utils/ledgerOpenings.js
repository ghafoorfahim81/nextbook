/**
 * A customer or supplier can hold an opening balance in several currencies at
 * once. Each one is stored as its own posted transaction, because
 * `transactions.currency_id` carries a single currency for the whole voucher.
 *
 * The forms render every currency as a row rather than hiding extra currencies
 * behind an "add row" control, so these helpers build that full list and keep
 * its order stable — validation errors come back keyed `openings.<index>.<field>`
 * and must land on the row the user actually typed into.
 */

/** Inertia resource collections arrive as `{ data: [...] }`; plain arrays pass through. */
export const unwrap = (value) => {
    if (Array.isArray(value)) {
        return value
    }

    return value?.data ?? []
}

/**
 * One row per currency, seeded from the openings the ledger already has.
 * The home currency sorts first — it is the one most entries use.
 */
export const buildOpeningRows = (currencies, openings = [], homeCurrency = null) => {
    const list = unwrap(currencies)

    const existing = new Map(
        unwrap(openings)
            .filter((opening) => opening?.currency_id)
            .map((opening) => [opening.currency_id, opening]),
    )

    const ordered = homeCurrency
        ? [...list].sort((a, b) => {
            if (a.id === homeCurrency.id) return -1
            if (b.id === homeCurrency.id) return 1
            return 0
        })
        : list

    return ordered.map((currency) => {
        const opening = existing.get(currency.id)
        const isHome = !!homeCurrency && currency.id === homeCurrency.id

        return {
            currency_id: currency.id,
            // The home currency is 1:1 with itself; its rate input is disabled.
            rate: opening?.rate ?? (isHome ? 1 : currency.exchange_rate ?? 1),
            amount: opening?.amount ?? '',
            remark: opening?.remark ?? '',
        }
    })
}
