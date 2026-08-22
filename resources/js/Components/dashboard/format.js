// Number formatting shared by the dashboard tiles, lists and charts, so a figure
// reads the same wherever it appears on the page.

const MONEY = { minimumFractionDigits: 2, maximumFractionDigits: 2 }
const QUANTITY = { minimumFractionDigits: 0, maximumFractionDigits: 2 }
const COUNT = { maximumFractionDigits: 0 }

function options(type) {
  if (type === 'count') return COUNT
  if (type === 'quantity') return QUANTITY
  return MONEY
}

export function formatNumber(value, type = 'money') {
  return Number(value || 0).toLocaleString(undefined, options(type))
}

/**
 * Tile values are set at display sizes, where a fully written seven-figure amount
 * either wraps or gets clipped. Above a million we compact it and let the caller
 * expose the exact figure through the element's title attribute.
 */
export function formatCompact(value, type = 'money') {
  const numeric = Number(value || 0)

  if (Math.abs(numeric) < 1_000_000) {
    return formatNumber(numeric, type)
  }

  return numeric.toLocaleString(undefined, {
    notation: 'compact',
    maximumFractionDigits: 1,
  })
}

export function formatPercent(change) {
  if (change === null || change === undefined || Number.isNaN(Number(change))) {
    return ''
  }

  return `${Math.abs(Number(change)).toLocaleString(undefined, { maximumFractionDigits: 1 })}%`
}
