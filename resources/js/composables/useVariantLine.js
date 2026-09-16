/**
 * Shared line-level variant logic for every stock-moving form (purchase,
 * sale, transfer, returns, orders, quotations, adjustments).
 *
 * Each item option from the warehouse-aware search (SearchController
 * `item_variants`) carries: { id, sku, barcode, display_name, is_default,
 * attributes, sale_price, purchase_price, avg_cost, on_hand, reserved_out,
 * available, has_stock }. These helpers keep every form's row shape and
 * behaviour consistent instead of re-deriving it per module.
 */

/** The variant a fresh row should start on: the item's own default. */
export function pickDefaultVariant(itemVariants) {
  const list = itemVariants || []
  return list.find((v) => v.is_default) || list[0] || null
}

const positive = (value) => {
  const number = Number(value)
  return Number.isFinite(number) && number > 0 ? number : null
}

/**
 * What a line should charge, by direction.
 *
 * A sale asks what we sell it for and a purchase what we pay for it; these are
 * different numbers and neither is avg_cost, which is only what the stock on
 * the shelf is worth. Getting that wrong on a sale form invoices the customer
 * at cost, so the direction is a required argument rather than a default.
 *
 * A variant priced on its own wins, then the item's own price. avg_cost is a
 * last resort and appears only on the purchase side, where a cost is at least
 * the right kind of figure; a sale falls back to cost plus the item's margin
 * instead, which is what the item form itself proposes.
 *
 * @param {'sale'|'purchase'} direction
 */
export function resolveVariantUnitPrice(item, variant, direction) {
  if (direction !== 'sale' && direction !== 'purchase') {
    throw new Error(`resolveVariantUnitPrice: direction must be 'sale' or 'purchase', got ${direction}`)
  }

  if (direction === 'purchase') {
    return positive(variant?.purchase_price)
      ?? positive(item?.purchase_price)
      ?? positive(item?.avg_cost)
      ?? 0
  }

  const fromMargin = () => {
    const cost = positive(item?.avg_cost)
    if (cost === null) return null
    const margin = Number(item?.margin_percentage)
    return cost * (1 + (Number.isFinite(margin) ? margin : 0) / 100)
  }

  return positive(variant?.sale_price)
    ?? positive(item?.sale_price)
    ?? positive(fromMargin())
    ?? 0
}

/**
 * Re-price a row from its currently selected item and variant.
 *
 * Switching variant has to move the price with it — that is the entire point of
 * pricing a variant separately — so every form calls this from its item-change
 * AND its variant-change handler. `variant` is passed explicitly because forms
 * disagree on where they keep it (selected_variant vs selected_item_variant).
 *
 * Returns the base (home-currency) price it settled on, for callers that want
 * to convert it into the document's currency themselves.
 *
 * @param {'sale'|'purchase'} direction
 */
export function repriceRow(row, variant, direction) {
  if (!row?.selected_item) return 0

  row.base_unit_price = resolveVariantUnitPrice(row.selected_item, variant, direction)

  return row.base_unit_price
}

/**
 * On-hand for a row: a chosen batch is the most specific figure and wins.
 * Otherwise a variant with stock actually recorded against it shows that;
 * everything else (no variant, or one nothing has moved against yet) falls
 * back to the item's total on-hand.
 */
export function resolveVariantOnHand(row) {
  const variantOnHand = (!row.selected_batch && row.selected_variant?.has_stock)
    ? row.selected_variant.on_hand
    : null
  const fallback = row?.selected_batch?.on_hand ?? row.selected_item?.on_hand
  const value = variantOnHand ?? fallback
  return value === undefined || value === null ? null : Number(value)
}
