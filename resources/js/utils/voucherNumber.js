// Voucher numbers are stored as plain English text ("Sale #12", "Transfer #3").
// Translate the known prefix at display time so the column follows the user's language.
const PATTERNS = [
    { re: /^Account Opening Balance (.*)$/i, key: 'account_opening_balance', hash: false },
    { re: /^Opening Balance (.*)$/i, key: 'opening_balance', hash: false },
    { re: /^Sale Return #(.*)$/i, key: 'sale_return', hash: true },
    { re: /^Purchase Return #(.*)$/i, key: 'purchase_return', hash: true },
    { re: /^Sale #(.*)$/i, key: 'sale', hash: true },
    { re: /^Purchase #(.*)$/i, key: 'purchase', hash: true },
    { re: /^Receipt #(.*)$/i, key: 'receipt', hash: true },
    { re: /^Payment #(.*)$/i, key: 'payment', hash: true },
    { re: /^Item Transfer #(.*)$/i, key: 'item_transfer', hash: true },
    { re: /^Transfer #(.*)$/i, key: 'transfer', hash: true },
    { re: /^Adjustment #(.*)$/i, key: 'adjustment', hash: true },
    { re: /^Landed cost #(.*)$/i, key: 'landed_cost', hash: true },
    { re: /^journal #(.*)$/i, key: 'journal', hash: true },
]

export function translateVoucherNumber(value, t) {
    if (typeof value !== 'string' || !value) return value
    const reversal = value.match(/^Reversal of (.*)$/i)
    if (reversal) {
        return `${t('general.voucher_prefix.reversal')} ${translateVoucherNumber(reversal[1], t)}`
    }
    for (const { re, key, hash } of PATTERNS) {
        const m = value.match(re)
        if (m) return `${t(`general.voucher_prefix.${key}`)}${hash ? ' #' : ' '}${m[1]}`
    }
    return value
}
