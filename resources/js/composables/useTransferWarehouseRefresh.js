import axios from 'axios'
import { pickDefaultVariant } from '@/composables/useVariantLine'

/**
 * Re-point the filled lines of an item transfer at a new source warehouse.
 *
 * Each line carries a copy of its item as it looked in the warehouse it was
 * picked from — batches, per-variant stock, cost. Changing the source warehouse
 * without refreshing them leaves every line showing the old warehouse's figures
 * and offering its batches, so the lines are re-read from the new warehouse:
 * the variant is kept, the batch and expiry (which belong to the old warehouse)
 * are cleared, and a line whose item has no stock in the new warehouse is
 * emptied and reported.
 *
 * @returns {Promise<{ refreshed: number, removed: string[] }>}
 */
export async function refreshRowsForWarehouse({ rows, warehouseId, createEmptyRow, reprice }) {
  const filled = rows.filter((row) => row.item_id)
  const removed = []
  let refreshed = 0

  if (!filled.length || !warehouseId) return { refreshed, removed }

  await Promise.all(filled.map(async (row) => {
    let option = null

    try {
      const { data } = await axios.get(route('search.items-list'), {
        params: {
          warehouse_id: warehouseId,
          search: row.selected_item?.code || row.selected_item?.name || '',
          limit: 25,
        },
      })
      option = (data?.data || []).find((item) => item.id === row.item_id) || null
    } catch (error) {
      option = null
    }

    if (!option) {
      removed.push(row.selected_item?.name || '')
      Object.assign(row, createEmptyRow())
      return
    }

    row.selected_item = option
    row.item_variants = option.item_variants || []
    row.selected_variant = row.item_variants.find((variant) => variant.id === row.variant_id)
      || pickDefaultVariant(row.item_variants)
    row.variant_id = row.selected_variant?.id || null
    row.selected_batch = null
    row.batch = ''
    row.expire_date = ''
    reprice(row)
    refreshed += 1
  }))

  return { refreshed, removed }
}
