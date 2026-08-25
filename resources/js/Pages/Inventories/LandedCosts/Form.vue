<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
import { computed, onMounted, ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';
import { Plus, Trash2 } from 'lucide-vue-next';
import { todayValueForCalendar } from '@/utils/dateDefaults';
import { usePage } from '@inertiajs/vue3';

const { t } = useI18n();
const page = usePage();
const calendarType = computed(() => page.props.auth?.user?.calendar_type || 'gregorian');

const props = defineProps({
  allocationMethods: { type: Array, required: true },
  landedCost: { type: Object, default: null },
  purchases: { type: Array, default: () => [] },
  pageTitle: { type: String, required: true },
  submitRouteName: { type: String, required: true },
  submitMethod: { type: String, required: true },
});

const normalizeRecord = (value) => {
  if (!value) {
    return null;
  }

  const payload = value.data && typeof value.data === 'object'
    ? { ...value.data }
    : { ...value };

  const normalizeCollection = (collection) => {
    if (Array.isArray(collection)) {
      return collection;
    }

    if (Array.isArray(collection?.data)) {
      return collection.data;
    }

    return [];
  };

  payload.items = normalizeCollection(payload.items);
  payload.purchases = normalizeCollection(payload.purchases);
  payload.purchase_ids = Array.isArray(payload.purchase_ids) ? payload.purchase_ids : [];
  payload.purchase_numbers = Array.isArray(payload.purchase_numbers) ? payload.purchase_numbers : [];

  return payload;
};

const blankRow = () => ({
  purchase_id: '',
  purchase_number: '',
  purchase_item_id: '',
  item_id: '',
  selected_item: null,
  quantity: '',
  unit_cost: '',
  warehouse_id: '',
  batch: '',
  expire_date: '',
});

const normalizeItem = (row) => ({
  purchase_id: row?.purchase_id || row?.purchase_item?.purchase_id || '',
  purchase_number: row?.purchase_number || row?.purchase_item?.purchase?.number || '',
  purchase_item_id: row?.purchase_item_id || row?.purchase_item?.id || '',
  item_id: row?.item_id || row?.item?.id || '',
  selected_item: row?.item_name
    ? { id: row?.item_id, name: row?.item_name, code: row?.item_code }
    : (row?.item || null),
  quantity: row?.quantity ?? '',
  unit_cost: row?.unit_cost ?? '',
  warehouse_id: row?.warehouse_id ?? '',
  batch: row?.batch ?? '',
  expire_date: row?.expire_date ?? '',
  item_name: row?.item_name || row?.item?.name || '',
  item_code: row?.item_code || row?.item?.code || '',
  allocated_percentage: row?.allocated_percentage ?? 0,
  allocated_amount: row?.allocated_amount ?? 0,
  item_cost_before: row?.item_cost_before ?? 0,
  item_cost_after: row?.item_cost_after ?? 0,
});

const normalizePurchase = (purchase) => ({
  id: purchase?.id || '',
  number: purchase?.number || '',
  supplier_name: purchase?.supplier_name || purchase?.supplier?.name || '',
  name: purchase?.name || `#${purchase?.number || ''}${purchase?.supplier_name || purchase?.supplier?.name ? ` - ${purchase?.supplier_name || purchase?.supplier?.name}` : ''}`,
});

const getItemOnHand = (item) => Number(
  item?.on_hand
    ?? item?.onHand
    ?? item?.quantity
    ?? 0,
);

const getItemAvgCost = (item) => Number(
  item?.avg_cost
    ?? item?.average_cost
    ?? item?.cost
    ?? item?.purchase_price
    ?? 0,
);

const autoFillManualItemValues = (item) => {
  if ((form.purchase_ids || []).length) {
    return {
      quantity: '',
      unit_cost: '',
    };
  }

  return {
    quantity: getItemOnHand(item),
    unit_cost: getItemAvgCost(item),
  };
};

const handleItemSelection = (row, item) => {
  row.selected_item = item || null;
  row.item_id = item?.id || '';
  row.purchase_item_id = '';
  row.purchase_id = '';
  row.purchase_number = '';

  if (!item) {
    row.quantity = '';
    row.unit_cost = '';
    row.warehouse_id = '';
    row.batch = '';
    row.expire_date = '';
    return;
  }

  const autoFill = autoFillManualItemValues(item);
  row.quantity = autoFill.quantity;
  row.unit_cost = autoFill.unit_cost;
};

const currentRecord = ref(normalizeRecord(props.landedCost));

const defaultPurchases = computed(() => {
  const recordPurchases = Array.isArray(currentRecord.value?.purchases) ? currentRecord.value.purchases : [];
  if (recordPurchases.length > 0) {
    return recordPurchases.map(normalizePurchase);
  }

  const recordPurchaseIds = Array.isArray(currentRecord.value?.purchase_ids) ? currentRecord.value.purchase_ids : [];
  if (recordPurchaseIds.length === 0) {
    return [];
  }

  return props.purchases
    .filter((purchase) => recordPurchaseIds.includes(purchase.id))
    .map(normalizePurchase);
});

const form = useForm({
  date: currentRecord.value?.date || todayValueForCalendar(calendarType.value),
  purchase_id: currentRecord.value?.purchase_id || defaultPurchases.value[0]?.id || '',
  purchase_ids: Array.isArray(currentRecord.value?.purchase_ids) ? currentRecord.value.purchase_ids : defaultPurchases.value.map((purchase) => purchase.id),
  selected_purchases: defaultPurchases.value,
  total_cost: currentRecord.value?.total_cost || '',
  allocation_method: currentRecord.value?.allocation_method_id || 'by_value',
  notes: currentRecord.value?.notes || '',
  items: (currentRecord.value?.items || []).length > 0 ? (currentRecord.value.items || []).map(normalizeItem) : [blankRow()],
});

const isPosted = computed(() => currentRecord.value?.status_id === 'posted');
const canEditItems = computed(() => !isPosted.value);
const isEditMode = computed(() => !!currentRecord.value?.id);

const allocationMethodOptions = computed(() => props.allocationMethods.map((method) => ({
  id: method.id,
  name: method.name,
})));

const round = (value, precision = 2) => {
  const factor = 10 ** precision;
  return Math.round((Number(value) || 0) * factor) / factor;
};

const calculatePreviewRows = (rows, totalCost, method) => {
  const prepared = (rows || [])
    .filter((row) => !!row?.item_id)
    .map((row) => {
      const quantity = Number(row.quantity) || 0;
      const unitCost = Number(row.unit_cost) || 0;
      const weight = Number(row.weight) || 0;
      const volume = Number(row.volume) || 0;

      const basisValue = {
        by_quantity: quantity,
        by_weight: weight > 0 ? weight : quantity,
        by_volume: volume > 0 ? volume : quantity,
        by_value: quantity * unitCost,
      }[method] ?? (quantity * unitCost);

      return {
        ...row,
        quantity,
        unit_cost: unitCost,
        item_cost_before: round(quantity * unitCost, 2),
        basis_value: basisValue,
      };
    });

  const basisTotal = prepared.reduce((sum, row) => sum + Number(row.basis_value || 0), 0);

  if (!prepared.length || basisTotal <= 0) {
    return {
      rows: [],
      allocated_total: 0,
    };
  }

  let remaining = round(totalCost, 2);
  let allocatedTotal = 0;
  const lastIndex = prepared.length - 1;

  const rowsOut = prepared.map((row, index) => {
    const allocation = index === lastIndex
      ? round(remaining, 2)
      : round((round(totalCost, 2) * Number(row.basis_value || 0)) / basisTotal, 2);

    remaining = round(remaining - allocation, 2);
    allocatedTotal = round(allocatedTotal + allocation, 2);

    const itemCostAfter = round(row.item_cost_before + allocation, 2);
    const landedUnitCost = row.quantity > 0
      ? round(itemCostAfter / row.quantity, 4)
      : round(row.unit_cost, 4);

    return {
      ...row,
      allocated_percentage: round((Number(row.basis_value || 0) / basisTotal) * 100, 4),
      allocated_amount: allocation,
      item_cost_after: itemCostAfter,
      landed_unit_cost: landedUnitCost,
    };
  });

  return {
    rows: rowsOut,
    allocated_total: allocatedTotal,
  };
};

const previewState = computed(() => calculatePreviewRows(
  form.items,
  Number(form.total_cost || 0),
  form.allocation_method,
));

const previewRows = computed(() => previewState.value.rows);
const previewAllocatedTotal = computed(() => previewState.value.allocated_total);
const previewForRow = (row) => previewRows.value.find((previewRow) => (
  previewRow.purchase_item_id === row.purchase_item_id
    && previewRow.item_id === row.item_id
    && Number(previewRow.quantity || 0) === Number(row.quantity || 0)
    && Number(previewRow.unit_cost || 0) === Number(row.unit_cost || 0)
));

const setRecordFromResponse = (data) => {
  currentRecord.value = data;
  form.date = data.date || form.date;
  const responsePurchases = Array.isArray(data.purchases) ? data.purchases.map(normalizePurchase) : [];
  const responsePurchaseIds = Array.isArray(data.purchase_ids) && data.purchase_ids.length > 0
    ? data.purchase_ids
    : responsePurchases.map((purchase) => purchase.id);

  form.purchase_id = data.purchase_id || responsePurchaseIds[0] || '';
  form.purchase_ids = responsePurchaseIds;
  form.selected_purchases = Array.isArray(data.purchases)
    ? responsePurchases
    : props.purchases.filter((purchase) => responsePurchaseIds.includes(purchase.id)).map(normalizePurchase);
  form.total_cost = data.total_cost || '';
  form.allocation_method = data.allocation_method_id || 'by_value';
  form.notes = data.notes || '';
  form.items = (data.items || []).map(normalizeItem);
};

const prepareItemsPayload = () => form.items
  .map((row) => ({
    purchase_id: row.purchase_id || row.purchase_item?.purchase_id || null,
    purchase_item_id: row.purchase_item_id || null,
    item_id: row.selected_item?.id || row.item_id || null,
    quantity: row.quantity,
    unit_cost: row.unit_cost,
    warehouse_id: row.warehouse_id || null,
    batch: row.batch,
    expire_date: row.expire_date || null,
  }))
  .filter((row) => !!row.item_id);

const buildPayload = () => ({
  date: form.date,
  purchase_id: form.purchase_ids[0] || form.purchase_id || null,
  purchase_ids: form.purchase_ids || [],
  total_cost: form.total_cost,
  allocation_method: form.allocation_method,
  notes: form.notes,
  items: prepareItemsPayload(),
});

const submitRoute = computed(() => (props.submitMethod === 'post'
  ? route(props.submitRouteName)
  : route(props.submitRouteName, currentRecord.value?.id)));

const saveDraft = () => {
  const payload = buildPayload();

  const request = form.transform(() => payload);

  const options = {
    onSuccess: () => {
      toast.success(t('general.success'), {
        description: isEditMode.value ? t('landed_cost.update_success') : t('landed_cost.save_success'),
      });
    },
    onError: () => {
      toast.error(t('general.error'), {
        description: isEditMode.value
          ? t('general.update_error', { name: t('landed_cost.title') })
          : t('general.create_error', { name: t('landed_cost.title') }),
      });
    },
  };

  if (props.submitMethod === 'post') {
    request.post(submitRoute.value, options);
  } else {
    request.put(submitRoute.value, options);
  }
};

const fetchPurchaseItems = async (purchase) => {
  if (!purchase?.id) {
    return [];
  }

  try {
    const response = await axios.get(route('purchases.show', purchase.id));
    const purchaseData = response.data?.data;

    if (!purchaseData) {
      return [];
    }

    return (purchaseData.items || []).map((row) => ({
      purchase_id: purchase.id,
      purchase_number: purchaseData.number || purchase.number || '',
      purchase_item_id: row.id,
      item_id: row.item_id,
      selected_item: row.item || { id: row.item_id, name: row.item_name, code: row.item_code },
      quantity: row.quantity,
      unit_cost: row.unit_price,
      warehouse_id: row.warehouse_id,
      batch: row.batch,
      expire_date: row.expire_date,
    }));
  } catch (error) {
    toast.error(t('landed_cost.failed_to_load_purchase_items'));
    return [];
  }
};

const loadSelectedPurchaseItems = async (purchases) => {
  const rows = await Promise.all((purchases || []).map((purchase) => fetchPurchaseItems(purchase)));
  const mergedRows = rows.flat().filter((row) => !!row.item_id);

  form.items = mergedRows.length > 0 ? mergedRows : [blankRow()];
};

const addRow = () => {
  if (!canEditItems.value) {
    return;
  }

  form.items.push(blankRow());
};

const removeRow = (index) => {
  if (!canEditItems.value || form.items.length <= 1) {
    return;
  }

  form.items.splice(index, 1);
};

const selectPurchases = async (purchases) => {
  const selected = Array.isArray(purchases)
    ? purchases.map(normalizePurchase).filter((purchase) => purchase.id)
    : [];

  form.selected_purchases = selected;
  form.purchase_ids = selected.map((purchase) => purchase.id);
  form.purchase_id = form.purchase_ids[0] || '';

  if (selected.length === 0) {
    form.items = [blankRow()];
    return;
  }

  await loadSelectedPurchaseItems(selected);
};

const calculateAllocation = async () => {
  if (!currentRecord.value?.id) {
    toast.error(t('landed_cost.save_draft_first'));
    return;
  }

    try {
      const payload = buildPayload();
      const { data } = await axios.post(`/api/landed-costs/${currentRecord.value.id}/allocate`, payload);
      setRecordFromResponse(data?.data);
      toast.success(t('landed_cost.preview_updated'));
    } catch (error) {
      toast.error(t('landed_cost.unable_to_calculate'));
    }
  };

const postLandedCost = async () => {
  if (!currentRecord.value?.id) {
    toast.error(t('landed_cost.save_draft_first'));
    return;
  }

  try {
    const { data } = await axios.post(`/api/landed-costs/${currentRecord.value.id}/post`);
    setRecordFromResponse(data?.data);
    toast.success(t('general.completed_successfully', { resource: t('landed_cost.title') }));
  } catch (error) {
    toast.error(t('landed_cost.unable_to_post'));
  }
};

const rowTotal = (row) => (Number(row.quantity) || 0) * (Number(row.unit_cost) || 0);
const totalRowCost = computed(() => form.items.reduce((sum, row) => sum + rowTotal(row), 0));

watch(() => props.landedCost, (value) => {
  const normalized = normalizeRecord(value);

  if (!normalized) {
    return;
  }

  setRecordFromResponse(normalized);
}, { deep: true });

onMounted(() => {
  if (props.landedCost) {
    setRecordFromResponse(normalizeRecord(props.landedCost));
  }
});
</script>

<template>
  <AppLayout :title="pageTitle">
    <form @submit.prevent="saveDraft" class="space-y-6">
      <div class="rounded-xl border border-violet-500 bg-card p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <NextDate
            v-model="form.date"
            :current-date="true"
            :disabled="isPosted"
            :label="t('landed_cost.date')"
          />
          <NextSelect
            :options="purchases"
            v-model="form.selected_purchases"
            @update:modelValue="selectPurchases"
            label-key="name"
            value-key="id"
            :reduce="purchase => purchase"
            :multiple="true"
            :close-on-select="false"
            :floating-text="t('landed_cost.purchase_order')"
            :searchable="true"
            resource-type="purchases"
            :disabled="isPosted"
          />
          <NextInput
            v-model="form.total_cost"
            type="number"
            step="any"
            :disabled="isPosted"
            :label="t('landed_cost.total_additional_cost')"
          />
          <NextSelect
            :options="allocationMethodOptions"
            v-model="form.allocation_method"
            label-key="name"
            value-key="id"
            :reduce="method => method.id"
            :floating-text="t('landed_cost.allocation_method')"
            :disabled="isPosted"
          />
          <NextTextarea
            v-model="form.notes"
            :disabled="isPosted"
            :label="t('landed_cost.notes')"
            rows="2"
            class="md:col-span-2"
          />
        </div>
      </div>

      <div class="rounded-xl border border-violet-500 bg-card shadow-sm">
        <div class="flex items-center justify-between border-b px-4 py-3">
          <div class="font-semibold text-violet-500">{{ t('landed_cost.items') }}</div>
          <button
            v-if="!isPosted"
            type="button"
            class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted"
            @click="addRow"
          >
            <Plus class="h-4 w-4" />
            {{ t('landed_cost.add_row') }}
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-[1050px] w-full">
            <thead class="bg-muted/40 text-sm text-muted-foreground">
              <tr>
                <th class="px-3 py-2 text-left">#</th>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.purchase_order') }}</th>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.item') }}</th>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.quantity') }}</th>
                <th class="px-3 py-2 text-left">{{ t('general.unit_price') }}</th>
                <th class="px-3 py-2 text-left">{{ t('general.batch') }}</th>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.date') }}</th>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.line_total') }}</th>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.landed_unit_cost') }}</th>
                <th class="px-3 py-2 text-left"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in form.items" :key="index" class="border-t">
                <td class="px-3 py-2 align-top">{{ index + 1 }}</td>
                <td class="px-3 py-2 align-top min-w-[180px]">
                  <div class="text-sm font-medium">
                    {{ row.purchase_number || row.purchase_item?.purchase?.number || '-' }}
                  </div>
                </td>
                <td class="px-3 py-2 align-top min-w-[260px]">
                  <NextSelect
                    :options="[]"
                    v-model="row.selected_item"
                    @update:modelValue="(value) => handleItemSelection(row, value)"
                    label-key="name"
                    value-key="id"
                    :reduce="item => item"
                    :searchable="true"
                    resource-type="items"
                    :search-fields="['name', 'code', 'generic_name', 'packing', 'barcode']"
                    :disabled="!canEditItems"
                  />
                </td>
                <td class="px-3 py-2 align-top">
                  <NextInput v-model="row.quantity" type="number" step="any" :disabled="!canEditItems" />
                </td>
                <td class="px-3 py-2 align-top">
                  <NextInput v-model="row.unit_cost" type="number" step="any" :disabled="!canEditItems" />
                </td>
                <td class="px-3 py-2 align-top">
                  <NextInput v-model="row.batch" :disabled="!canEditItems" />
                </td>
                <td class="px-3 py-2 align-top">
                  <NextDate v-model="row.expire_date" :disabled="!canEditItems" />
                </td>
                <td class="px-3 py-2 align-top">
                  {{ rowTotal(row).toFixed(2) }}
                </td>
                <td class="px-3 py-2 align-top">
                  {{ Number(previewForRow(row)?.landed_unit_cost ?? row.unit_cost ?? 0).toFixed(4) }}
                </td>
                <td class="px-3 py-2 align-top">
                  <button
                    v-if="!isPosted"
                    type="button"
                    class="text-red-500 hover:text-red-700"
                    :disabled="!canEditItems"
                    @click="removeRow(index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </button>
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-muted/30">
              <tr>
                <td colspan="7" class="px-3 py-3 text-right font-semibold">{{ t('landed_cost.line_total') }}</td>
                <td class="px-3 py-3 font-semibold">{{ totalRowCost.toFixed(2) }}</td>
                <td></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="rounded-xl border border-violet-500 bg-card shadow-sm">
        <div class="flex items-center justify-between border-b px-4 py-3">
          <div class="font-semibold text-violet-500">{{ t('landed_cost.allocation_preview') }}</div>
          <div class="flex items-center gap-2">
            <button
              v-if="isEditMode && !isPosted"
              type="button"
              class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
              @click="calculateAllocation"
            >
              {{ t('landed_cost.calculate_allocation') }}
            </button>
            <button
              v-if="isEditMode && !isPosted"
              type="button"
              class="rounded-md border border-green-600 px-3 py-2 text-sm text-green-700 hover:bg-green-50"
              @click="postLandedCost"
            >
              {{ t('landed_cost.post') }}
            </button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-[1000px] w-full">
            <thead class="bg-muted/40 text-sm text-muted-foreground">
              <tr>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.item') }}</th>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.base_unit_cost') }}</th>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.allocated_percentage') }}</th>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.allocated_amount') }}</th>
                <th class="px-3 py-2 text-left">{{ t('landed_cost.landed_unit_cost') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in previewRows" :key="`${row.item_id}-${row.purchase_item_id || ''}`" class="border-t">
                <td class="px-3 py-2">{{ row.item_name || row.selected_item?.name || row.item_id }}</td>
                <td class="px-3 py-2">{{ Number(row.unit_cost || 0).toFixed(2) }}</td>
                <td class="px-3 py-2">{{ Number(row.allocated_percentage || 0).toFixed(4) }}%</td>
                <td class="px-3 py-2">{{ Number(row.allocated_amount || 0).toFixed(2) }}</td>
                <td class="px-3 py-2">{{ Number(row.landed_unit_cost || 0).toFixed(4) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-muted/30">
              <tr>
                <td class="px-3 py-3 font-semibold">{{ t('general.total') }}</td>
                <td></td>
                <td></td>
                <td class="px-3 py-3 font-semibold">{{ Number(previewAllocatedTotal || 0).toFixed(2) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <button
          type="submit"
          class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
          :disabled="form.processing || isPosted"
        >
          {{ isEditMode ? t('landed_cost.update_draft') : t('landed_cost.save_draft') }}
        </button>
        <button
          type="button"
          class="rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted"
          @click="() => router.visit(route('landed-costs.index'))"
        >
          {{ t('landed_cost.back') }}
        </button>
      </div>
    </form>
  </AppLayout>
</template>
