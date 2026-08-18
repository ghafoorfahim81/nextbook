// The "Balance Nature Format" preference decides whether a ledger balance reads
// in accounting terms (`3500 DR`) or in plain language (`Owe you 3500`).
//
// The Ledger model already applies it to the `statement.balance` it hands the
// show pages, but LedgerOptionResource — what the receipt/payment/sale forms
// select from — sends the raw number and the bare nature, so those forms have
// to apply the same rule themselves.

// Both customers and suppliers read the same way: a debit balance means the
// party owes you, a credit means you owe them. (The Ledger model spells the two
// types out separately, but the two branches collapse to this.)
export function natureLabel(nature, t) {
    return String(nature || '').toLowerCase() === 'cr'
        ? t('general.owe_to')
        : t('general.owe_you')
}

export function formatLedgerBalance(statement, natureFormat, t) {
    if (!statement) return ''

    const amount = Number(statement.balance ?? 0)
    if (!(amount > 0)) return `${statement.balance ?? 0}`

    return natureFormat === 'without_nature'
        ? `${natureLabel(statement.balance_nature, t)} ${amount}`
        : `${amount} ${String(statement.balance_nature || '').toUpperCase()}`
}
