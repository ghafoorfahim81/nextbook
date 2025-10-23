<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { ref, computed } from 'vue'
import { Button } from '@/Components/ui/button'
import NextInput from '@/Components/next/NextInput.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import { useForm } from '@inertiajs/vue3'
import FloatingLabel from "@/Components/next/FloatingLabel.vue";
import NextSelect from "@/Components/next/NextSelect.vue";
import NextDatePicker from '@/Components/next/NextDatePicker.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    item: { type: Object, required: true },
    stores: { type: [Array, Object], required: true },
    unitMeasures: { type: [Array, Object], required: true },
    categories: { type: [Array, Object], required: true },
    brands: { type: [Array, Object], required: true },
})

const { t } = useI18n()
const stores = computed(() => props.stores?.data ?? props.stores ?? [])
const unitMeasures = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])
const categories = computed(() => props.categories?.data ?? props.categories ?? [])
const brands = computed(() => props.brands?.data ?? props.brands ?? [])
console.log('props.item.data', props.item.data)
const form = useForm({
    ...props.item.data,
    selected_unit_measure: props.item.data.unitMeasure,
    selected_category: props.item.data.category,
    selected_brand: props.item.data.brand,
    photo: null,
    openings: props.item.data.openings?.length
        ? props.item.data.openings.map(o => ({
            batch: o.batch,
            expire_date: o.expire_date,
            quantity: o.quantity,
            store_id: o.store_id,
            selected_store: o.store
        }))
        : [{ batch: '', expire_date: '', quantity: '', store_id: null, selected_store: null, store: null }],
})


// File handler
const onPhotoChange = (e) => {
    form.photo = e.target.files?.[0] ?? null
}

// Rows
const addRow = (index) => {
    if (index === form.openings.length - 1) {
        form.openings.push({ batch: '', expire_date: '', quantity: '', store_id: null, selected_store: null, store: null })
    }
}
const removeRow = (idx) => {
    if (form.openings.length > 1) form.openings.splice(idx, 1)
}

// Normalize numbers before submit
const normalize = () => {
    const toNum = v => (v === '' || v === null ? null : Number(v))
    form.minimum_stock = toNum(form.minimum_stock)
    form.maximum_stock = toNum(form.maximum_stock)
    form.purchase_price = toNum(form.purchase_price)
    form.cost = toNum(form.cost)
    form.mrp_rate = toNum(form.mrp_rate)
    form.rate_a = toNum(form.rate_a)
    form.rate_b = toNum(form.rate_b)
    form.rate_c = toNum(form.rate_c)
    form.opening = form.openings.map(o => ({
        ...o,
        quantity: toNum(o.quantity),
        expire_date: o.expire_date || null,
        batch: o.batch || null,
        store_id: o.store_id || null,
    }))
}

const handleSubmit = () => {
    normalize()
    form.patch(route('items.update', form.id), {
        onSuccess: () => {
            form.reset()
        },
    })
}
const handleSelectChange = (field, value) => {
    form[field] = value;
};

const handleOpeningSelectChange = (index, value) => {
    form.openings[index].selected_store = value;
    form.openings[index].store_id = value ? value.id : null;
};

</script>
<template>
    <AppLayout :title="t('general.edit', { name: t('item.item') })">
        <form @submit.prevent="handleSubmit" class="space-y-6">
            <div class="grid grid-cols-3 gap-x-2 gap-y-5">
                <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" :placeholder="t('general.enter', { text: t('general.name') })" />
                <NextInput :label="t('admin.currency.code')" v-model="form.code" :error="form.errors?.code" :placeholder="t('general.enter', { text: t('admin.currency.code') })" />
                <NextInput :label="t('item.generic_name')" v-model="form.generic_name" :error="form.errors?.generic_name" :placeholder="t('general.enter', { text: t('item.generic_name') })" />
                <NextInput :label="t('item.packing')" v-model="form.packing" :error="form.errors?.packing" :placeholder="t('general.enter', { text: t('item.packing') })" />
                <NextInput :label="t('general.colors')" v-model="form.colors" :error="form.errors?.colors" :placeholder="t('general.enter', { text: t('general.colors') })" />
                <NextInput :label="t('item.size')" v-model="form.size" :error="form.errors?.size" :placeholder="t('general.enter', { text: t('item.size') })" />

                <!-- Photo -->

                <NextInput :label="t('item.photo')" type="file"  @input="onPhotoChange" :error="form.errors?.photo" :placeholder="t('general.enter', { text: t('item.photo') })" />

                <NextSelect
                    v-model="form.selected_unit_measure"
                    :options="unitMeasures"
                    @update:modelValue="(value) => handleSelectChange('unit_measure_id', value)"
                    label-key="name"
                    value-key="id"
                    id="measure"
                    :floating-text="t('admin.unit_measure.unit_measure')"
                    :searchable="true"
                    resource-type="unit_measures"
                    :search-fields="['name','unit','symbol']"
                    :error="form.errors.unit_measure_id"
                />
                <NextSelect
                    v-model="form.selected_category"
                    @update:modelValue="(value) => handleSelectChange('category_id', value)"
                    :options="categories"
                    label-key="name"
                    value-key="id"
                    id="category"
                    :floating-text="t('admin.category.category')"
                    :searchable="true"
                    resource-type="categories"
                    :search-fields="['name']"
                    :error="form.errors.category_id"
                />
                <NextSelect
                    v-model="form.selected_brand"
                    @update:modelValue="(value) => handleSelectChange('brand_id', value)"
                    :options="brands"
                    label-key="name"
                    value-key="id"
                    id="brand"
                    :floating-text="t('admin.brand.brand')"
                    :searchable="true"
                    resource-type="brands"
                    :search-fields="['name', 'legal_name', 'registration_number', 'email', 'phone', 'website', 'industry', 'type', 'city', 'country']"
                    :error="form.errors.brand_id"
                />
                <NextInput :label="t('item.minimum_stock')" type="number" :placeholder="t('general.enter', { text: t('item.minimum_stock') })" v-model="form.minimum_stock" :error="form.errors?.minimum_stock" />
                <NextInput :label="t('item.maximum_stock')" type="number" :placeholder="t('general.enter', { text: t('item.maximum_stock') })" v-model="form.maximum_stock" :error="form.errors?.maximum_stock" />
                <NextInput :label="t('item.purchase_price')" type="number" :placeholder="t('general.enter', { text: t('item.purchase_price') })" v-model="form.purchase_price" :error="form.errors?.purchase_price" />
                <NextInput :label="t('item.cost')" type="number" v-model="form.cost" :error="form.errors?.cost" />
                <NextInput :label="t('item.mrp_rate')" type="number" :placeholder="t('general.enter', { text: t('item.mrp_rate') })" v-model="form.mrp_rate" :error="form.errors?.mrp_rate" />
                <NextInput :label="t('item.rate_a')" type="number" :placeholder="t('general.enter', { text: t('item.rate_a') })" v-model="form.rate_a" :error="form.errors?.rate_a" />
                <NextInput :label="t('item.rate_b')" type="number" :placeholder="t('general.enter', { text: t('item.rate_b') })" v-model="form.rate_b" :error="form.errors?.rate_b" />
                <NextInput :label="t('item.rate_c')" type="number" :placeholder="t('general.enter', { text: t('item.rate_c') })" v-model="form.rate_c" :error="form.errors?.rate_c" />
                <NextInput :label="t('item.barcode')" v-model="form.barcode" :placeholder="t('general.enter', { text: t('item.barcode') })" :error="form.errors?.barcode" />
                <NextInput :label="t('item.rack_no')" v-model="form.rack_no" :placeholder="t('general.enter', { text: t('item.rack_no') })" :error="form.errors?.rack_no" />
                <NextInput :label="t('item.fast_search')" v-model="form.fast_search" :placeholder="t('general.enter', { text: t('item.fast_search') })" :error="form.errors?.fast_search" />
            </div>

            <div class="pt-2">
                <span class="font-bold">{{ t('item.openings') }}</span>
                <separator> </separator>
                <div
                    v-for="(opening, index) in form.openings"
                    :key="index"
                    class="mt-3 grid grid-cols-4 gap-x-2 gap-y-5 items-start"
                >
                    <NextInput :label="t('item.batch')" @click="addRow(index)" v-model="opening.batch" :error="form.errors?.[`openings.${index}.batch`]" />
                    <NextDatePicker v-model="opening.expire_date" :error="form.errors?.[`openings.${index}.expire_date`]" :placeholder="t('general.enter', { text: t('item.expire_date') })" />
                    <NextInput :label="t('general.quantity')" type="number" v-model="opening.quantity" :error="form.errors?.[`openings.${index}.quantity`]" />
                    <NextSelect
                        v-model="opening.selected_store"
                        @update:modelValue="(value) => handleOpeningSelectChange(index, value)"
                        :options="stores"
                        label-key="name"
                        value-key="id"
                        id="store"
                        :floating-text="t('admin.store.store')"
                        :error="form.errors[`openings.${index}.store_id`]"
                        :searchable="true"
                        resource-type="stores"
                        :search-fields="['name', 'address']"
                    />
                </div>
            </div>
            <progress v-if="form.progress" :value="form.progress.percentage" max="100">
            {{ form.progress.percentage }}%
            </progress>
            <div class="pt-4">
                <Button type="submit" class="bg-blue-500 text-white">{{ t('general.update') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
