<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import { useToast } from '@/components/ui/toast/use-toast'

const props = defineProps({
    stores: { type: [Array, Object], required: true },
    unitMeasures: { type: [Array, Object], required: true },
    brands: { type: [Array, Object], required: true },
})


const { toast } = useToast()
const stores        = computed(() => props.stores?.data ?? props.stores ?? [])
const unitMeasures  = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])

const blankRow = () => ({
    name: '',
    code: '',
    measure_id: null,
    purchase_price: '',
    mrp_rate: '',
    batch: '',
    expire_date: '',
    quantity: '',
    store_id: null,
})

const form = useForm({
    items: [blankRow(), blankRow()],
})

const fieldError = (idx, field) => form.errors?.[`items.${idx}.${field}`]

const addRowAfter = (idx) => {
    // only add when user is on the last row (good UX), but feel free to remove this guard
    if (idx === form.items.length - 1) form.items.push(blankRow())
}

const removeRow = (idx) => {
    if (form.items.length <= 1) return
    form.items.splice(idx, 1)
}

const normalize = () => {
        form.items = form.items
            .map(r => ({
                ...r,
                quantity: r.quantity === '' ? null : Number(r.quantity),
                purchase_price: r.purchase_price === '' ? null : Number(r.purchase_price),
                mrp_rate: r.mrp_rate === '' ? null : Number(r.mrp_rate),
                category_id: r.category_id ?? null,
                measure_id: r.measure_id ?? null,
                company_id: r.company_id ?? null,
                store_id: r.store_id ?? null,
                expire_date: r.expire_date || null,
            }))
            // drop completely empty rows
            .filter(r => Object.values({ ...r, _key: undefined }).some(v => v !== '' && v !== null))
}

const handleSubmit = () => {
    normalize()

    // ensure items is a clean array; drop helper keys
    form.transform(data => ({
        items: (data.items ?? []).map(({ _key, ...r }) => r)
    }))

    form.post(route('item.fast.store'), {
        preserveScroll: true,
        // preserveState: true, // optional
        onSuccess: () => {
            toast({
                title: 'Success',
                variant: 'success',
                description: 'The items have been added successfully.',
            });
            form.reset('items')
        },
    })
}

</script>

<template>
    <AppLayout title="Fast Item Entry">
        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="rounded-2xl border bg-card text-card-foreground shadow-sm">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-semibold">Fast Entry</h2>
                    <p class="text-sm text-muted-foreground">Add multiple items quickly. Use the “+” on the last row to append more.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-fixed min-w-[1000px]">
                        <thead class="sticky top-0 bg-muted/40">
                        <tr class="text-left text-sm">
                            <th class="px-1 py-1 w-40">Name</th>
                            <th class="px-1 py-1 w-36">Code</th>
                            <th class="px-1 py-1 w-40">Measure</th>
                            <th class="px-1 py-1 w-28">Qty</th>
                            <th class="px-1 py-1 w-36">Batch</th>
                            <th class="px-1 py-1 w-44">Expiry</th>
                            <th class="px-1 py-1 w-44">Store</th>
                            <th class="px-1 py-1 w-36">MRP</th>
                            <th class="px-1 py-1 w-40">Purchase</th>
                            <th class="px-1 py-1 w-24">Actions</th>
                        </tr>
                        </thead>

                        <tbody>
                        <tr v-for="(item, index) in form.items" :key="item._key" class="border-t">
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    label="Name"
                                    v-model="item.name"
                                    :error="fieldError(index, 'name')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    label="Code"
                                    v-model="item.code"
                                    :error="fieldError(index, 'code')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextSelect
                                    v-model="item.measure_id"
                                    :options="unitMeasures"
                                    label-key="name"
                                    value-key="id"
                                    id="measure"
                                    floating-text="Measure"
                                    :error="fieldError(index, 'measure_id')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    label="Quantity"
                                    type="number"
                                    inputmode="decimal"
                                    v-model="item.quantity"
                                    :error="fieldError(index, 'quantity')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    label="Batch"
                                    v-model="item.batch"
                                    :error="fieldError(index, 'batch')"
                                    @keyup.enter.prevent="addRowAfter(index)"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    label="Expire Date"
                                    type="date"
                                    v-model="item.expire_date"
                                    :error="fieldError(index, 'expire_date')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextSelect
                                    v-model="item.store_id"
                                    :options="stores"
                                    label-key="name"
                                    value-key="id"
                                    append-to-body
                                    floating-text="Store"
                                    :error="fieldError(index, 'measure_id')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    label="MRP Rate"
                                    type="number"
                                    inputmode="decimal"
                                    v-model="item.mrp_rate"
                                    :error="fieldError(index, 'mrp_rate')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    label="Purchase Price"
                                    type="number"
                                    inputmode="decimal"
                                    v-model="item.purchase_price"
                                    :error="fieldError(index, 'purchase_price')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <div class="flex gap-2">
                                    <Button type="button" variant="secondary" size="sm" @click="addRowAfter(index)" :disabled="index !== form.items.length - 1">+</Button>
                                    <Button type="button" variant="destructive" size="sm" @click="removeRow(index)" :disabled="form.items.length === 1">−</Button>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t flex items-center justify-between gap-4">
                    <div class="text-sm text-muted-foreground">
                        Rows: <span class="font-medium text-foreground">{{ form.items.length }}</span>
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" variant="secondary" @click="form.items.push(blankRow())">
                            Add Row
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            Save
                        </Button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
