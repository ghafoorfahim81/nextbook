<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/Layout.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextInput from '@/Components/next/NextInput.vue'
import { Button } from '@/Components/ui/button'

const props = defineProps({
    items: { type: Array, required: true },
    unitMeasures: { type: Array, required: true }, // Array of {id, name}
    stores: { type: Array, required: true },       // Array of {id, name}
})

// Initialize form with _key for v-for
const form = useForm({
    items: props.items.map(item => ({
        _key: item.id,             // unique key for v-for
        item_id: item.id,          // important for backend
        name: item.name,
        batch: item.batch,
        store_id: null,            // user selects
        unit_measure_id: item.unit_measure_id, // default to item's unit
        quantity: item.quantity ?? 0,
        expire_date: item.expire_date ?? '',
        cost: item.cost ?? 0,
    }))
})

// Helper for field errors
const fieldError = (idx, field) => form.errors?.[`items.${idx}.${field}`]

// Normalize before submission
const normalize = () => {
    form.items = form.items.map(r => ({
        ...r,
        name: r.name || '',
        batch: r.batch || '',
        quantity: r.quantity === '' ? null : Number(r.quantity),
        cost: r.cost === '' ? null : Number(r.cost),
        unit_measure_id: r.unit_measure_id ?? null,
        store_id: r.store_id ?? null,
        expire_date: r.expire_date || null,
    }))

    // Remove empty rows
    form.items = form.items.filter(r => Object.values({ ...r, _key: undefined }).some(v => v !== '' && v !== null))
}

// Handle form submit
const handleSubmit = () => {
    console.log('this is item',form)
    normalize()
    form.post(route('fast-opening.store'), {
        onSuccess: () => {
            // Success feedback if needed
        }
    })
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
                        <th class="px-3 py-2 w-36">Batch</th>
                        <th class="px-3 py-2 w-28">Quantity</th>
                        <th class="px-3 py-2 w-36">Unit</th>
                        <th class="px-3 py-2 w-44">Expiration Date</th>
                        <th class="px-3 py-2 w-40">Cost</th>
                        <th class="px-3 py-2 w-44">Stores</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(item, index) in form.items" :key="item._key">
                        <!-- Item Name (readonly) -->
                        <td class="px-1 py-2 align-top">
                            <NextInput v-model="item.name" :disabled="true" />
                            <NextInput type="hidden" v-model="item.item_id" />
                        </td>

                        <!-- Code (readonly) -->
                        <td class="px-1 py-2 align-top">
                            <NextInput
                                v-model="item.batch"
                                type="text"
                                :error="fieldError(index, 'batch')"
                            />
                        </td>

                        <!-- Quantity (editable) -->
                        <td class="px-1 py-2 align-top">
                            <NextInput
                                v-model="item.quantity"
                                type="number"
                                inputmode="decimal"
                                :error="fieldError(index, 'quantity')"
                            />
                        </td>

                        <!-- Unit Measure (editable, defaults to item's unit) -->
                        <td class="px-1 py-2 align-top">
                            <NextSelect
                                v-model="item.unit_measure_id"
                                :options="props.unitMeasures.data"
                                label-key="name"
                                value-key="id"
                                :error="fieldError(index, 'unit_measure_id')"
                            />
                        </td>

                        <!-- Expiration Date -->
                        <td class="px-1 py-2 align-top">
                            <NextInput
                                type="date"
                                v-model="item.expire_date"
                                :error="fieldError(index, 'expire_date')"
                            />
                        </td>

                        <!-- Purchase Rate -->
                        <td class="px-1 py-2 align-top">
                            <NextInput
                                type="number"
                                v-model="item.cost"
                                inputmode="decimal"
                                :error="fieldError(index, 'cost')"
                            />
                        </td>

                        <!-- Store Selection -->
                        <td class="px-1 py-2 align-top">
                            <NextSelect
                                v-model="item.store_id"
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

            <!-- Submit -->
            <div class="flex justify-end gap-4">
                <Button type="submit" :disabled="form.processing">Save</Button>
            </div>
        </form>
    </AppLayout>
</template>
