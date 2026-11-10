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

  // With no sale price anywhere, the proposal is built on what the chosen
  // variant's own stock cost. The item's average is blended across every
  // variant, so it only stands in when the variant has no average of its own
  // (or the item has no variants to choose between).
  const fromMargin = () => {
    const cost = positive(variant?.avg_cost) ?? positive(item?.avg_cost)
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
  const variants = row.item_variants?.length ? row.item_variants : (row.selected_item?.item_variants || [])
  const variant = row.selected_variant

  // On an item with several variants, one that has no stock of its own recorded
  // must not fall back to the item's total — that total is the other variants'
  // stock. Only the default variant inherits what carries no variant at all
  // (stock from before variants existed): the total less every variant's share.
  if (!row.selected_batch && variant && variants.length > 1 && !variant.has_stock) {
    if (!variant.is_default) return 0

    const itemTotal = Number(row.selected_item?.on_hand) || 0
    const recorded = variants.reduce((sum, v) => sum + (v.has_stock ? Number(v.on_hand) || 0 : 0), 0)

    return Math.max(0, itemTotal - recorded)
  }

  const variantOnHand = (!row.selected_batch && variant?.has_stock)
    ? variant.on_hand
    : null
  const fallback = row?.selected_batch?.on_hand ?? row.selected_item?.on_hand
  const value = variantOnHand ?? fallback
  return value === undefined || value === null ? null : Number(value)
}

/**
 * One variant's own stock figure in the selected warehouse, or null when the
 * item's figure is the right one to show.
 *
 * `item_variants` comes from the warehouse-aware search, so `has_stock` means
 * "has stock in THIS warehouse". On an item with several variants, a variant
 * with nothing here must read zero rather than borrow the item's total — that
 * total is the other variants' stock. Only the default variant inherits what
 * carries no variant at all (stock from before variants existed).
 *
 * For forms that keep the variant under a different key than
 * resolveVariantOnHand expects, and want a reserved figure by the same rule.
 *
 * @param {'on_hand'|'reserved_out'} field
 */
export function variantStockFigure(row, variant, field = 'on_hand') {
  if (!variant) return null
  if (variant.has_stock) return Number(variant[field]) || 0

  const variants = row?.item_variants?.length ? row.item_variants : (row?.selected_item?.item_variants || [])
  if (variants.length <= 1) return null
  if (!variant.is_default) return 0

  const itemTotal = Number(row?.selected_item?.[field]) || 0
  const recorded = variants.reduce((sum, v) => sum + (v.has_stock ? Number(v[field]) || 0 : 0), 0)

  return Math.max(0, itemTotal - recorded)
}

/**
 * The expiry dates a sale line may pick: only the ones on the shelf.
 *
 * `expiry_options` (warehouse-aware search) lists every expiry date in stock
 * per variant and batch, nearest first. A row sees its own variant's dates,
 * narrowed to the chosen batch when there is one; the same date under several
 * batches is one option with the stock added up. Stock from before variants
 * existed carries no variant and counts for the default one.
 *
 * `keep` is a date the row already holds (a saved draft): it stays selectable
 * even when the shelf no longer shows it, so opening a document never blanks it.
 */
export function expiryOptionsForRow(row, variant, keep = '') {
  const item = row?.selected_item
  const lots = Array.isArray(item?.expiry_options) ? item.expiry_options : []
  const variants = row?.item_variants?.length ? row.item_variants : (item?.item_variants || [])
  const defaultId = item?.default_variant_id ?? null
  const variantId = variant?.id ?? defaultId
  const batch = (row?.batch || '').toString().trim()
  const merged = new Map()

  for (const lot of lots) {
    if (variants.length > 1 && lot.variant_id !== variantId && !(!lot.variant_id && variantId === defaultId)) continue
    if (batch && (lot.batch || '') !== batch) continue

    const entry = merged.get(lot.expire_date) || { expire_date: lot.expire_date, on_hand: 0, reserved_out: 0, available: 0 }
    entry.on_hand += Number(lot.on_hand) || 0
    entry.reserved_out += Number(lot.reserved_out) || 0
    entry.available += Number(lot.available) || 0
    merged.set(lot.expire_date, entry)
  }

  const options = [...merged.values()].map((o) => ({ ...o, label: `${o.expire_date} (${o.on_hand})` }))

  if (keep && !merged.has(keep)) {
    options.unshift({ expire_date: keep, on_hand: null, reserved_out: null, available: null, label: keep })
  }

  return options
}

/**
 * What the stock on a line is worth per base unit.
 *
 * Mirrors SaleController::resolveLineBaseCost so the below-cost warning the
 * operator sees is measured against the same figure the posting will actually
 * relieve inventory at. A variant's own average wins; a zero means the variant
 * has never carried one, and pricing goods at nothing would report the whole
 * sale as profit, so it falls back to the item.
 */
export function resolveVariantAverageCost(item, variant) {
  return positive(variant?.avg_cost) ?? positive(item?.avg_cost) ?? 0
}

/**
 * The batches a row may pick from: only the selected variant's own.
 *
 * The item's merged `batches` list adds a batch's stock up across variants, so a
 * white phone and an orange phone from the same lot would each show the combined
 * figure. `variant_batches` keeps them apart. Stock recorded before variants
 * existed has no variant and counts for the default one. Payloads without
 * `variant_batches` (older cached responses) fall back to the merged list.
 */
export function batchOptionsForRow(row) {
  const item = row?.selected_item
  const split = item?.variant_batches

  if (!Array.isArray(split)) return item?.batches || []

  const variantId = row.selected_variant?.id ?? item.default_variant_id ?? null
  const defaultId = item.default_variant_id ?? null

  return split
    .filter((b) => b.batch && (b.variant_id === variantId || (!b.variant_id && variantId === defaultId)))
    .map(({ batch, expire_date, on_hand, reserved_out, available, avg_cost }) => ({
      batch, expire_date, on_hand, reserved_out, available, avg_cost,
    }))
}
