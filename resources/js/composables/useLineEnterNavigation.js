import { nextTick } from 'vue'

/**
 * Enter walks a document line the way a salesperson fills it:
 * item -> variant -> quantity -> price -> the next line's item.
 *
 * Cells opt in with `data-nav="item|variant|qty|price"` and `:data-row="index"`;
 * the table carries `@keydown.enter="onLineEnter"`. A cell with nothing to focus
 * (an item with a single variant shows no picker) is skipped, so the path is
 * never longer than the line needs.
 *
 * Enter keeps doing what the field itself does first — a select still takes its
 * highlighted option — and only then moves on. It never submits the form from
 * inside a line.
 *
 * @param {object} options
 * @param {(row: number) => boolean} options.hasItem   Whether the row holds an item yet.
 * @param {(row: number) => void} options.ensureRow    Make sure this row index exists.
 * @param {() => void} [options.focusBarcode]          Where Enter on the customer goes.
 */
export function useLineEnterNavigation({ hasItem, ensureRow, focusBarcode }) {
  const ORDER = ['item', 'variant', 'qty', 'price']
  // Each Enter starts a new move; an older one still settling gives way to it.
  let move = 0

  // The table element captured at the keypress can itself be replaced while the
  // line re-renders; once it is no longer in the page, look in the page instead.
  const inputIn = (table, row, kind) => (table?.isConnected ? table : document)
    .querySelector(`[data-nav="${kind}"][data-row="${row}"]`)
    ?.querySelector('input:not([disabled])') || null

  // Where Enter leads from (row, kind) as the line stands right now.
  const resolveTarget = (table, row, kind) => {
    for (let i = ORDER.indexOf(kind) + 1; i < ORDER.length; i += 1) {
      const input = inputIn(table, row, ORDER[i])
      if (input) return input
    }

    ensureRow(row + 1)

    return inputIn(table, row + 1, 'item')
  }

  /**
   * Choosing an item re-renders its line — the price, the units and the
   * variants arrive a moment later, and the fields are rebuilt as they do. A
   * single focus() lands on an element that is about to be replaced. So the
   * move is retried for a short while: it waits for the line to be ready, and
   * puts the focus back if the rebuild dropped it. It never takes the focus
   * away from a field the user has since moved to themselves.
   */
  const settle = (table, row, kind) => {
    const mine = ++move
    let landed = null
    let attempts = 16

    const step = () => {
      if (mine !== move) return

      const active = document.activeElement
      const lost = !active || active === document.body
      const ready = kind !== 'item' || (hasItem(row) && inputIn(table, row, 'qty'))
      const holding = landed && landed.isConnected && active === landed

      // Somewhere else by the user's own doing: leave them there.
      if (landed && !holding && !lost) return

      if (ready && !holding) {
        const input = resolveTarget(table, row, kind)
        if (input) {
          input.focus()
          // A number is replaced by typing over it; a select's search box is empty anyway.
          if (!input.classList.contains('vs__search')) input.select?.()
          landed = input
        }
      }

      attempts -= 1
      if (attempts > 0) setTimeout(step, 70)
    }

    setTimeout(step, 60)
  }

  const onLineEnter = (event) => {
    if (event.isComposing || event.shiftKey || event.ctrlKey || event.metaKey || event.altKey) return

    const cell = event.target?.closest?.('[data-nav]')
    if (!cell) return

    // Inside a line Enter moves on; it must not submit the whole form.
    event.preventDefault()

    settle(cell.closest('table'), Number(cell.dataset.row), cell.dataset.nav)
  }

  /** Enter on the customer goes straight to the barcode / item search. */
  const onCustomerEnter = (event) => {
    if (event.isComposing || event.shiftKey || event.ctrlKey || event.metaKey || event.altKey) return
    move += 1
    setTimeout(() => nextTick(() => focusBarcode?.()), 80)
  }

  const hasModifier = (event) => event.isComposing || event.shiftKey || event.ctrlKey || event.metaKey || event.altKey
  const focusable = (cell) => cell?.querySelector('input:not([disabled])') || null

  const focusField = (input) => {
    input.focus()
    if (!input.classList.contains('vs__search')) input.select?.()
  }

  /**
   * Arrow keys move between the fields of the lines table, like a spreadsheet,
   * and never change a value on the way: Up/Down on a number field would
   * otherwise step it by one.
   *
   * Bound on the table in the capture phase, so it runs before the field's own
   * handling. A select keeps the keys it needs: Up/Down while its list is open
   * walk the options, and Left/Right move the caret while something is typed
   * in its search box. Left and Right follow the screen, so they swap in
   * right-to-left languages.
   */
  const onGridArrow = (event) => {
    const { key } = event
    if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(key) || hasModifier(event)) return

    const input = event.target
    if (input?.tagName !== 'INPUT') return

    const cell = input.closest('td')
    const line = cell?.closest('tr')
    const body = line?.parentElement
    if (!cell || !body || body.tagName !== 'TBODY') return

    const vertical = key === 'ArrowUp' || key === 'ArrowDown'
    const isSelect = input.classList.contains('vs__search')
    const isNumber = input.type === 'number'

    if (isSelect) {
      if (vertical && input.closest('.v-select')?.classList.contains('vs--open')) return
      if (!vertical && input.value !== '') return
    } else if (!isNumber && !vertical && input.value !== '') {
      // Plain text: the caret needs Left/Right.
      return
    }

    let target = null

    if (vertical) {
      const lines = [...body.rows]
      const next = lines[lines.indexOf(line) + (key === 'ArrowDown' ? 1 : -1)]
      // The same column; a line that is still empty only offers its item field.
      target = focusable(next?.cells[cell.cellIndex])
        || [...(next?.cells || [])].map(focusable).find(Boolean)
        || null
    } else {
      const rtl = getComputedStyle(body).direction === 'rtl'
      const step = (key === 'ArrowRight') !== rtl ? 1 : -1
      for (let i = cell.cellIndex + step; i >= 0 && i < line.cells.length && !target; i += step) {
        target = focusable(line.cells[i])
      }
    }

    // Even with nowhere to go, a number must not be stepped by the key.
    if (target || (isNumber && vertical)) {
      event.preventDefault()
      event.stopPropagation()
    }

    if (target) focusField(target)
  }

  /* ---------------------------------------------------------------- numbers
   * Guards for every number field of the form (bound on the <form>, capture):
   * no negative figures — not typed, not pasted, not stepped below zero. */
  const isNumberField = (element) => element?.tagName === 'INPUT' && element.type === 'number'

  const onNumberBeforeInput = (event) => {
    if (isNumberField(event.target) && typeof event.data === 'string' && event.data.includes('-')) {
      event.preventDefault()
    }
  }

  const onNumberPaste = (event) => {
    if (isNumberField(event.target) && (event.clipboardData?.getData('text') || '').includes('-')) {
      event.preventDefault()
    }
  }

  // Outside the lines table Up/Down still step a number: stop it at zero.
  const onNumberKeydown = (event) => {
    if (event.key !== 'ArrowDown' || !isNumberField(event.target)) return

    const step = Number(event.target.step) || 1
    if ((Number(event.target.value) || 0) - step < 0) event.preventDefault()
  }

  return { onLineEnter, onCustomerEnter, onGridArrow, onNumberBeforeInput, onNumberPaste, onNumberKeydown }
}
