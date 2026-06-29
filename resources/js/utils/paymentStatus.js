// Centralized colorization for Payment Status values so the styling stays
// consistent across every module (Sales, Purchases, ledger statements, ...).
//
// The color is keyed off the raw enum value (paid / unpaid / partially_paid),
// not the translated label, so it works in every locale.
export function paymentStatusBadgeClass(value) {
    switch (String(value || '').toLowerCase()) {
        case 'paid':
        case 'completed':
            return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300'
        case 'partially_paid':
        case 'partial':
            return 'border-amber-500/50 bg-amber-500/15 text-amber-700 dark:text-amber-300'
        case 'unpaid':
        case 'pending':
            return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300'
        case 'overdue':
            return 'border-red-700/40 bg-red-700/15 text-red-800 dark:text-red-300'
        case 'cancelled':
        case 'void':
        case 'voided':
            return 'border-border bg-muted text-muted-foreground'
        default:
            return 'border-border bg-muted text-foreground'
    }
}

// Shared badge layout classes used alongside the color classes above.
export const PAYMENT_STATUS_BADGE_BASE =
    'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize'
