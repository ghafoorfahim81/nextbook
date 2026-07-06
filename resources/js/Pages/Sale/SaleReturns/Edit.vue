<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { useFormGuard } from '@/composables/useFormGuard'
import { ref, computed, onMounted } from 'vue';
import axios from 'axios'
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDate from '@/Components/next/NextDatePicker.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    saleReturn: { type: Object, required: true },
    reasons: { type: Array, default: () => [] },
})

const returnData = computed(() => props.saleReturn?.data ?? props.saleReturn ?? {})

const form = useForm({
    number: returnData.value.number,
    sale_id: returnData.value.sale_id,
    date: '',
    reason: returnData.value.reason || '',
    description: returnData.value.description || '',
    item_list: [],
})

const rows = ref([])
const loadingItems = ref(false)
const loadError = ref('')

const existingQuantityBySaleItemId = computed(() => {
    const map = {}
    for (const item of returnData.value.item_list || []) {
        map[item.sale_item_id] = Number(item.quantity) || 0
    }
    return map
})

const loadReturnableItems = async () => {
    loadingItems.value = true
    loadError.value = ''

    try {
        const { data } = await axios.get(route('sale-returns.returnable-items'), {
            params: {
                sale_id: returnData.value.sale_id,
                exclude_sale_return_id: returnData.value.id,
            },
        })

        rows.value = (data.items || []).map(item => ({
            ...item,
            quantity_to_return: existingQuantityBySaleItemId.value[item.sale_item_id] ?? '',
        }))
    } catch (error) {
        rows.value = []
        loadError.value = error?.response?.data?.message || t('sale_return.no_sale_selected')
    } finally {
        loadingItems.value = false
    }
}

onMounted(() => {
    loadReturnableItems()
})

const rowLineTotal = (row) => (Number(row.quantity_to_return) || 0) * Number(row.unit_price || 0)

const totalCredit = computed(() =>
    rows.value.reduce((sum, row) => sum + rowLineTotal(row), 0)
)

const totalQuantity = computed(() =>
    rows.value.reduce((sum, row) => sum + (Number(row.quantity_to_return) || 0), 0)
)

const rowError = (row) => {
    const qty = Number(row.quantity_to_return) || 0
    if (qty > 0 && qty > Number(row.remaining_quantity || 0)) {
        return t('sale_return.remaining_quantity') + ': ' + row.remaining_quantity
    }
    return ''
}

const handleSubmit = () => {
    form.item_list = rows.value
        .filter(row => Number(row.quantity_to_return) > 0)
        .map(row => ({
            sale_item_id: row.sale_item_id,
            quantity: Number(row.quantity_to_return),
        }))

    form.put(route('sale-returns.update', returnData.value.id))
}

useFormGuard(form)
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('sale_return.sale_return') })">
        <FormPageToolbar back-route="sale-returns.index" module="sale_returns" />
        <form @submit.prevent="handleSubmit">
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('sale_return.sale_return') }) }} #{{ returnData.number }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextInput disabled :model-value="`#${returnData.sale_number}`" :label="t('sale_return.linked_sale')" />
                    <NextInput type="number" :error="form.errors?.number" v-model="form.number" :label="t('general.bill_number')" />
                    <NextDate v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
                    <NextSelect
                        :options="reasons"
                        v-model="form.reason"
                        label-key="name"
                        value-key="id"
                        :reduce="reason => reason.id"
                        :floating-text="t('sale_return.reason')"
                        :error="form.errors?.reason"
                    />
                    <div class="md:col-span-2">
                        <NextTextarea v-model="form.description" :label="t('general.description')" />
                    </div>
                </div>
            </div>

            <div class="rounded-md border bg-card shadow-sm border-violet-500">
                <div v-if="loadError" class="p-3 text-sm text-red-600">{{ loadError }}</div>
                <div v-if="loadingItems" class="p-3 text-sm text-muted-foreground">{{ t('general.loading') }}</div>
                <table v-else class="w-full table-fixed min-w-[1100px] border-separate">
                    <thead class="bg-card sticky top-0 z-10">
                        <tr class="rounded-xl text-muted-foreground font-semibold text-sm text-violet-500">
                            <th class="px-2 py-2 w-8 text-center">#</th>
                            <th class="px-2 py-2 w-56 text-left">{{ t('item.item') }}</th>
                            <th class="px-2 py-2 w-28">{{ t('general.batch') }}</th>
                            <th class="px-2 py-2 w-24 text-right">{{ t('sale_return.original_quantity') }}</th>
                            <th class="px-2 py-2 w-24 text-right">{{ t('sale_return.returned_quantity') }}</th>
                            <th class="px-2 py-2 w-24 text-right">{{ t('sale_return.remaining_quantity') }}</th>
                            <th class="px-2 py-2 w-24 text-right">{{ t('general.price') }}</th>
                            <th class="px-2 py-2 w-32 text-right">{{ t('sale_return.quantity_to_return') }}</th>
                            <th class="px-2 py-2 w-24 text-right">{{ t('general.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.sale_item_id"
                            :class="{ 'opacity-50': row.remaining_quantity <= 0 && !row.quantity_to_return }">
                            <td class="px-2 py-2 text-center">{{ index + 1 }}</td>
                            <td class="px-2 py-2">
                                <div class="font-medium">{{ row.item_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.warehouse_name }}</div>
                            </td>
                            <td class="px-2 py-2">{{ row.batch || '-' }}</td>
                            <td class="px-2 py-2 text-right">{{ row.original_quantity }}</td>
                            <td class="px-2 py-2 text-right">{{ row.returned_quantity }}</td>
                            <td class="px-2 py-2 text-right">{{ row.remaining_quantity }}</td>
                            <td class="px-2 py-2 text-right">{{ row.unit_price }}</td>
                            <td class="px-2 py-2">
                                <NextInput
                                    type="number"
                                    step="any"
                                    v-model="row.quantity_to_return"
                                    :label="t('sale_return.quantity_to_return')"
                                    :error="rowError(row) || form.errors?.[`item_list.${index}.quantity`]"
                                />
                            </td>
                            <td class="px-2 py-2 text-right font-semibold">{{ rowLineTotal(row).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex items-center justify-end gap-6 border-t border-border p-4 text-sm">
                    <div>
                        <span class="text-muted-foreground">{{ t('general.qty') }}: </span>
                        <span class="font-semibold text-foreground">{{ totalQuantity }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('sale_return.total_credit_to_customer') }}: </span>
                        <span class="text-lg font-bold text-violet-600 dark:text-violet-400">{{ totalCredit.toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <SubmitButtons
                class="mt-4"
                :create-label="t('general.save')"
                :create-and-new-label="t('general.save_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.saving')"
                :create-loading="form.processing"
                :show-create-and-new="false"
                module="sale_return"
                @cancel="$inertia.visit(route('sale-returns.index'))"
            />
        </form>
    </AppLayout>
</template>
