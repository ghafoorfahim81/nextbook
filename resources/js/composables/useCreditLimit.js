// Centralised credit-limit maths + colour coding so the customer/supplier show
// pages and the sale/purchase forms all behave identically.
//
// A "ledger" here is a customer or supplier object carrying:
//   credit_limit, credit_limit_enabled, credit_terms ('strict'|'warning'|'flexible'),
//   type ('customer'|'supplier') and statement.{receivable_amount,payable_amount}.

const toNum = (v) => {
    const n = Number(v)
    return Number.isFinite(n) ? n : 0
}

// How much the party currently owes on the relevant side:
//  - customer: what THEY owe you  → statement.receivable_amount
//  - supplier: what YOU owe them  → statement.payable_amount
export function currentUsage(ledger) {
    if (!ledger) return 0
    const st = ledger.statement || {}
    return ledger.type === 'supplier'
        ? toNum(st.payable_amount)
        : toNum(st.receivable_amount)
}

// Traffic-light level from remaining credit as a share of the limit.
//   >50% → green, <50% → yellow, exceeded or <10% → red
export function creditLevel(available, limit) {
    if (limit <= 0) return 'green'
    if (available <= 0) return 'red'
    const pct = (available / limit) * 100
    if (pct < 10) return 'red'
    if (pct < 50) return 'yellow'
    return 'green'
}

export function levelClasses(level) {
    switch (level) {
        case 'red':
            return {
                text: 'text-red-600 dark:text-red-400',
                badge: 'border-red-500/40 bg-red-500/10 text-red-600 dark:text-red-400',
                card: 'border-red-500/40 bg-red-500/5',
                dot: '🔴',
            }
        case 'yellow':
            return {
                text: 'text-amber-600 dark:text-amber-400',
                badge: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
                card: 'border-amber-500/40 bg-amber-500/5',
                dot: '🟡',
            }
        default:
            return {
                text: 'text-green-600 dark:text-green-400',
                badge: 'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400',
                card: 'border-green-500/30 bg-green-500/5',
                dot: '🟢',
            }
    }
}

/**
 * Build a full credit summary for a ledger.
 *
 * @param {object|null} ledger        customer/supplier object (see file header)
 * @param {number}      pendingAmount order/bill total (base currency) being entered, if any
 */
export function getCreditSummary(ledger, pendingAmount = 0) {
    const enabled = Boolean(ledger && ledger.credit_limit_enabled) && toNum(ledger?.credit_limit) > 0
    const limit = toNum(ledger?.credit_limit)
    const used = currentUsage(ledger)
    const available = limit - used

    const pending = Math.max(0, toNum(pendingAmount))
    const projectedUsed = used + pending
    const projectedAvailable = limit - projectedUsed
    const exceeded = pending > 0 && projectedAvailable < 0
    const overBy = exceeded ? Math.abs(projectedAvailable) : 0

    const terms = ledger?.credit_terms || 'warning'
    const level = creditLevel(available, limit)

    return {
        enabled,
        limit,
        used,
        available,
        availablePct: limit > 0 ? (available / limit) * 100 : 0,
        pending,
        projectedUsed,
        projectedAvailable,
        exceeded,
        overBy,
        terms,
        level,
        classes: levelClasses(level),
        // A strict term with an exceeding order should block submission.
        blockSubmit: enabled && terms === 'strict' && exceeded,
        // A warning term with an exceeding order should warn but still allow submit.
        showWarning: enabled && terms === 'warning' && exceeded,
    }
}

export function useCreditLimit() {
    return { getCreditSummary, currentUsage, creditLevel, levelClasses }
}
