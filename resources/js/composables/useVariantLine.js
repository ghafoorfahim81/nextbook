/**
 * Shared line-level variant logic for every stock-moving form (purchase,
 * sale, transfer, returns, orders, quotations, adjustments).
 *
 * Each item option from the warehouse-aware search (SearchController
 * `item_variants`) carries: { id, sku, barcode, display_name, is_default,
 * attributes, avg_cost, purchase_price, on_hand, reserved_out, available,
 * has_stock }. These helpers keep every form's row shape and behaviour
 * consistent instead of re-deriving it per module.
 */

/** The variant a fresh row should start on: the item's own default. */
export function pickDefaultVariant(itemVariants) {
  const list = itemVariants || []
  return list.find((v) => v.is_default) || list[0] || null
}

/**
 * A variant priced on its own wins; otherwise this is the item's own cost —
 * the same figure shown before a variant existed to pick.
 */
export function resolveVariantUnitCost(item, variant, fallbackKey = 'avg_cost') {
  const variantCost = variant?.avg_cost ?? variant?.purchase_price
  if (variantCost !== null && variantCost !== undefined && Number(variantCost) > 0) {
    return Number(variantCost)
  }
  return item?.[fallbackKey] ?? item?.purchase_price ?? item?.avg_cost ?? 0
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
