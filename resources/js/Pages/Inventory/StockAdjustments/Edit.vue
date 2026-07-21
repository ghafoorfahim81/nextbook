<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useFormGuard } from '@/composables/useFormGuard'
import { useForm, usePage } from '@inertiajs/vue3'
import { h, ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useColors } from '@/composables/useColors'
import { useToast } from '@/Components/ui/toast/use-toast'
import { ToastAction } from '@/Components/ui/toast'
import axios from 'axios'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'
import AttachmentUploader from '@/Components/AttachmentUploader.vue'
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import { Trash2, ArrowDownCircle, ArrowUpCircle } from 'lucide-vue-next'
import { useSidebar } from '@/Components/ui/sidebar/utils'

const { t } = useI18n()
const { colorOptions } = useColors()
const { toast } = useToast()

const props = defineProps({
  adjustment: Object,
  reasons: Array,
})

const adjustmentData = computed(() => props.adjustment?.data || props.adjustment || {})

const page = usePage()
const warehouses = computed(() => page.props.warehouses?.data || page.props.warehouses || [])
const unitMeasures = computed(() => page.props.unitMeasures?.data || page.props.unitMeasures || [])
const sizeOptions = computed(() => page.props.sizes?.data || page.props.sizes || [])
const userPreferences = computed(() => page.props.user_preferences || {})
const allowInCostOverride = computed(
  () => userPreferences.value?.stock_adjustment?.allow_in_cost_override ?? true
)

const createEmptyRow = () => ({
  item_id: '',
  selected_item: null,
  quantity: '',
  unit_measure_id: '',
  selected_measure: null,
  batch: '',
  selected_batch: null,
  color: null,
  size_id: null,
  selected_size: null,
  expire_date: '',
  unit_cost: '',
  base_unit_cost: '',
  available_measures: [],
})

const initialReason = props.reasons?.find(r => r.id === adjustmentData.value.reason) || null
const initialWarehouse = adjustmentData.value.warehouse
  ? { id: adjustmentData.value.warehouse.id, name: adjustmentData.value.warehouse.name }
  : null

const buildRow = (line) => ({
  item_id: line.item_id,
  selected_item: line.item ? { ...line.item, unitMeasure: line.unit_measure } : null,
  quantity: line.quantity,
  unit_measure_id: line.unit_measure_id,
  selected_measure: line.unit_measure || null,
  batch: line.batch || '',
  selected_batch: line.batch ? { batch: line.batch } : null,
  color: line.color || null,
  size_id: line.size_id || null,
  selected_size: line.size_id ? { id: line.size_id, name: line.size_name } : null,
  expire_date: line.expire_date || '',
  unit_cost: line.unit_cost,
  base_unit_cost: line.unit_cost,
  available_measures: [],
})

const form = useForm({
  date: adjustmentData.value.date || '',
  reason: adjustmentData.value.reason || '',
  selected_reason: initialReason,
  warehouse_id: adjustmentData.value.warehouse_id || '',
  selected_warehouse: initialWarehouse,
  notes: adjustmentData.value.notes || '',
  items: (adjustmentData.value.items || []).map(buildRow).concat([createEmptyRow()]),
  attachments: [],
})

const direction = computed(() => form.selected_reason?.direction || null)
const isOut = computed(() => direction.value === 'out')
const isIn = computed(() => direction.value === 'in')
const costEditable = computed(() => isIn.value && allowInCostOverride.value)

const itemSearchOptions = computed(() => {
  const additionalParams = {}
  if (form.warehouse_id) {
    additionalParams.warehouse_id = form.warehouse_id
  }
  return { additionalParams, limit: 200 }
})

const itemOptions = ref([])
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
      },
    })
    itemOptions.value = response.data?.data || []
  } catch (error) {
    console.error('Failed to load items', error)
    itemOptions.value = []
  }
}

watch(() => form.warehouse_id, (warehouseId) => {
  if (!warehouseId) {
    itemOptions.value = []
    return
  }
  loadItemOptions(warehouseId)
}, { immediate: true })

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

const applyUnitCost = (row) => {
  const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1
  const selectedUnit = Number(row.selected_measure?.unit) || baseUnit
  const baseCost = Number(row.base_unit_cost) || 0
  row.unit_cost = (baseCost / baseUnit) * selectedUnit
}

const handleItemChange = (index, selectedItem) => {
  const row = form.items[index]
  if (!row) return
  if (!selectedItem) {
    Object.assign(row, createEmptyRow())
    return
  }
  row.available_measures = buildAvailableMeasures(selectedItem)
  row.selected_measure = selectedItem.unitMeasure || null
  row.unit_measure_id = selectedItem.unitMeasure?.id || ''
  row.item_id = selectedItem.id
  row.base_unit_cost = selectedItem.avg_cost ?? selectedItem.purchase_price ?? 0
  row.selected_batch = null
  row.batch = ''
  row.expire_date = ''
  row.quantity = 1
  applyUnitCost(row)

  // Add a new empty row only when selecting into the last row
  if (index === form.items.length - 1) {
    addRow()
  }

  notifyIfDuplicate(index)
}

function handleBatchChange(index, batch) {
  const row = form.items[index]
  row.selected_batch = batch ?? null
  row.batch = batch?.batch || ''
  row.expire_date = batch?.expire_date || ''
  notifyIfDuplicate(index)
}

// Duplicate line detection (same item + batch + expiry + unit), mirroring the
// sale create table: a persistent toast with an "unselect" action, and the
// submit button stays disabled while a duplicate exists.
const buildRowKey = (r) => {
  const measureId = r?.selected_measure?.id
    || (typeof r?.selected_measure === 'object' ? (r?.selected_measure?.name || r?.selected_measure?.unit) : r?.selected_measure)
    || ''
  return [
    (r.item_id || r.selected_item?.id || '').toString(),
    (r.batch || '').toString().trim().toLowerCase(),
    (r.expire_date || '').toString().trim(),
    measureId.toString(),
  ].join('|')
}

const isDuplicateRow = (index) => {
  const r = form.items[index]
  if (!r || !r.selected_item || !r.selected_measure) return false
  const key = buildRowKey(r)
  let count = 0
  for (let i = 0; i < form.items.length; i++) {
    if (key === buildRowKey(form.items[i])) count++
    if (count > 1) return true
  }
  return false
}

const resetRow = (index) => {
  const row = form.items[index]
  if (!row) return
  Object.assign(row, createEmptyRow())
}

const duplicateToast = ref(null)

const hasDuplicateRows = computed(() => {
  const seen = new Set()
  for (const r of form.items) {
    if (!r?.selected_item || !r?.selected_measure) continue
    const key = buildRowKey(r)
    if (seen.has(key)) return true
    seen.add(key)
  }
  return false
})

watch(hasDuplicateRows, (hasDuplicates) => {
  if (!hasDuplicates && duplicateToast.value) {
    duplicateToast.value.dismiss()
    duplicateToast.value = null
  }
})

const notifyIfDuplicate = (index) => {
  if (isDuplicateRow(index)) {
    const item = form.items[index]
    const batchText = item.batch ? `Batch: ${item.batch}` : 'No batch'
    const expiryText = item.expire_date ? `Expiry: ${item.expire_date}` : 'No expiry'
    duplicateToast.value = toast({
      title: t('general.duplicate_item_detected'),
      description: t('general.duplicate_item_detected_description', { batchText: batchText, expiryText: expiryText }),
      variant: 'destructive',
      class: 'bg-pink-600 text-white',
      duration: Infinity,
      action: h(ToastAction, { altText: t('general.unselect'), onClick: () => resetRow(index) }, { default: () => t('general.unselect') }),
    })
  }
}

function onhand(index) {
  const row = form.items[index]
  if (!row || !row.selected_item) return ''
  const onHand = row?.selected_batch?.on_hand ?? row.selected_item.on_hand
  if (onHand === undefined || onHand === null) return ''
  const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1
  const selectedUnit = Number(row.selected_measure?.unit) || baseUnit
  const converted = (Number(onHand) * baseUnit) / selectedUnit
  // Show the projected on-hand after this line: OUT subtracts, IN adds.
  const qty = Number(row.quantity) || 0
  return isOut.value ? converted - qty : converted + qty
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
  const row = form.items[index]
  if (!row || !row.selected_item) return ''
  return (toNum(row.quantity, 0) * toNum(row.unit_cost, 0)).toFixed(2)
}

const totalRows = computed(() => form.items.length)
const totalQuantity = computed(() => form.items.reduce((acc, row) => acc + toNum(row.quantity, 0), 0))
const totalAmount = computed(() => form.items.reduce((acc, row) => acc + (toNum(row.quantity, 0) * toNum(row.unit_cost, 0)), 0))

function handleSubmit() {
  if (hasDuplicateRows.value) {
    toast({
      title: t('general.error'),
      description: t('general.duplicate_item_detected'),
      variant: 'destructive',
      class: 'bg-pink-600 text-white',
    })
    return
  }

  const payloadItems = form.items
    .filter(row => row.item_id)
    .map(row => ({
      item_id: row.item_id,
      quantity: row.quantity,
      unit_measure_id: row.selected_measure?.id || row.unit_measure_id,
      batch: row.batch || '',
      color: row.color || null,
      size_id: row.selected_size?.id || row.size_id || null,
      expire_date: row.expire_date || null,
      unit_cost: costEditable.value ? (row.unit_cost || null) : null,
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
    reason: form.reason,
    warehouse_id: form.warehouse_id,
    notes: form.notes,
    items: payloadItems,
    attachments: form.attachments,
  })).put(route('stock-adjustments.update', adjustmentData.value.id), {
    onSuccess: () => {
      toast({
        title: t('general.success'),
        description: t('general.update_success', { name: t('adjustment.stock_adjustment') }),
        variant: 'success',
        class: 'bg-green-600 text-white',
      })
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
})
onUnmounted(() => {
  if (sidebar) {
    sidebar.setOpen(prevSidebarOpen.value)
  }
})

useFormGuard(form)
</script>

<template>
  <AppLayout :title="t('general.edit', { name: t('adjustment.stock_adjustment') })" :sidebar-collapsed="true">
    <FormPageToolbar confirm-module="stock_adjustment" back-route="stock-adjustments.index" module="adjustment" />
    <form @submit.prevent="handleSubmit">
      <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.edit', { name: t('adjustment.stock_adjustment') }) }} — {{ adjustmentData.reference }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextDate v-model="form.date" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
          <NextSelect
            autofocus
            :options="reasons"
            v-model="form.selected_reason"
            @update:modelValue="(value) => handleSelectChange('reason', value?.id || '')"
            label-key="name"
            value-key="id"
            :reduce="reason => reason"
            :floating-text="t('adjustment.reason')"
            is-required
            :error="form.errors?.reason"
            :searchable="true"
          />
          <NextSelect
            :options="warehouses"
            v-model="form.selected_warehouse"
            @update:modelValue="(value) => handleSelectChange('warehouse_id', value?.id || '')"
            label-key="name"
            value-key="id"
            :reduce="warehouse => warehouse"
            :floating-text="t('adjustment.warehouse')"
            is-required
            :error="form.errors?.warehouse_id"
            :searchable="true"
          />
          <div v-if="direction" class="flex items-center gap-2">
            <component
              :is="isOut ? ArrowDownCircle : ArrowUpCircle"
              class="w-5 h-5"
              :class="isOut ? 'text-red-500' : 'text-green-500'"
            />
            <span class="text-sm font-medium" :class="isOut ? 'text-red-500' : 'text-green-500'">
              {{ isOut ? t('adjustment.stock_decreases') : t('adjustment.stock_increases') }}
            </span>
          </div>
          <NextTextarea
            v-model="form.notes"
            :error="form.errors?.notes"
            :label="t('adjustment.notes')"
          />
        </div>
      </div>

      <div class="rounded-xl border bg-card shadow-sm overflow-x-auto max-h-80">
        <table class="w-full table-fixed min-w-[900px] purchase-table border-separate">
          <thead class="bg-card sticky top-0 z-10">
            <tr class="text-muted-foreground font-semibold text-sm text-violet-500">
              <th class="px-1 py-1 w-5 min-w-5">#</th>
              <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }} <span class="text-red-500">*</span></th>
              <th class="px-1 py-1 w-32">{{ t('general.batch') }}</th>
              <th class="px-1 py-1 w-32">{{ t('item.color') }}</th>
              <th class="px-1 py-1 w-28">{{ t('item.size') }}</th>
              <th class="px-1 py-1 w-36">{{ t('general.expire_date') }}</th>
              <th class="px-1 py-1 w-16">{{ t('general.qty') }} <span class="text-red-500">*</span></th>
              <th class="px-1 py-1 w-24">{{ t('general.on_hand') }}</th>
              <th class="px-1 py-1 w-24">{{ t('general.unit') }} <span class="text-red-500">*</span></th>
              <th class="px-1 py-1 w-24">{{ t('adjustment.unit_cost') }}</th>
              <th class="px-1 py-1 w-24">{{ t('general.total') }}</th>
              <th class="px-1 py-1 w-10">
                <Trash2 class="w-4 h-4 text-fuchsia-700 inline" />
              </th>
            </tr>
          </thead>
          <tbody class="p-2">
            <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-muted/40 transition-colors">
              <td class="px-1 py-2 align-top w-5">{{ index + 1 }}</td>
              <td>
                <NextSelect
                  :options="itemOptions"
                  v-model="item.selected_item"
                  label-key="name"
                  :placeholder="t('general.search_or_select')"
                  id="item_id"
                  :error="form.errors?.[`items.${index}.item_id`]"
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
              <td>
                <NextSelect
                  :options="item.selected_item?.batches"
                  v-model="item.selected_batch"
                  label-key="batch"
                  :placeholder="t('general.search_or_select')"
                  id="batch_id"
                  :error="form.errors?.[`items.${index}.batch`]"
                  :show-arrow="false"
                  value-key="batch"
                  :reduce="batch => batch"
                  @update:modelValue="value => { handleBatchChange(index, value) }"
                />
              </td>
              <td>
                <NextSelect
                  v-model="item.color"
                  :options="colorOptions"
                  label-key="name"
                  value-key="id"
                  :reduce="o => o.id"
                  :disabled="!item.selected_item"
                  :id="`adj_color_${index}`"
                  :placeholder="t('general.select')"
                  :show-arrow="false"
                  :append-to-body="true"
                  :error="form.errors?.[`items.${index}.color`]"
                >
                  <template #option="{ name, hex }">
                    <span class="flex items-center gap-2">
                      <span class="h-3.5 w-3.5 shrink-0 rounded-full border border-muted-foreground/40" :style="{ backgroundColor: hex }" />
                      <span>{{ name }}</span>
                    </span>
                  </template>
                  <template #selected-option="{ name, hex }">
                    <span class="flex items-center gap-1.5">
                      <span class="h-3 w-3 shrink-0 rounded-full border border-muted-foreground/40" :style="{ backgroundColor: hex }" />
                      <span>{{ name }}</span>
                    </span>
                  </template>
                </NextSelect>
              </td>
              <td>
                <NextSelect
                  v-model="item.selected_size"
                  :options="sizeOptions"
                  label-key="name"
                  value-key="id"
                  :reduce="s => s"
                  :disabled="!item.selected_item"
                  :id="`adj_size_${index}`"
                  :placeholder="t('general.select')"
                  :show-arrow="false"
                  :append-to-body="true"
                  :error="form.errors?.[`items.${index}.size_id`]"
                />
              </td>
              <td>
                <NextDate
                  v-model="item.expire_date"
                  :disabled="isOut"
                  :error="form.errors?.[`items.${index}.expire_date`]"
                />
              </td>
              <td>
                <NextInput
                  v-model="item.quantity"
                  :disabled="!item.selected_item"
                  type="number"
                  step="any"
                  inputmode="decimal"
                  :error="form.errors?.[`items.${index}.quantity`]"
                />
              </td>
              <td>
                <span :title="String(onhand(index))">{{ item.selected_item && onhand(index) !== '' ? Number(onhand(index)).toFixed(2) : '' }}</span>
              </td>
              <td>
                <NextSelect
                  :options="item.available_measures.length ? item.available_measures : (item.selected_measure ? [item.selected_measure] : [])"
                  v-model="item.selected_measure"
                  label-key="name"
                  :error="form.errors?.[`items.${index}.unit_measure_id`]"
                  value-key="id"
                  :show-arrow="false"
                  :reduce="unit => unit"
                  @update:modelValue="(measure) => {
                    item.unit_measure_id = measure?.id
                    applyUnitCost(item)
                    notifyIfDuplicate(index)
                  }"
                />
              </td>
              <td>
                <NextInput
                  v-model="item.unit_cost"
                  :disabled="!item.selected_item || !costEditable"
                  type="number"
                  step="any"
                  inputmode="decimal"
                  :title="!costEditable ? t('adjustment.cost_from_costing_method') : ''"
                  :error="form.errors?.[`items.${index}.unit_cost`]"
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
              <td class="text-center">{{ totalQuantity || 0 }}</td>
              <td></td>
              <td class="text-center"></td>
              <td></td>
              <td class="text-center">{{ totalAmount.toFixed(2) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="mt-2">
        <button type="button" class="text-sm text-violet-600 hover:underline" @click="addRow">
          + {{ t('general.add', { name: t('item.item') }) }}
        </button>
      </div>

      <div class="mt-4">
        <AttachmentUploader v-model="form.attachments" :label="t('general.attachments')" :error="form.errors['attachments.0']" />
      </div>

      <div class="mt-4 flex items-center gap-2">
        <button
          type="submit"
          class="btn px-4 py-2 rounded-md bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50"
          :disabled="form.processing || hasDuplicateRows"
        >
          {{ form.processing ? t('general.updating', { name: t('adjustment.stock_adjustment') }) : t('general.update') }}
        </button>
        <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit(route('stock-adjustments.index'))">
          {{ t('general.cancel') }}
        </button>
      </div>
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
