<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed, watch, ref } from 'vue'
import NextInput from '@/Components/next/NextInput.vue'
import { useForm, router } from '@inertiajs/vue3'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
const { t } = useI18n()
// keep props reactive
const props = defineProps({
    stores: { type: [Array, Object], required: true },
    unitMeasures: { type: [Array, Object], required: true },
    categories: { type: [Array, Object], required: true },
    brands: { type: [Array, Object], required: true },
    sizes: { type: [Array, Object], required: true },
    maxCode: { type: Number, required: true },
    user_preferences: { type: Object, required: true },
    itemTypes: { type: Object, required: true },
    otherCurrentAssetsAccounts:{ type:Object, required: true},
    incomeAccounts:{ type:Object, required: true},
    costAccounts:{ type:Object, required: true},
})

// normalize lists whether they're paginated or not
const stores = computed(() => props.stores?.data ?? props.stores ?? [])
const unitMeasures = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])
const categories = computed(() => props.categories?.data ?? props.categories ?? [])
const brands = computed(() => props.brands?.data ?? props.brands ?? [])
const sizes = computed(() => props.sizes?.data ?? props.sizes ?? [])
const otherCurrentAssetsAccounts = computed(() => props.otherCurrentAssetsAccounts?.data ?? props.otherCurrentAssetsAccounts ?? [])
const incomeAccounts = computed(() => props.incomeAccounts?.data ?? props.incomeAccounts ?? [])
const costAccounts = computed(() => props.costAccounts?.data ?? props.costAccounts ?? [])

const itemTypes = computed(() => props.itemTypes?.data ?? props.itemTypes ?? [])
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
    size_id: null,
    item_type: null,
    barcode: '',
    unit_measure_id: null,
    selected_unit_measure: '',
    selected_company: '',
    selected_category: '',
    selected_brand: '',
    selected_size: '',
    minimum_stock: '',
    maximum_stock: '',
    purchase_price: '',
    brand_id: null,
    category_id: null,
    asset_account_id: null,
    selected_asset_account: null,
    income_account_id: null,
    selected_income_account: null,
    cost_account_id: null,
    selected_cost_account: null,
    cost: '',
    sale_price: '',
    rate_a: '',
    rate_b: '',
    rate_c: '',
    rack_no: '',
    photo: null, // file
    openings: [
        { batch: '', expire_date: '', quantity: 0, store_id: null, selected_store: null },
    ],
})

const findBySlugOrName = (list, want) => {
    const w = String(want || '').trim().toLowerCase()
    if (!w) return null
    return (list || []).find((a) => {
        const slug = String(a?.slug || '').toLowerCase()
        const name = String(a?.name || '').toLowerCase()
        return slug === w || name === w
    }) || null
}

const isInventoryMaterial = (type) => {
    const name = String(type?.name || '').toLowerCase()
    const slug = String(type?.slug || '').toLowerCase()
    // covers variants like "Inventory Material", "inventory-material", etc
    return (
        (slug.includes('inventory') && (slug.includes('material') || slug.includes('stock'))) ||
        (name.includes('inventory') && (name.includes('material') || name.includes('stock')))
    )
}

const defaultItemTypeId = computed(() => {
    const list = itemTypes.value || []
    const inv = list.find(isInventoryMaterial)
    return inv?.id ?? list?.[0]?.id ?? null
})

const defaultIncomeAccountId = computed(() => {
    const list = incomeAccounts.value || []
    const a =
        findBySlugOrName(list, 'income-account') ||
        findBySlugOrName(list, 'income account') ||
        list.find(x => String(x?.name || '').toLowerCase().includes('income account')) ||
        list?.[0] ||
        null
    return a?.id ?? null
})

const defaultCostAccountId = computed(() => {
    const list = costAccounts.value || []
    const a =
        findBySlugOrName(list, 'cost-of-goods-sold') ||
        findBySlugOrName(list, 'cost of goods sold') ||
        findBySlugOrName(list, 'cost-of-good-sold') ||
        findBySlugOrName(list, 'cost of good sold') ||
        list.find(x => String(x?.name || '').toLowerCase().includes('cost of good')) ||
        list.find(x => String(x?.name || '').toLowerCase().includes('cost of goods')) ||
        list?.[0] ||
        null
    return a?.id ?? null
})

const defaultAssetAccountId = computed(() => {
    const list = otherCurrentAssetsAccounts.value || []
    const a =
        findBySlugOrName(list, 'inventory') ||
        findBySlugOrName(list, 'other-current-asset') ||
        list?.[0] ||
        null
    return a?.id ?? null
})

const preferredAssetAccountIdForItemType = computed(() => {
    const type = (itemTypes.value || []).find(t => t?.id === form.item_type) || null
    console.log('this is type', form.item_type);
    const typeName = String(type?.name || '').toLowerCase()
    const typeSlug = String(type?.slug || '').toLowerCase()

    // Best-effort mapping to common GL slugs used elsewhere in backend.
    let wantSlug = null
    if (typeSlug.includes('non') || typeName.includes('non')) wantSlug = 'non-inventory-items'
    else if (typeSlug.includes('raw') || typeName.includes('raw')) wantSlug = 'raw-materials'
    else if (typeSlug.includes('finished') || typeName.includes('finished')) wantSlug = 'finished-goods'
    else if (typeSlug.includes('inventory') || typeName.includes('inventory')) wantSlug = 'inventory-stock'

    const list = otherCurrentAssetsAccounts.value || []
    const match = wantSlug ? findBySlugOrName(list, wantSlug) : null
    return match?.id ?? defaultAssetAccountId.value
})

const settingAssetAccount = ref(false)
const assetAccountUserSet = ref(false)
watch(() => form.asset_account_id, () => {
    if (settingAssetAccount.value) return
    assetAccountUserSet.value = true
})

const setAssetAccountId = (id) => {
    settingAssetAccount.value = true
    form.asset_account_id = id ?? null
    settingAssetAccount.value = false
}

watch(
    [itemTypes, incomeAccounts, costAccounts, otherCurrentAssetsAccounts],
    () => {
        // a) item type default: inventory material
        if (!form.item_type) form.item_type = defaultItemTypeId.value

        // c) income account default: "income account"
        if (!form.income_account_id) form.income_account_id = defaultIncomeAccountId.value

        // d) cost account default: cost of goods sold
        if (!form.cost_account_id) form.cost_account_id = defaultCostAccountId.value

        // b) asset account default: based on item type (inventory -> inventory/other-current-asset)
        if (!form.asset_account_id) setAssetAccountId(preferredAssetAccountIdForItemType.value)
    },
    { immediate: true }
)

watch(
    () => form.item_type,
    () => {
        // b) keep asset account in sync with item type until user chooses manually
        if (assetAccountUserSet.value) return
        setAssetAccountId(preferredAssetAccountIdForItemType.value)
    }
)

let salePriceTimeout = null

watch(
    () => form.sale_price,
    () => {
        if (salePriceTimeout) clearTimeout(salePriceTimeout)
        salePriceTimeout = setTimeout(() => {
            if (form.sale_price && form.purchase_price && form.sale_price < form.purchase_price) {
                toast.error(t('item.low_price_warning'), {
                    description: t('item.sale_price_less_than_purchase_price') || 'Sale price cannot be less than purchase price.',
                    class: 'bg-red-600',
                });
            }
        }, 2000)
    }
)
const submitAction = ref(null)
const createLoading = computed(() => form.processing && submitAction.value === 'create')
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new')

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
        form.openings.push({ batch: '', expire_date: '', quantity: 0, store_id: null, selected_store: null })
    }
}
const addOpeningRow = () => {
    form.openings.push({ batch: '', expire_date: '', quantity: 0, store_id: null, selected_store: null })
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
const handleSubmitAction = (createAndNew = false) => {
    const isCreateAndNew = createAndNew === true;
    submitAction.value = isCreateAndNew ? 'create_and_new' : 'create';
    normalize() 
    // Always show toast on success, regardless of which button is used
    const postOptions = {
        onSuccess: () => {
            toast.success(t('general.success'), {
                description: t('general.create_success', { name: t('item.item') }),
                class: 'bg-green-600',
            });
            if (isCreateAndNew) {
                form.reset();
                if (typeof buildOpenings === 'function') {
                    form.openings = buildOpenings();
                }
                form.transform((d) => d); // Reset transform to identity
            }
        },
        // Any shared callbacks like onError can go here
    };

    const transformFn = isCreateAndNew
        ? (data) => ({ ...data, create_and_new: true, stay: true })
        : (data) => data;

    form
        .transform(transformFn)
        .post(route('items.store'), postOptions);
};

const handleCancel = () => {
    router.visit(route('items.index'))
}

const handleSelectChange = (field, value) => {
    form[field] = value;
    if(field === 'item_type') {
        form.item_type = value;
        form.asset_account_id = preferredAssetAccountIdForItemType.value;
    }
};
const disabled = ref(false);
watch(
    () => form.openings.map(o => [o.selected_store, o.batch, o.expire_date].join('|')).join(';'),
    (newVal, oldVal) => {
        form.openings.forEach((currentOpening, index) => {
            const { selected_store, batch, expire_date } = currentOpening;
            const duplicate = form.openings.some((o, i) =>
                i !== index &&
                o.store_id === selected_store &&
                o.batch === batch &&
                o.expire_date === expire_date
            );
            if (duplicate) {
                disabled.value = true;
                toast.error(t('general.duplicate_found'), {
                    description: t('item.duplicate_store_batch_expiry') || 'This store with the same batch and expiry already exists.',
                    class: 'bg-red-600',
                });
            }
            else{
                disabled.value = false;
            }
        });
    },
    { deep: true }
)
const handleOpeningSelectChange = (index, value) => {
    form.openings[index].selected_store = value;
    form.openings[index].store_id = value ? value : null;
};

</script>

<template>
    <AppLayout :title="t('item.item')">
        <form @submit.prevent="handleSubmitAction">
            <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.create', { name: t('item.item') }) }}
                </div>
                <ModuleHelpButton module="inventory_item" />
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                <NextInput  :label="t('general.name')" v-model="form.name" :error="form.errors?.name"  :placeholder="t('general.enter', { text: t('general.name') })" />
                <NextInput v-show="visibleFields.code" :label="t('admin.currency.code')" disabled="true"  v-model="form.code" :error="form.errors?.code" :placeholder="t('general.enter', { text: t('admin.currency.code') })" />
                <NextInput v-show="visibleFields.generic_name" :label="t('item.generic_name')" v-model="form.generic_name" :error="form.errors?.generic_name" :placeholder="t('general.enter', { text: t('item.generic_name') })" />
                <NextInput v-show="visibleFields.packing" :label="t('item.packing')" v-model="form.packing" :error="form.errors?.packing" :placeholder="t('general.enter', { text: t('item.packing') })" />
                <NextInput v-show="visibleFields.colors" packing:label="t('item.colors')" v-model="form.colors" :error="form.errors?.colors" :placeholder="t('general.enter', { text: t('item.colors') })" />
                <NextSelect
                    v-show="visibleFields.size"
                    v-model="form.selected_size"
                    :options="sizes"
                    @update:modelValue="(value) => handleSelectChange('size_id', value)"
                    label-key="name"
                    value-key="id"
                    id="size_id"
                    :floating-text="t('item.size')"
                    :searchable="true"
                    resource-type="sizes"
                    :search-fields="['name','code']"
                    :error="form.errors.size_id"
                />
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
                    :has-add-button="false"
                />
                <NextSelect
                    v-model="form.item_type"
                    :options="itemTypes"
                    label-key="name"
                    value-key="id"
                    @update:modelValue="(value) => handleSelectChange('item_type', value)"
                    id="item_type"
                    :floating-text="t('item.item_type')"
                />
                <NextInput v-show="visibleFields.sku" :label="t('item.sku')" v-model="form.sku" :error="form.errors?.sku" :placeholder="t('general.enter', { text: t('item.sku') })" />
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
                <NextSelect
                    :options="otherCurrentAssetsAccounts"
                    v-model="form.asset_account_id"
                    label-key="name"
                    value-key="id"
                    id="assets_account"
                    :floating-text="t('item.asset_account')"
                    :searchable="true"
                    resource-type="assets_accounts"
                    :search-fields="['name']"
                    :error="form.errors.asset_account_id"
                />
                <NextSelect
                    :options="incomeAccounts"
                    v-model="form.income_account_id"
                    label-key="name"
                    value-key="id"
                    id="income_account"
                    :floating-text="t('item.income_account')"
                    :searchable="true"
                    resource-type="income_accounts"
                    :search-fields="['name']"
                    :error="form.errors.income_account_id"
                />
                <NextSelect
                    :options="costAccounts"
                    v-model="form.cost_account_id"
                    label-key="name"
                    value-key="id"
                    id="cost_account"
                    :floating-text="t('item.cost_account')"
                    :searchable="true"
                    resource-type="cost_accounts"
                    :search-fields="['name']"
                    :error="form.errors.cost_account_id"
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
                        <div class="flex items-center justify-between">
                            <span class="font-bold">{{ t('item.opening') }}</span>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary px-3"
                                @click="addOpeningRow"
                            >
                                + {{ t('general.add', { title: t('item.opening') }) }}
                            </button>
                        </div>
                        <div
                            v-for="(opening, index) in form.openings"
                            :key="index"
                            class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-4 items-start"
                        >
                            <NextInput :label="specText?? t('item.batch')" v-model="opening.batch" :error="form.errors?.[`openings.${index}.batch`]" />
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
                            <div class="md:col-span-4 flex justify-end -mt-2" v-if="form.openings.length > 1">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger px-3"
                                    @click="removeRow(index)"
                                >
                                    −
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <SubmitButtons
                :create-label="t('general.create')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :disabled="disabled"
                :creating-label="t('general.creating', { name: t('item.item') })"
                :create-loading="createLoading"
                :create-and-new-loading="createAndNewLoading"
                @create-and-new="handleSubmitAction(true)"
                @cancel="handleCancel"
            />
        </form>
    </AppLayout>
</template>
