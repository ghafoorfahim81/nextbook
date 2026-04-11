const FOCUSABLE_SELECTOR = [
  'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([disabled]):not([readonly])',
  'textarea:not([disabled]):not([readonly])',
  'select:not([disabled])',
  '.vs__search:not([disabled])',
  'input[type="radio"]:not([disabled])',
].join(', ')

const isVisibleElement = (el) => {
  if (!el || typeof el.getClientRects !== 'function') return false
  const rects = el.getClientRects()
  if (!rects || rects.length === 0) return false

  const style = el.ownerDocument?.defaultView?.getComputedStyle?.(el)
  if (!style) return true

  return style.display !== 'none' && style.visibility !== 'hidden'
}

export const shouldAutoFocusElement = (el) => {
  if (!el || typeof el.closest !== 'function') return false

  if (el.hasAttribute?.('autofocus')) {
    return true
  }

  const form = el.closest('form')
  if (!form || typeof form.querySelectorAll !== 'function') return false

  const candidates = Array.from(form.querySelectorAll(FOCUSABLE_SELECTOR))
    .filter((node) => node === el || isVisibleElement(node))
    .filter((node) => !node.disabled)

  if (!candidates.length) return false

  return candidates[0] === el
}
