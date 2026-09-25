<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useFormGuard } from '@/composables/useFormGuard'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/Components/ui/toast/use-toast'
import axios from 'axios'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'
import AttachmentUploader from '@/Components/AttachmentUploader.vue'
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import { useSidebar } from '@/Components/ui/sidebar/utils'
import { todayValueForCalendar } from '@/utils/dateDefaults'
import { pickDefaultVariant, resolveVariantOnHand } from '@/composables/useVariantLine'
import VariantCell from '@/Components/inventory/VariantCell.vue'
import { Switch } from '@/Components/ui/switch'
import { Label } from '@/Components/ui/label'
import { Trash2, Info } from 'lucide-vue-next'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/Components/ui/tooltip'

const { t } = useI18n()
const { toast } = useToast()

const props = defineProps({
  bankAccounts: { type: [Array, Object], default: () => [] },
  expenseAccounts: { type: [Array, Object], default: () => [] },
  defaultExpenseAccountId: { type: String, default: null },
})

const page = usePage()
const calendarType = computed(() => page.props.auth?.user?.calendar_type || 'gregorian')
const warehouses = computed(() => page.props.warehouses?.data || page.props.warehouses || [])
const unitMeasures = computed(() => page.props.unitMeasures?.data || page.props.unitMeasures || [])
const currencies = computed(() => page.props.currencies?.data || page.props.currencies || [])
const homeCurrency = computed(() => page.props.homeCurrency?.data || page.props.homeCurrency || null)
const bankAccounts = computed(() => props.bankAccounts?.data || props.bankAccounts || [])
const expenseAccounts = computed(() => props.expenseAccounts?.data || props.expenseAccounts || [])
const itemOptions = ref([])
const createEmptyRow = () => ({
  item_id: '',
  selected_item: null,
  quantity: '',
  measure_id: '',
  selected_measure: null,
  batch: '',
  selected_batch: null,
  expire_date: '',
  unit_price: '',
  base_unit_price: '',
  available_measures: [],
  variant_id: null,
  selected_variant: null,
  item_variants: [],
})
const defaultTransferRows = () => [createEmptyRow(), createEmptyRow(), createEmptyRow(), createEmptyRow()]

const form = useForm({
  date: '',
  from_warehouse_id: '',
  to_warehouse_id: '',
  selected_from_warehouse: null,
  selected_to_warehouse: null,
  has_transfer_cost: false,
  transfer_cost: '',
  bank_account_id: '',
  selected_bank_account: null,
  expense_account_id: '',
  selected_expense_account: null,
  currency_id: '',
  selected_currency: null,
  rate: 1,
  remarks: '',
  items: defaultTransferRows(),
  attachments: [],
})

const submitAction = ref(null)
const createLoading = computed(() => form.processing && submitAction.value === 'create')
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new')

const handleSubmitAction = (createAndNew = false) => {
  submitAction.value = createAndNew ? 'create_and_new' : 'create'
  handleSubmit(createAndNew)
}

const itemSearchOptions = computed(() => {
  const additionalParams = {}
  if (form.from_warehouse_id) {
    additionalParams.warehouse_id = form.from_warehouse_id
  }
  return { additionalParams, limit: 200 }
})

const loadItemOptions = async (warehouseId = form.warehouse_id) => {
    if (!warehouseId) {
        itemOptions.value = []
        return
    }
    try {
        const response = await axios.get(route('search.items-list'), {
            params: {
                warehouse_id: warehouseId,
                limit: 50,
            }
        })
        itemOptions.value = response.data?.data || []
    } catch (error) {
        console.error('Failed to load items', error)
        itemOptions.value = []
    }
}

watch(
  warehouses,
  (availableWarehouses = []) => {
    if (availableWarehouses.length && !form.from_warehouse_id) {
      const preferredWarehouse = availableWarehouses.find(str => str.is_main) || availableWarehouses[0]
      form.selected_from_warehouse = preferredWarehouse || null
      form.from_warehouse_id = preferredWarehouse?.id || ''
    }
  },
  { immediate: true }
)

const applyCreateDefaults = () => {
  const preferredWarehouse = warehouses.value.find(str => str.is_main) || warehouses.value[0] || null
  form.date = todayValueForCalendar(calendarType.value)
  form.selected_from_warehouse = preferredWarehouse
  form.from_warehouse_id = preferredWarehouse?.id || ''
  form.has_transfer_cost = false
  applyTransferCostDefaults()
}

watch(() => form.from_warehouse_id, (warehouseId) => {
  if (!warehouseId) {
    itemOptions.value = []
    return
  }
  loadItemOptions(warehouseId)
}, { immediate: true });

/**
 * Turning the switch off clears everything behind it, so a cost that was typed
 * and then abandoned cannot be posted by accident. Turning it on pre-selects
 * the branch's Item Transfer Expense account and the home currency at rate 1 —
 * the answer for almost every transfer.
 */
const applyTransferCostDefaults = () => {
  if (!form.has_transfer_cost) {
    form.transfer_cost = ''
    form.bank_account_id = ''
    form.selected_bank_account = null
    form.expense_account_id = ''
    form.selected_expense_account = null
    form.currency_id = ''
    form.selected_currency = null
    form.rate = 1
    return
  }

  if (!form.expense_account_id) {
    const preferred = expenseAccounts.value.find(a => a.id === props.defaultExpenseAccountId)
    form.selected_expense_account = preferred || null
    form.expense_account_id = preferred?.id || ''
  }

  if (!form.currency_id) {
    const preferred = currencies.value.find(c => c.id === homeCurrency.value?.id)
      || currencies.value.find(c => c.is_base_currency)
      || null
    form.selected_currency = preferred
    form.currency_id = preferred?.id || ''
    form.rate = Number(preferred?.exchange_rate) || 1
  }
}

watch(() => form.has_transfer_cost, applyTransferCostDefaults)

const handleCurrencyChange = (currency) => {
  form.selected_currency = currency || null
  form.currency_id = currency?.id || ''
  form.rate = Number(currency?.exchange_rate) || 1
}

const sameWarehouseError = computed(() => {
  return form.from_warehouse_id && form.to_warehouse_id && form.from_warehouse_id === form.to_warehouse_id
})

const handleSelectChange = (field, value) => {
  form[field] = value
}

const buildAvailableMeasures = (selectedItem) => {
  const selUM = selectedItem?.unitMeasure || {}
  const selectedQuantityId = selUM.quantity_id ?? selUM.quantity?.id
  const selectedQuantityName = (selUM.quantity?.name || selUM.quantity?.code || '').toString().toLowerCase()
  return unitMeasures.value.filter(unit => {
    const unitQtyId = unit?.quantity_id ?? unit?.quantity?.id
    const unitQtyName = (unit?.quantity?.name || unit?.quantity?.code || '').toString().toLowerCase()
    return (selectedQuantityId && unitQtyId === selectedQuantityId) || (!!selectedQuantityName && unitQtyName === selectedQuantityName)
  })
}

const handleItemChange = (index, selectedItem) => {
  const row = form.items[index]
  if (!row || !selectedItem) {
    row.selected_item = null
    row.item_id = ''
    row.available_measures = []
    row.selected_measure = null
    row.quantity = ''
    row.batch = ''
    row.expire_date = ''
    row.unit_price = ''
    row.item_variants = []
    row.selected_variant = null
    row.variant_id = null
    return
  }
  row.available_measures = buildAvailableMeasures(selectedItem)
  row.selected_measure = selectedItem.unitMeasure || null
  row.item_id = selectedItem.id
  row.item_variants = selectedItem.item_variants || []
  row.selected_variant = pickDefaultVariant(row.item_variants)
  row.variant_id = row.selected_variant?.id || null
  row.selected_batch = null
  row.batch = ''
  row.expire_date = ''
  repriceRow(row)
}

/**
 * What the moved stock is worth.
 *
 * A transfer is not a purchase: the line carries the cost of the goods, so the
 * variant's own average wins, then its purchase price, then the item's figures.
 * Reading the parent item's cost for a variant that has its own would move the
 * goods at the wrong value and then bake that value into the variant average.
 */
const resolveRowCost = (row) => {
  const positive = (value) => {
    const number = Number(value)
    return Number.isFinite(number) && number > 0 ? number : null
  }

  return positive(row.selected_variant?.avg_cost)
    ?? positive(row.selected_variant?.purchase_price)
    ?? positive(row.selected_item?.avg_cost)
    ?? positive(row.selected_item?.purchase_price)
    ?? 0
}

const repriceRow = (row) => {
  if (!row?.selected_item) return
  row.base_unit_price = resolveRowCost(row)
  const baseUnit = Number(row.selected_item.unitMeasure?.unit) || 1
  const selectedUnit = Number(row.selected_measure?.unit) || baseUnit
  row.unit_price = (row.base_unit_price / baseUnit) * selectedUnit
}

const handleVariantChange = (index, variant) => {
  const row = form.items[index]
  if (!row) return
  row.selected_variant = variant || null
  row.variant_id = variant?.id || null
  // Switching variant moves both the on-hand shown and the cost used with it.
  repriceRow(row)
}

function handleBatchChange(index, batch){
    const row = form.items[index]
    if (!row) return
    row.batch = batch?.batch || ''
    row.expire_date = batch?.expire_date || ''
    // A batch carries its own on-hand, which onhand(index) now reads straight
    // off selected_batch; the stale row.on_hand copy it used to write was
    // passed the row instead of its index and always came back empty.
}

function onhand(index) {
  const item = form.items[index]
  if (!item || !item.selected_item) return ''
  const selected_item = item.selected_item
  // A chosen batch is the most specific figure; otherwise a variant that has
  // stock recorded against it shows its own, not the parent item's total.
  const onHand = resolveVariantOnHand(item) ?? 0
  const baseUnit = Number(selected_item?.unitMeasure?.unit) || 1
  const selectedUnit = Number(item.selected_measure?.unit) || baseUnit
  const converted = (onHand * baseUnit) / selectedUnit
  const free = Number(item.free) || 0
  const qty = Number(item.quantity) || 0
  return converted - free - qty
}

const isRowEnabled = (index) => {
  if (!form.selected_from_warehouse || !form.selected_to_warehouse) return false
  for (let i = 0; i < index; i++) {
    if (!form.items[i]?.selected_item) return false
  }
  return true
}

const addRow = () => {
  form.items.push(createEmptyRow())
}

const deleteRow = (index) => {
  if (form.items.length === 1) return
  form.items.splice(index, 1)
}

const toNum = (v, d = 0) => {
  const n = Number(v)
  return isNaN(n) ? d : n
}

const rowTotal = (index) => {
  const item = form.items[index]
  if (!item || !item.selected_item) return ''
  return toNum(item.quantity, 0) * toNum(item.unit_price, 0)
}

const totalRows = computed(() => form.items.length)
const totalQuantity = computed(() => form.items.reduce((acc, item) => acc + toNum(item.quantity, 0), 0))
const totalAmount = computed(() => form.items.reduce((acc, item) => acc + (toNum(item.quantity, 0) * toNum(item.unit_price, 0)), 0))

function handleSubmit(createAndNew = false) {
  if (sameWarehouseError.value) {
    toast({
      title: t('general.error'),
      description: t('item_transfer.warehouses_cannot_be_same'),
      variant: 'destructive',
      class: 'bg-pink-600 text-white',
    })
    return
  }

  const payloadItems = form.items
    .filter(item => item.item_id)
    .map(item => ({
      item_id: item.item_id,
      quantity: item.quantity,
      measure_id: item.selected_measure?.id || item.measure_id,
      batch: item.batch || '',
      expire_date: item.expire_date || null,
      unit_price: item.unit_price || 0,
      variant_id: item.selected_variant?.id ?? item.variant_id ?? null,
    }))

  if (!payloadItems.length) {
    toast({
      title: t('general.error'),
      description: t('general.no_data_found'),
      variant: 'destructive',
      class: 'bg-yellow-600 text-white',
    })
    return
  }

  form.transform(() => ({
    date: form.date,
    from_warehouse_id: form.from_warehouse_id,
    to_warehouse_id: form.to_warehouse_id,
    has_transfer_cost: form.has_transfer_cost,
    transfer_cost: form.has_transfer_cost ? form.transfer_cost : null,
    bank_account_id: form.has_transfer_cost ? form.bank_account_id : null,
    expense_account_id: form.has_transfer_cost ? form.expense_account_id : null,
    currency_id: form.has_transfer_cost ? form.currency_id : null,
    rate: form.has_transfer_cost ? form.rate : null,
    remarks: form.remarks,
    items: payloadItems,
    attachments: form.attachments,
    ...(createAndNew ? { create_and_new: true } : {}),
  })).post(route('item-transfers.store'), {
    onSuccess: () => {
      toast({
        title: t('general.success'),
        description: t('general.create_success', { name: t('item_transfer.item_transfer') }),
        variant: 'success',
        class: 'bg-green-600 text-white',
      })

      if (createAndNew) {
        form.reset()
        form.items = defaultTransferRows()
        form.attachments = []
        applyCreateDefaults()
      }
    },
  })
}

// Collapse sidebar while on this page, restore on leave (safe if provider missing)
let sidebar = null
try {
  sidebar = useSidebar()
} catch (e) {
  sidebar = null
}
const prevSidebarOpen = ref(true)
onMounted(() => {
  if (sidebar) {
    prevSidebarOpen.value = sidebar.open.value
    sidebar.setOpen(false)
  }
  applyCreateDefaults()
})
onUnmounted(() => {
  if (sidebar) {
    sidebar.setOpen(prevSidebarOpen.value)
  }
})

useFormGuard(form)
</script>

<template>
  <AppLayout :title="t('general.create', { name: t('item_transfer.item_transfer') })" :sidebar-collapsed="true">
    <FormPageToolbar confirm-module="item_transfer" back-route="item-transfers.index" module="transfer" />
    <form @submit.prevent="handleSubmitAction(false)">
      <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.create', { name: t('item_transfer.item_transfer') }) }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextDate v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
          <NextSelect
            autofocus
            :options="warehouses"
            v-model="form.selected_from_warehouse"
            @update:modelValue="(value) => handleSelectChange('from_warehouse_id', value.id)"
            label-key="name"
            value-key="id"
            :reduce="warehouse => warehouse"
            :floating-text="t('item_transfer.from_warehouse')"
            is-required
            :error="form.errors?.from_warehouse_id"
            :searchable="true"
            resource-type="warehouses"
            :search-fields="['name','code']"
          />
          <NextSelect
            :options="warehouses"
            v-model="form.selected_to_warehouse"
            @update:modelValue="(value) => handleSelectChange('to_warehouse_id', value.id)"
            label-key="name"
            value-key="id"
            :reduce="warehouse => warehouse"
            :floating-text="t('item_transfer.to_warehouse')"
            is-required
            :error="form.errors?.to_warehouse_id"
            :searchable="true"
            resource-type="warehouses"
            :search-fields="['name','code']"

          />
          <NextTextarea
            v-model="form.remarks"
            :error="form.errors?.remarks"
            :label="t('general.remarks')"
          />
        </div>

        <div class="mt-4 flex items-center gap-2">
          <Switch id="has_transfer_cost" v-model="form.has_transfer_cost" />
          <Label for="has_transfer_cost" class="cursor-pointer">{{ t('item_transfer.has_transfer_cost') }}</Label>
        </div>

        <div v-if="form.has_transfer_cost" class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
          <NextSelect
            :options="bankAccounts"
            v-model="form.selected_bank_account"
            @update:modelValue="(value) => { form.selected_bank_account = value; form.bank_account_id = value?.id || '' }"
            label-key="name"
            value-key="id"
            :reduce="account => account"
            :floating-text="t('item_transfer.bank_account')"
            is-required
            :error="form.errors?.bank_account_id"
            :searchable="true"
          />
          <NextSelect
            :options="currencies"
            v-model="form.selected_currency"
            @update:modelValue="handleCurrencyChange"
            label-key="name"
            value-key="id"
            :reduce="currency => currency"
            :floating-text="t('admin.currency.currency')"
            is-required
            :error="form.errors?.currency_id"
            :searchable="true"
          />
          <NextInput
            v-model="form.rate"
            type="number"
            step="any"
            inputmode="decimal"
            :error="form.errors?.rate"
            :label="t('general.rate')"
          />
          <NextInput
            v-model="form.transfer_cost"
            type="number"
            step="any"
            inputmode="decimal"
            :error="form.errors?.transfer_cost"
            :label="t('general.amount')"
          />
          <div class="md:col-span-2 flex items-start gap-2">
            <div class="flex-1 min-w-0">
              <NextSelect
                :options="expenseAccounts"
                v-model="form.selected_expense_account"
                @update:modelValue="(value) => { form.selected_expense_account = value; form.expense_account_id = value?.id || '' }"
                label-key="name"
                value-key="id"
                :reduce="account => account"
                :floating-text="t('item_transfer.expense_account')"
                :error="form.errors?.expense_account_id"
                :searchable="true"
              />
            </div>
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger as-child>
                  <button
                    type="button"
                    tabindex="-1"
                    class="mt-3 shrink-0 text-violet-500"
                    :aria-label="t('item_transfer.expense_account_hint')"
                  >
                    <Info class="h-4 w-4" />
                  </button>
                </TooltipTrigger>
                <TooltipContent>
                  <p class="max-w-xs">{{ t('item_transfer.expense_account_hint') }}</p>
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          </div>
        </div>
      </div>

      <div class="rounded-xl border bg-card shadow-sm overflow-x-auto max-h-80">
        <table class="w-full table-fixed min-w-[900px] purchase-table border-separate">
          <thead class="bg-card sticky top-0 z-10">
            <tr class="text-muted-foreground font-semibold text-sm text-violet-500">
              <th class="px-1 py-1 w-5 min-w-5">#</th>
              <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }} <span class="text-red-500">*</span></th>
              <th class="px-1 py-1 w-32">{{ t('item.variant') }}</th>
              <th class="px-1 py-1 w-32">{{ t('general.batch') }}</th>
              <th class="px-1 py-1 w-36">{{ t('general.expire_date') }}</th>
              <th class="px-1 py-1 w-16">{{ t('general.qty') }} <span class="text-red-500">*</span></th>
              <th class="px-1 py-1 w-24">{{ t('general.on_hand') }}</th>
              <th class="px-1 py-1 w-24">{{ t('general.unit') }} <span class="text-red-500">*</span></th>
              <th class="px-1 py-1 w-24">{{ t('general.unit_price') }}</th>
              <th class="px-1 py-1 w-24">{{ t('general.total') }}</th>
              <th class="px-1 py-1 w-10">
                <Trash2 class="w-4 h-4 text-fuchsia-700 inline" />
              </th>
            </tr>
          </thead>
          <tbody class="p-2">
            <tr v-for="(item, index) in form.items" :key="item.id || index" class="hover:bg-muted/40 transition-colors">
              <td class="px-1 py-2 align-top w-5">{{ index + 1 }}</td>
              <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                <NextSelect
                  :options="itemOptions"
                  v-model="item.selected_item"
                  label-key="name"
                  :placeholder="t('general.search_or_select')"
                  id="item_id"
                  :error="form.errors?.[`items.${index}.item_id`]"`
                  :show-arrow="false"
                  :searchable="true"
                  resource-type="items-list"
                  :search-fields="['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search']"
                  value-key="id"
                  :reduce="itemValue => itemValue"
                  :search-options="itemSearchOptions"
                  @update:modelValue="value => { handleItemChange(index, value) }"
                />
              </td>
              <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                <VariantCell
                  :model-value="item.selected_variant"
                  :item-variants="item.item_variants"
                  :disabled="!item?.selected_item"
                  :error="form.errors?.[`items.${index}.variant_id`]"
                  :id="`transfer_variant_${index}`"
                  @update:modelValue="value => handleVariantChange(index, value)"
                />
              </td>
              <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                <NextSelect
                    :options="item.selected_item?.batches"
                    v-model="item.selected_batch"
                    label-key="batch"
                    :placeholder="t('general.search_or_select')"
                    id="batch_id"
                    :error="form.errors?.[`items.${index}.batch`]"`
                    :show-arrow="false"
                    value-key="batch"
                    :reduce="batch => batch"
                    @update:modelValue=" value => { handleBatchChange(index, value); }"
                />
              </td>
              <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                <NextDate v-model="item.expire_date"
                :lock-future-dates="false"
                :error="form.errors?.[`items.${index}.expire_date`]"   />
              </td>
              <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                <NextInput
                  v-model="item.quantity"
                  :disabled="!item.selected_item"
                  type="number"
                  step="any"
                  inputmode="decimal"
                  :error="form.errors?.[`items.${index}.quantity`]"`
                />
              </td>
              <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                <span :title="String(onhand(index))">{{ Number(onhand(index)).toFixed(2) }}</span>
              </td>
              <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                <NextSelect
                  :options="item.available_measures || []"
                  v-model="item.selected_measure"
                  label-key="name"
                  :error="form.errors?.[`items.${index}.measure_id`]"`
                  value-key="id"
                  :show-arrow="false"
                  :reduce="unit => unit"
                  @update:modelValue="(measure) => {
                    item.measure_id = measure?.id
                    const baseUnit = Number(item.selected_item?.unitMeasure?.unit) || 1
                    const selectedUnit = Number(measure?.unit) || baseUnit
                    const baseUnitPrice = Number(item.base_unit_price || 0)
                    item.unit_price = (baseUnitPrice / baseUnit) * selectedUnit
                  }"
                />
              </td>
              <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                <NextInput
                  v-model="item.unit_price"
                  :disabled="!item.selected_item"
                  type="number"
                  step="any"
                  inputmode="decimal"
                  :error="form.errors?.[`items.${index}.unit_price`]"`
                />
              </td>
              <td class="text-center">
                {{ rowTotal(index) }}
              </td>
              <td class="w-10 text-center">
                <Trash2 class="w-4 h-4 cursor-pointer text-fuchsia-500 inline" @click="deleteRow(index)" />
              </td>
            </tr>
          </tbody>
          <tfoot class="sticky bottom-0 bg-card">
            <tr class="bg-violet-500/10 hover:bg-violet-500/30 transition-colors">
              <td></td>
              <td class="text-center">{{ totalRows }}</td>
              <td></td>
              <td></td>
              <td></td>
              <td class="text-center">{{ totalQuantity || 0 }}</td>
              <td></td>
              <td class="text-center"></td>
              <td class="text-center">{{ totalAmount || 0 }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="mt-4">
        <AttachmentUploader v-model="form.attachments" :label="t('general.attachments')" :error="form.errors['attachments.0']" />
      </div>

      <SubmitButtons module="item_transfer"
        :create-label="t('general.create')"
        :create-and-new-label="t('general.create_and_new')"
        :cancel-label="t('general.cancel')"
        :creating-label="t('general.creating', { name: t('item_transfer.item_transfer') })"
        :create-loading="createLoading"
        :create-and-new-loading="createAndNewLoading"
        @create-and-new="handleSubmitAction(true)"
        @cancel="() => $inertia.visit(route('item-transfers.index'))"
      />
    </form>
  </AppLayout>
</template>

<style scoped>
.purchase-table thead {
  border: 2px solid hsl(var(--border));
  border-radius: 8px;
}

.purchase-table thead th {
  border-bottom: 1px solid hsl(var(--border));
  padding: 0.5rem;
  white-space: nowrap;
  overflow: hidden;
}
</style>
