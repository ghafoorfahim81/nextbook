<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed } from 'vue'
import { Button } from '@/Components/ui/button'
import NextInput from '@/Components/next/NextInput.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import { useForm } from '@inertiajs/vue3'
import { Label } from '@/Components/ui/label'
import NextSelect from '@/Components/next/NextSelect.vue'
import FloatingLabel     from "@/Components/next/FloatingLabel.vue";
// keep props reactive
const props = defineProps({
    branches: { type: [Array, Object], required: true },
    stores: { type: [Array, Object], required: true },
    unitMeasures: { type: [Array, Object], required: true },
    categories: { type: [Array, Object], required: true },
    companies: { type: [Array, Object], required: true },
})

// normalize lists whether they’re paginated or not
const branches = computed(() => props.branches?.data ?? props.branches ?? [])
const stores = computed(() => props.stores?.data ?? props.stores ?? [])
const unitMeasures = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])
const categories = computed(() => props.categories?.data ?? props.categories ?? [])
const companies = computed(() => props.companies?.data ?? props.companies ?? [])

const form = useForm({
    name: '',
    code: '',
    generic_name: '',
    packing: '',
    remark: '',
    branch_id: null,
    store_id: null,
    colors: '',
    size: '',
    barcode: '',
    unit_measure_id: null,
    minimum_stock: '',
    maximum_stock: '',
    purchase_price: '',
    company_id: null,
    category_id: null,
    cost: '',
    mrp_rate: '',
    rate_a: '',
    rate_b: '',
    rate_c: '',
    rack_no: '',
    photo: null, // file
    openings: [
        { batch: '', expire_date: '', quantity: '', store_id: null },
    ],
})

// file handler (no v-model on file inputs)
const onPhotoChange = (e) => {
    const f = e.target.files?.[0]
    form.photo = f ?? null
}

// rows
const addRow = (index) => {
    if (index === form.openings.length - 1) {
        form.openings.push({ batch: '', expire_date: '', quantity: '', store_id: null })
    }
}
const removeRow = (idx) => {
    if (form.openings.length > 1) form.openings.splice(idx, 1)
}

// coerce numbers just before submit (optional but tidy)
const normalize = () => {
    const toNum = (v) => (v === '' || v === null ? null : Number(v))
    form.minimum_stock = toNum(form.minimum_stock)
    form.maximum_stock = toNum(form.maximum_stock)
    form.purchase_price = toNum(form.purchase_price)
    form.cost = toNum(form.cost)
    form.mrp_rate = toNum(form.mrp_rate)
    form.rate_a = toNum(form.rate_a)
    form.rate_b = toNum(form.rate_b)
    form.rate_c = toNum(form.rate_c)
    form.openings = form.openings.map(o => ({
        ...o,
        quantity: toNum(o.quantity),
        expire_date: o.expire_date || null,
        batch: o.batch || null,
        store_id: o.store_id || null,
    }))
}

const handleSubmit = () => {
    normalize()
    form.post(route('items.store'), {
        forceFormData: true, // required to send the file
        onSuccess: () => {
            form.reset()
        },
    })
}
</script>

<template>
    <AppLayout title="Create Item">
        <form @submit.prevent="handleSubmit" class="space-y-6">
            <div class="grid grid-cols-3 gap-x-2 gap-y-5">
                <NextInput label="Name" v-model="form.name" :error="form.errors?.name" placeholder="Name" />
                <NextInput label="Code" v-model="form.code" :error="form.errors?.code" placeholder="Code" />
                <NextInput label="Generic Name" v-model="form.generic_name" :error="form.errors?.generic_name" placeholder="Generic Name" />
                <NextInput label="Packing" v-model="form.packing" :error="form.errors?.packing" placeholder="Packing" />
                <NextInput label="Colors" v-model="form.colors" :error="form.errors?.colors" placeholder="Colors" />
                <NextInput label="Size" v-model="form.size" :error="form.errors?.size" placeholder="Size" />

                <!-- Photo -->

                <NextInput label="Photo" type="file"  @input="onPhotoChange" :error="form.errors?.photo" placeholder="Photo" />

                <NextSelect
                    v-model="form.unit_measure_id"
                    :options="unitMeasures"
                    label-key="name"
                    value-key="id"
                    id="measure"
                    floating-text="Measure"
                    :error="form.errors.unit_measure_id"
                />

                <NextSelect
                    :options="categories"
                    v-model="form.category_id"
                    label-key="name"
                    value-key="id"
                    id="category"
                    floating-text="Category"
                    :error="form.errors.category_id"
                />
                <NextSelect
                    :options="companies"
                    v-model="form.company_id"
                    label-key="name"
                    value-key="id"
                    id="company"
                    floating-text="Company"
                    :error="form.errors.company_id"
                />

                <NextInput label="Minimum Stock" type="number" v-model="form.minimum_stock" :error="form.errors?.minimum_stock" />
                <NextInput label="Maximum Stock" type="number" v-model="form.maximum_stock" :error="form.errors?.maximum_stock" />
                <NextInput label="Purchase Price" type="number" v-model="form.purchase_price" :error="form.errors?.purchase_price" />
                <NextInput label="Cost" type="number" v-model="form.cost" :error="form.errors?.cost" />
                <NextInput label="MRP Rate" type="number" v-model="form.mrp_rate" :error="form.errors?.mrp_rate" />
                <NextInput label="Rate A" type="number" v-model="form.rate_a" :error="form.errors?.rate_a" />
                <NextInput label="Rate B" type="number" v-model="form.rate_b" :error="form.errors?.rate_b" />
                <NextInput label="Rate C" type="number" v-model="form.rate_c" :error="form.errors?.rate_c" />
                <NextInput label="Barcode" v-model="form.barcode" :error="form.errors?.barcode" />
                <NextInput label="Rack No" v-model="form.rack_no" :error="form.errors?.rack_no" />
                <NextInput label="Fast Search" v-model="form.fast_search" :error="form.errors?.fast_search" />
            </div>

            <div class="pt-2">
                <span class="font-bold">Opening</span>
                    <separator> </separator>
                <div
                    v-for="(opening, index) in form.openings"
                    :key="index"
                    class="mt-3 grid grid-cols-4 gap-x-2 gap-y-5 items-start"
                >
                    <NextInput label="Batch" @click="addRow(index)" v-model="opening.batch" :error="form.errors?.[`openings.${index}.batch`]" />
                    <NextInput label="Expire Date" type="date" v-model="opening.expire_date" :error="form.errors?.[`openings.${index}.expire_date`]"/>
                    <NextInput label="Quantity" type="number" v-model="opening.quantity" :error="form.errors?.[`openings.${index}.quantity`]" />

                    <NextSelect
                        v-model="opening.store_id"
                        :options="stores"
                        label-key="name"
                        value-key="id"
                        id="measure"
                        floating-text="Measure"
                        :error="form.errors[`openings.${index}.store_id`]"
                    />
                </div>
            </div>

            <div class="pt-4">
                <Button type="submit" class="bg-blue-500 text-white">Submit</Button>
            </div>
        </form>
    </AppLayout>
</template>
