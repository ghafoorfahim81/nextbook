import { ref } from 'vue'
import axios from 'axios'

/**
 * Automatic item discounts, resolved on the sale form.
 *
 * The server decides *which* rules could apply to an item — scope, customer
 * group, date window, and the order they beat each other in — and hands back
 * that shortlist, best first. This composable only does the part that has to
 * follow the salesperson's typing: pick the first rule whose minimum quantity
 * the line reaches, and work out the money. Keeping the selection server-side
 * is what stops the two ever disagreeing.
 */
export function useLineDiscounts() {
    /** item id => shortlist of candidate rules, best first. */
    const rulesByItem = ref({})
    const loading = ref(false)

    /** Fetch the shortlist for any item we have not asked about yet. */
    const load = async (itemIds, { customerId = null, date = null } = {}) => {
        const wanted = [...new Set((itemIds || []).filter(Boolean))]
            .filter((id) => rulesByItem.value[id] === undefined)

        if (!wanted.length) return

        loading.value = true
        try {
            const response = await axios.get(route('discount-rules.for-items'), {
                params: { item_ids: wanted, customer_id: customerId, date },
            })
            rulesByItem.value = { ...rulesByItem.value, ...(response.data?.data || {}) }
        } catch (error) {
            // A discount we could not fetch must never block the sale: the line
            // simply keeps whatever the salesperson typed.
            console.error('Failed to load discount rules', error)
            wanted.forEach((id) => { rulesByItem.value[id] = [] })
        } finally {
            loading.value = false
        }
    }

    /** Drop everything — the customer or the date changed, so the answers did. */
    const reset = () => {
        rulesByItem.value = {}
    }

    const toNumber = (value) => {
        const number = Number(value)
        return Number.isFinite(number) ? number : 0
    }

    /** The rule this line earns at this quantity, or null. */
    const ruleFor = (itemId, quantity) => {
        const candidates = rulesByItem.value[itemId]
        if (!candidates?.length) return null

        const qty = toNumber(quantity)

        return candidates.find(
            (rule) => rule.min_quantity === null || qty >= toNumber(rule.min_quantity),
        ) || null
    }

    /** Mirrors DiscountRule::discountFor() — a discount never exceeds its line. */
    const amountFor = (rule, quantity, unitPrice) => {
        if (!rule) return 0

        const lineTotal = toNumber(quantity) * toNumber(unitPrice)
        if (lineTotal <= 0) return 0

        const raw = rule.discount_type === 'percentage'
            ? lineTotal * (toNumber(rule.value) / 100)
            : toNumber(rule.value)

        return Math.round(Math.max(0, Math.min(raw, lineTotal)) * 10000) / 10000
    }

    /** The rule and the money for a line, in one call. */
    const discountFor = (itemId, quantity, unitPrice) => {
        const rule = ruleFor(itemId, quantity)

        return { rule, amount: amountFor(rule, quantity, unitPrice) }
    }

    return { rulesByItem, loading, load, reset, ruleFor, amountFor, discountFor }
}
