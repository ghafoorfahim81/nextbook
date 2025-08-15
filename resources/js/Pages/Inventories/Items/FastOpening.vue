<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/Layout.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextInput from '@/Components/next/NextInput.vue'

const props = defineProps({
    items: { type: Array, required: true },
    unitMeasures: { type: Array, required: true },
    stores: { type: Array, required: true },
})

console.log('this is items',props.items)
// Initialize form
const form = useForm({
    items: props.items.map(item => ({
        item_id: item.id,
        name: item.name, // Item name from the passed items
        code: item.code, // Item code from the passed items
        store_id: null,  // Store ID field to select a store
        unit_measure_id: item.unit_measure_id, // Default to item's unit measure
        quantity: item.quantity ?? 0,
        expire_date: item.expire_date ?? '',
        purchase_price: item.purchase_price ?? 0,
    }))
})

const fieldError = (idx, field) => form.errors?.[`items.${idx}.${field}`]

const handleSubmit = () => {
    normalize()
    form.post(route('fast-opening.store'), {
        onSuccess: () => {
            // handle success if needed
        }
    })
}

const normalize = () => {
    form.items = form.items.map(r => ({
        ...r,
        item_id:r.id,
        name: r.name || '',
        code: r.code || '',
        quantity: r.quantity === '' ? null : Number(r.quantity),
        purchase_price: r.purchase_price === '' ? null : Number(r.purchase_price),
        unit_measure_id: r.unit_measure_id ?? null,
        store_id: r.store_id ?? null,
        expire_date: r.expire_date || null,
    }))
    // drop completely empty rows
    form.items = form.items.filter(r => Object.values({ ...r, _key: undefined }).some(v => v !== '' && v !== null))
}
</script>

<template>
    <AppLayout title="Fast Opening">
        <form @submit.prevent="handleSubmit" class="space-y-6">
            <div class="overflow-x-auto">
                <table class="w-full table-fixed min-w-[1000px]">
                    <thead class="sticky top-0 bg-muted/40">
                    <tr class="text-left text-sm">
                        <th class="px-3 py-2 w-40">Item</th>
                        <th class="px-3 py-2 w-36">Code</th>
                        <th class="px-3 py-2 w-28">Quantity</th>
                        <th class="px-3 py-2 w-36">Unit</th>
                        <th class="px-3 py-2 w-44">Expiration Date</th>
                        <th class="px-3 py-2 w-40">Purchase Rate</th>
                        <th class="px-3 py-2 w-44">Stores</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr v-for="(item, index) in form.items" :key="item._key">
                        <!-- Item Name (non-editable) -->
                        <td class="px-1 py-2 align-top">
                            <NextInput v-model="item.name" :disabled="true" />
                            <NextInput type="hidden" v-model="item.item_id" />
                        </td>

                        <!-- Code (non-editable) -->
                        <td class="px-1 py-2 align-top">
                            <NextInput v-model="item.code" :disabled="true" />
                        </td>

                        <!-- Quantity (editable) -->
                        <td class="px-1 py-2 align-top">
                            <NextInput v-model="item.quantity" type="number" inputmode="decimal" :error="fieldError(index, 'quantity')" />
                        </td>

                        <!-- Unit Measure (editable, but defaults to item's measure) -->
                        <td class="px-1 py-2 align-top">
                            <NextSelect
                                v-model="form.items[index].unit_measure_id"
                                :options="props.unitMeasures.data"
                                label-key="name"
                                value-key="id"
                                id="unit_measure"
                                :error="fieldError(index, 'unit_measure_id')"
                                :value="form.items[index].unit_measure_id"
                            />
                        </td>

                        <!-- Expiration Date (editable) -->
                        <td class="px-1 py-2 align-top">
                            <NextInput type="date" v-model="item.expire_date" :error="fieldError(index, 'expire_date')" />
                        </td>

                        <!-- Purchase Rate (editable) -->
                        <td class="px-1 py-2 align-top">
                            <NextInput type="number" v-model="item.purchase_price" inputmode="decimal" :error="fieldError(index, 'purchase_price')" />
                        </td>

                        <!-- Store Selection (editable) -->
                        <td class="px-1 py-2 align-top">
                            <NextSelect
                                v-model="form.items[index].store_id"
                                :options="props.stores.data"
                                label-key="name"
                                value-key="id"
                                :error="fieldError(index, 'store_id')"
                            />
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end gap-4">
                <Button type="submit" :disabled="form.processing">
                    Save
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
