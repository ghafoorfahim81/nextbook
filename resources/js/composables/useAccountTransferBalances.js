import { computed, toRef } from 'vue'

function parseNumeric(value) {
  if (value === null || value === undefined || value === '') return 0
  const parsed = parseFloat(String(value).replace(/,/g, ''))
  return Number.isNaN(parsed) ? 0 : parsed
}

function parseBalanceDisplay(raw) {
  if (raw === null || raw === undefined || raw === '') return null

  if (typeof raw === 'number') {
    if (raw === 0) return { amount: 0, nature: 'dr' }
    return { amount: Math.abs(raw), nature: raw >= 0 ? 'dr' : 'cr' }
  }

  const str = String(raw).trim()
  const match = str.match(/^([\d,.]+)\s*(dr|cr)?$/i)

  if (!match) return null

  const amount = parseFloat(match[1].replace(/,/g, ''))
  if (Number.isNaN(amount)) return null

  return {
    amount,
    nature: (match[2] || 'dr').toLowerCase(),
  }
}

function getRealBalance(account) {
  if (!account) {
    return { amount: 0, nature: 'dr' }
  }

  const debit = parseNumeric(account.total_debit)
  const credit = parseNumeric(account.total_credit)

  if (debit !== 0 || credit !== 0) {
    const signed = debit - credit
    if (Math.abs(signed) < 0.000001) {
      return { amount: 0, nature: 'dr' }
    }

    return {
      amount: Math.abs(signed),
      nature: signed >= 0 ? 'dr' : 'cr',
    }
  }

  const amount = Math.abs(parseNumeric(account.balance_amount))
  const nature = String(account.balance_nature || 'dr').toLowerCase()

  if (amount > 0) {
    return { amount, nature }
  }

  const fromDisplay = parseBalanceDisplay(account.balance_with_nature)
    ?? parseBalanceDisplay(account.balance)

  if (fromDisplay) {
    return fromDisplay
  }

  return { amount: 0, nature: 'dr' }
}

function toSignedBalance({ amount, nature }) {
  return nature === 'cr' ? -amount : amount
}

function fromSignedBalance(signed) {
  if (Math.abs(signed) < 0.000001) {
    return { amount: 0, nature: 'dr' }
  }

  return {
    amount: Math.abs(signed),
    nature: signed >= 0 ? 'dr' : 'cr',
  }
}

export function formatRealBalance({ amount, nature }) {
  if (amount < 0.000001) {
    return '0'
  }

  const formatted = amount.toLocaleString(undefined, { maximumFractionDigits: 2 })
  return `${formatted} ${String(nature || 'dr').toLowerCase()}`
}

export function projectRealBalance(account, delta) {
  const current = getRealBalance(account)
  const projected = fromSignedBalance(toSignedBalance(current) + delta)
  return formatRealBalance(projected)
}

export function useAccountTransferBalances(form, bankAccounts) {
  const amount = toRef(form, 'amount')
  const fromAccountId = toRef(form, 'from_account_id')
  const toAccountId = toRef(form, 'to_account_id')
  const selectedFromAccount = toRef(form, 'selected_from_account')
  const selectedToAccount = toRef(form, 'selected_to_account')

  function resolveAccount(accountId, selectedAccount) {
    if (!accountId && !selectedAccount) return null

    const id = accountId || selectedAccount?.id
    const fromList = bankAccounts.value.find((account) => account.id === id)

    if (fromList) {
      return fromList
    }

    return selectedAccount
  }

  const transferAmount = computed(() => parseNumeric(amount.value))

  const fromAccountBalanceText = computed(() => {
    const account = resolveAccount(fromAccountId.value, selectedFromAccount.value)
    if (!account) return ''

    return projectRealBalance(account, -transferAmount.value)
  })

  const toAccountBalanceText = computed(() => {
    const account = resolveAccount(toAccountId.value, selectedToAccount.value)
    if (!account) return ''

    return projectRealBalance(account, transferAmount.value)
  })

  return { fromAccountBalanceText, toAccountBalanceText }
}
