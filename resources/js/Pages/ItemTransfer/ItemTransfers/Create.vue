<script setup>
import AppLayout from '@/Layouts/Layout.vue'
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
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { Trash2 } from 'lucide-vue-next'
import { useSidebar } from '@/Components/ui/sidebar/utils'
import { todayValueForCalendar } from '@/utils/dateDefaults'

const { t } = useI18n()
const { toast } = useToast()

const page = usePage()
const calendarType = computed(() => page.props.auth?.user?.calendar_type || 'gregorian')
const warehouses = computed(() => page.props.warehouses?.data || page.props.warehouses || [])
const unitMeasures = computed(() => page.props.unitMeasures?.data || page.props.unitMeasures || [])
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
})
const defaultTransferRows = () => [createEmptyRow(), createEmptyRow(), createEmptyRow(), createEmptyRow()]

const form = useForm({
  date: '',
  from_warehouse_id: '',
  to_warehouse_id: '',
  selected_from_warehouse: null,
  selected_to_warehouse: null,
  transfer_cost: '',
  remarks: '',
  items: defaultTransferRows(),
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
}

watch(() => form.from_warehouse_id, (warehouseId) => {
  if (!warehouseId) {
    itemOptions.value = []
    return
  }
  loadItemOptions(warehouseId)
}, { immediate: true });

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
    return
  }
  row.available_measures = buildAvailableMeasures(selectedItem)
  row.selected_measure = selectedItem.unitMeasure || null
  row.item_id = selectedItem.id
  row.base_unit_price = selectedItem.avg_cost ?? selectedItem.purchase_price ?? selectedItem.unit_price ?? 0
  const baseUnit = Number(selectedItem.unitMeasure?.unit) || 1
  const selectedUnit = Number(row.selected_measure?.unit) || baseUnit
  row.unit_price = (row.base_unit_price / baseUnit) * selectedUnit
    row.selected_batch = null
    row.batch = ''
    row.expire_date = ''
}

function handleBatchChange(index, batch){
    const row = form.items[index]
    row.batch = batch?.batch
    row.on_hand = onhand(row)
    row.expire_date = batch?.expire_date
}

function onhand(index) {
  const item = form.items[index]
  if (!item || !item.selected_item) return ''
  const selected_item = item.selected_item
  const onHand = item?.selected_batch?.on_hand ?? item.selected_item.on_hand
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
    transfer_cost: form.transfer_cost,
    remarks: form.remarks,
    items: payloadItems,
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
</script>

<template>
  <AppLayout :title="t('general.create', { name: t('item_transfer.item_transfer') })" :sidebar-collapsed="true">
    <form @submit.prevent="handleSubmitAction">
      <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.create', { name: t('item_transfer.item_transfer') }) }}
        </div>
        <ModuleHelpButton module="transfer" />
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
            :error="form.errors?.from_warehouse_id"
            :searchable="true"
          />
          <NextSelect
            :options="warehouses"
            v-model="form.selected_to_warehouse"
            @update:modelValue="(value) => handleSelectChange('to_warehouse_id', value.id)"
            label-key="name"
            value-key="id"
            :reduce="warehouse => warehouse"
            :floating-text="t('item_transfer.to_warehouse')"
            :error="form.errors?.to_warehouse_id"
            :searchable="true"
            resource-type="warehouses"
            :search-fields="['name','code']"

          />
          <NextInput
            v-model="form.transfer_cost"
            type="number"
            step="any"
            inputmode="decimal"
            :error="form.errors?.transfer_cost"
            :label="t('item_transfer.transfer_cost')"
          />
          <NextTextarea
            v-model="form.remarks"
            :error="form.errors?.remarks"
            :label="t('general.remarks')"
          />
        </div>
      </div>

      <div class="rounded-xl border bg-card shadow-sm overflow-x-auto max-h-80">
        <table class="w-full table-fixed min-w-[900px] purchase-table border-separate">
          <thead class="bg-card sticky top-0 z-[200]">
            <tr class="text-muted-foreground font-semibold text-sm text-violet-500">
              <th class="px-1 py-1 w-5 min-w-5">#</th>
              <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }}</th>
              <th class="px-1 py-1 w-32">{{ t('general.batch') }}</th>
              <th class="px-1 py-1 w-36">{{ t('general.expire_date') }}</th>
              <th class="px-1 py-1 w-16">{{ t('general.qty') }}</th>
              <th class="px-1 py-1 w-24">{{ t('general.on_hand') }}</th>
              <th class="px-1 py-1 w-24">{{ t('general.unit') }}</th>
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
                disabled='true'
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
              <td class="text-center">{{ totalQuantity || 0 }}</td>
              <td></td>
              <td class="text-center"></td>
              <td class="text-center">{{ totalAmount || 0 }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <SubmitButtons
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
