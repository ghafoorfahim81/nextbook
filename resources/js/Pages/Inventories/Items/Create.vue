<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed, watch } from 'vue'
import { Button } from '@/Components/ui/button'
import NextInput from '@/Components/next/NextInput.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import { useForm, router } from '@inertiajs/vue3'
import { Label } from '@/Components/ui/label'
import NextSelect from '@/Components/next/NextSelect.vue'
import FloatingLabel     from "@/Components/next/FloatingLabel.vue";
import NextDate from '@/Components/next/NextDatePicker.vue'
import { useI18n } from 'vue-i18n';
const { t } = useI18n()
// keep props reactive
const props = defineProps({
    branches: { type: [Array, Object], required: true },
    stores: { type: [Array, Object], required: true },
    unitMeasures: { type: [Array, Object], required: true },
    categories: { type: [Array, Object], required: true },
    brands: { type: [Array, Object], required: true },
    maxCode: { type: Number, required: true },
    user_preferences: { type: Object, required: true },
})

// normalize lists whether they're paginated or not
const branches = computed(() => props.branches?.data ?? props.branches ?? [])
const stores = computed(() => props.stores?.data ?? props.stores ?? [])
const unitMeasures = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])
const categories = computed(() => props.categories?.data ?? props.categories ?? [])
const brands = computed(() => props.brands?.data ?? props.brands ?? [])

// Format code with leading zeros based on the number
const formatCode = (number) => {
    const num = Number(number);
    if (num < 10) {
        return num.toString().padStart(3, '0'); // 001-009
    } else if (num < 100) {
        return num.toString().padStart(3, '0'); // 010-099
    } else {
        return num.toString(); // 100+
    }
}

const user_preferences = computed(() => props.user_preferences?.data ?? props.user_preferences ?? [])
const visibleFields = computed(() => user_preferences.value.item_management.visible_fields ?? []).value
const specText = computed(() => user_preferences.value.item_management.spec_text ?? '') 

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
    selected_unit_measure: '',
    selected_company: '',
    selected_category: '',
    selected_brand: '',
    minimum_stock: '',
    maximum_stock: '',
    purchase_price: '',
    brand_id: null,
    category_id: null,
    cost: '',
    sale_price: '',
    rate_a: '',
    rate_b: '',
    rate_c: '',
    rack_no: '',
    photo: null, // file
    openings: [
        { batch: '', expire_date: '', quantity: '', store_id: null, selected_store: null },
    ],
})

// By default select the main store if available
watch(() => props.stores?.data ?? props.stores, (stores) => {
    if (stores && stores.length) {
        const mainStore = stores.find(s => s.is_main === true)
        if (mainStore) {
            form.openings[0].selected_store = mainStore
            form.openings[0].store_id = mainStore.id
        }
    }
}, { immediate: true })

watch(() => props.maxCode, (maxCode) => {
    if (maxCode) {
        form.code = formatCode(maxCode)
    }
}, { immediate: true })

// file handler (no v-model on file inputs)
const onPhotoChange = (e) => {
    const f = e.target.files?.[0]
    form.photo = f ?? null
}

// rows
const addRow = (index) => {
    if (index === form.openings.length - 1) {
        form.openings.push({ batch: '', expire_date: '', quantity: '', store_id: null, selected_store: null })
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
    form.sale_price = toNum(form.sale_price)
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

const handleCreate = () => {
    normalize()
    // ensure no leftover transform
    form.transform((d) => d).post(route('items.store'), {
        forceFormData: true,
    })
}

const handleCreateAndNew = () => {
    normalize()
    form
        .transform((data) => ({ ...data, stay: true }))
        .post(route('items.store'), {
            forceFormData: true,
            onSuccess: () => {
                form.reset()
                // reset transform so it doesn't affect other submits
                form.transform((d) => d)
            },
        })
}

const handleCancel = () => {
    router.visit(route('items.index'))
}

const handleSelectChange = (field, value) => {
    form[field] = value;
};

const handleOpeningSelectChange = (index, value) => {
    form.openings[index].selected_store = value;
    form.openings[index].store_id = value ? value: null;
};

</script>

<template>
    <AppLayout :title="t('item.item')">
        <form @submit.prevent="handleCreate">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.create', { name: t('item.item') }) }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                <NextInput  :label="t('general.name')" v-model="form.name" :error="form.errors?.name"  :placeholder="t('general.enter', { text: t('general.name') })" />
                <NextInput v-show="visibleFields.code" :label="t('admin.currency.code')" disabled="true"  v-model="form.code" :error="form.errors?.code" :placeholder="t('general.enter', { text: t('admin.currency.code') })" />
                <NextInput v-show="visibleFields.generic_name" :label="t('item.generic_name')" v-model="form.generic_name" :error="form.errors?.generic_name" :placeholder="t('general.enter', { text: t('item.generic_name') })" />
                <NextInput v-show="visibleFields.packing" :label="t('item.packing')" v-model="form.packing" :error="form.errors?.packing" :placeholder="t('general.enter', { text: t('item.packing') })" />
                <NextInput v-show="visibleFields.colors" packing:label="t('item.colors')" v-model="form.colors" :error="form.errors?.colors" :placeholder="t('general.enter', { text: t('item.colors') })" />
                <NextInput v-show="visibleFields.size" :label="t('item.size')" v-model="form.size" :error="form.errors?.size" :placeholder="t('general.enter', { text: t('item.size') })" />
                <NextSelect
                    v-model="form.selected_unit_measure"
                    :options="unitMeasures"
                    @update:modelValue="(value) => handleSelectChange('unit_measure_id', value)"
                    label-key="name"
                    value-key="id"
                    id="measure_id"
                    :floating-text="t('admin.unit_measure.unit_measure')"
                    :searchable="true"
                    resource-type="unit_measures"
                    :search-fields="['name','unit','symbol']"
                    :error="form.errors.unit_measure_id"
                />

                <NextSelect
                    :options="categories"
                    v-model="form.selected_category"
                    @update:modelValue="(value) => handleSelectChange('category_id', value)"
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
                    :options="brands"
                    v-model="form.selected_brand"
                    @update:modelValue="(value) => handleSelectChange('brand_id', value)"
                    label-key="name"
                    v-show="visibleFields.brand"
                    value-key="id"
                    id="brand"
                    :floating-text="t('admin.brand.brand')"
                    :searchable="true"
                    resource-type="brands"
                    :search-fields="['name', 'legal_name', 'registration_number', 'email', 'phone', 'website', 'industry', 'type', 'city', 'country']"
                    :error="form.errors.brand_id"
                />
                <NextInput v-show="visibleFields.photo" :label="t('item.photo')" type="file"  @input="onPhotoChange" :error="form.errors?.photo" :placeholder="t('general.enter', { text: t('item.photo') })" />

                <NextInput v-show="visibleFields.minimum_stock" :label="t('item.minimum_stock')" type="number" :placeholder="t('general.enter', { text: t('item.minimum_stock') })" v-model="form.minimum_stock" :error="form.errors?.minimum_stock" />
                <NextInput v-show="visibleFields.maximum_stock" :label="t('item.maximum_stock')" type="number" :placeholder="t('general.enter', { text: t('item.maximum_stock') })" v-model="form.maximum_stock" :error="form.errors?.maximum_stock" />
                <NextInput :label="t('item.purchase_price')" type="number" :placeholder="t('general.enter', { text: t('item.purchase_price') })" v-model="form.purchase_price" :error="form.errors?.purchase_price" />
                <NextInput :label="t('item.cost')" type="number" v-model="form.cost" :error="form.errors?.cost" />
                <NextInput :label="t('item.sale_price')" type="number" :placeholder="t('general.enter', { text: t('item.sale_price') })" v-model="form.sale_price" :error="form.errors?.sale_price" />
                <NextInput v-show="visibleFields.rate_a" :label="t('item.rate_a')" type="number" :placeholder="t('general.enter', { text: t('item.rate_a') })" v-model="form.rate_a" :error="form.errors?.rate_a" />
                <NextInput v-show="visibleFields.rate_b" :label="t('item.rate_b')" type="number" :placeholder="t('general.enter', { text: t('item.rate_b') })" v-model="form.rate_b" :error="form.errors?.rate_b" />
                <NextInput v-show="visibleFields.rate_c" :label="t('item.rate_c')" type="number" :placeholder="t('general.enter', { text: t('item.rate_c') })" v-model="form.rate_c" :error="form.errors?.rate_c" />
                <NextInput v-show="visibleFields.barcode" :label="t('item.barcode')" v-model="form.barcode" :placeholder="t('general.enter', { text: t('item.barcode') })" :error="form.errors?.barcode" />
                <NextInput v-show="visibleFields.rack_no" :label="t('item.rack_no')" v-model="form.rack_no" :placeholder="t('general.enter', { text: t('item.rack_no') })" :error="form.errors?.rack_no" />
                    <NextInput v-show="visibleFields.fast_search" :label="t('item.fast_search')" v-model="form.fast_search" :placeholder="t('general.enter', { text: t('item.fast_search') })" :error="form.errors?.fast_search" />
                </div>

                <div class="md:col-span-3 mt-4">
                    <div class="pt-2">
                        <span class="font-bold">{{ t('item.opening') }}</span>
                        <div
                            v-for="(opening, index) in form.openings"
                            :key="index"
                            class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-4 items-start"
                        >
                            <NextInput :label="specText?? t('item.batch')" @click="addRow(index)" v-model="opening.batch" :error="form.errors?.[`openings.${index}.batch`]" />
                            <NextDate v-model="opening.expire_date" :error="form.errors?.[`openings.${index}.expire_date`]" :placeholder="t('general.enter', { text: t('item.expire_date') })" />
                            <NextInput :label="t('item.quantity')" type="number" v-model="opening.quantity" :error="form.errors?.[`openings.${index}.quantity`]" />
                            <NextSelect
                                v-model="opening.selected_store"
                                :options="stores"
                                label-key="name"
                                @update:modelValue="(value) => handleOpeningSelectChange(index, value)"
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
                </div>
            </div>

            <progress v-if="form.progress" :value="form.progress.percentage" max="100">
                {{ form.progress.percentage }}%
            </progress>

            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white">{{ t('general.create') }}</button>
                <button type="button" class="btn btn-primary px-4 py-2 rounded-md bg-primary border text-white" @click="handleCreateAndNew">{{ t('general.create_and_new') }}</button>
                <button type="button" class="btn px-4 py-2 rounded-md border" @click="handleCancel">{{ t('general.cancel') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
