<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';
import { AlertCircle } from 'lucide-vue-next';
import { todayValueForCalendar } from '@/utils/dateDefaults';
import { useSidebar } from '@/Components/ui/sidebar/utils';

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
  item_name: row?.item_name || row?.item?.name || row?.selected_item?.name || '',
  item_code: row?.item_code || row?.item?.code || row?.selected_item?.code || '',
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
  items: (currentRecord.value?.items || []).map(normalizeItem),
});

const isPosted = computed(() => currentRecord.value?.status_id === 'posted');
const isEditMode = computed(() => !!currentRecord.value?.id);
const isManualAllocation = computed(() => form.allocation_method === 'manual');

const allocationMethodOptions = computed(() => props.allocationMethods.map((method) => ({
  id: method.id,
  name: method.name,
})));

const round = (value, precision = 2) => {
  const factor = 10 ** precision;
  return Math.round((Number(value) || 0) * factor) / factor;
};

const rowKey = (row) => `${row?.purchase_item_id || ''}:${row?.item_id || ''}`;

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
        manual: 0,
      }[method] ?? (quantity * unitCost);

      return {
        ...row,
        quantity,
        unit_cost: unitCost,
        item_cost_before: round(quantity * unitCost, 2),
        basis_value: basisValue,
      };
    });

  if (!prepared.length) {
    return {
      rows: [],
      allocated_total: 0,
    };
  }

  if (method === 'manual') {
    const rowsOut = prepared.map((row) => {
      const allocation = round(Number(row.allocated_amount || 0), 2);
      const itemCostAfter = round(row.item_cost_before + allocation, 2);

      return {
        ...row,
        allocated_percentage: totalCost > 0 ? round((allocation / totalCost) * 100, 4) : 0,
        allocated_amount: allocation,
        item_cost_after: itemCostAfter,
        landed_unit_cost: row.quantity > 0
          ? round(itemCostAfter / row.quantity, 4)
          : round(row.unit_cost, 4),
      };
    });

    return {
      rows: rowsOut,
      allocated_total: round(rowsOut.reduce((sum, row) => sum + Number(row.allocated_amount || 0), 0), 2),
    };
  }

  const basisTotal = prepared.reduce((sum, row) => sum + Number(row.basis_value || 0), 0);

  if (basisTotal <= 0) {
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
const previewByKey = computed(() => {
  const map = {};
  previewRows.value.forEach((row) => {
    map[rowKey(row)] = row;
  });
  return map;
});

const previewForRow = (row) => previewByKey.value[rowKey(row)];

const allocationDifference = computed(() => round(
  Number(form.total_cost || 0) - Number(previewAllocatedTotal.value || 0),
  2,
));

const allocationWarning = computed(() => {
  if (!form.items.length || Number(form.total_cost || 0) <= 0) {
    return null;
  }

  if (allocationDifference.value > 0.01) {
    return {
      type: 'under',
      message: t('landed_cost.allocation_not_fully_allocated', {
        amount: Math.abs(allocationDifference.value).toFixed(2),
      }),
    };
  }

  if (allocationDifference.value < -0.01) {
    return {
      type: 'over',
      message: t('landed_cost.allocation_exceeds_additional_cost', {
        amount: Math.abs(allocationDifference.value).toFixed(2),
      }),
    };
  }

  return null;
});

const canPost = computed(() => isEditMode.value && !isPosted.value && !allocationWarning.value);

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
  .filter((row) => !!row.item_id)
  .map((row) => {
    const preview = previewForRow(row);

    return {
      purchase_id: row.purchase_id || null,
      purchase_item_id: row.purchase_item_id || null,
      item_id: row.item_id || null,
      quantity: row.quantity,
      unit_cost: row.unit_cost,
      warehouse_id: row.warehouse_id || null,
      batch: row.batch,
      expire_date: row.expire_date || null,
      allocated_amount: preview?.allocated_amount ?? row.allocated_amount ?? 0,
      allocated_percentage: preview?.allocated_percentage ?? row.allocated_percentage ?? 0,
    };
  });

const buildPayload = (createAndNew = false) => ({
  date: form.date,
  purchase_id: form.purchase_ids[0] || form.purchase_id || null,
  purchase_ids: form.purchase_ids || [],
  total_cost: form.total_cost,
  allocation_method: form.allocation_method,
  notes: form.notes,
  items: prepareItemsPayload(),
  ...(createAndNew ? { create_and_new: true } : {}),
});

const submitRoute = computed(() => (props.submitMethod === 'post'
  ? route(props.submitRouteName)
  : route(props.submitRouteName, currentRecord.value?.id)));

const submitAction = ref(null);
const createLoading = computed(() => form.processing && submitAction.value === 'create');
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new');

const resetFormForCreate = () => {
  form.reset();
  form.clearErrors();
  form.date = todayValueForCalendar(calendarType.value);
  form.purchase_id = '';
  form.purchase_ids = [];
  form.selected_purchases = [];
  form.total_cost = '';
  form.allocation_method = 'by_value';
  form.notes = '';
  form.items = [];
  currentRecord.value = null;
};

const saveDraft = (createAndNew = false) => {
  if (isPosted.value) {
    return;
  }

  if (!form.items.length) {
    toast.error(t('landed_cost.select_purchase_first'));
    return;
  }

  submitAction.value = createAndNew ? 'create_and_new' : 'create';
  const payload = buildPayload(createAndNew);
  const request = form.transform(() => payload);

  const options = {
    onSuccess: () => {
      toast.success(t('general.success'), {
        description: isEditMode.value ? t('landed_cost.update_success') : t('landed_cost.save_success'),
      });

      if (createAndNew) {
        resetFormForCreate();
      }
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

const handleSubmitAction = (createAndNew = false) => {
  saveDraft(createAndNew === true);
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
      item_name: row.item_name || row.item?.name || '',
      item_code: row.item_code || row.item?.code || '',
      quantity: row.quantity,
      unit_cost: row.unit_price,
      warehouse_id: row.warehouse_id || purchaseData.warehouse_id || '',
      batch: row.batch,
      expire_date: row.expire_date,
      allocated_amount: 0,
    }));
  } catch (error) {
    toast.error(t('landed_cost.failed_to_load_purchase_items'));
    return [];
  }
};

const seedManualAllocations = (method = 'by_value') => {
  const preview = calculatePreviewRows(form.items, Number(form.total_cost || 0), method);
  form.items.forEach((row) => {
    const match = preview.rows.find((previewRow) => rowKey(previewRow) === rowKey(row));
    row.allocated_amount = match?.allocated_amount ?? 0;
  });
};

const loadSelectedPurchaseItems = async (purchases) => {
  const rows = await Promise.all((purchases || []).map((purchase) => fetchPurchaseItems(purchase)));
  const mergedRows = rows.flat().filter((row) => !!row.item_id);

  form.items = mergedRows;

  if (isManualAllocation.value && mergedRows.length) {
    seedManualAllocations('by_value');
  }
};

const selectPurchases = async (purchases) => {
  const selected = Array.isArray(purchases)
    ? purchases.map(normalizePurchase).filter((purchase) => purchase.id)
    : [];

  form.selected_purchases = selected;
  form.purchase_ids = selected.map((purchase) => purchase.id);
  form.purchase_id = form.purchase_ids[0] || '';

  if (selected.length === 0) {
    form.items = [];
    return;
  }

  await loadSelectedPurchaseItems(selected);
};

const postLandedCost = async () => {
  if (!currentRecord.value?.id) {
    toast.error(t('landed_cost.save_draft_first'));
    return;
  }

  if (allocationWarning.value) {
    toast.error(allocationWarning.value.message);
    return;
  }

  try {
    const { data } = await axios.post(`/api/landed-costs/${currentRecord.value.id}/post`);
    setRecordFromResponse(data?.data);
    toast.success(t('general.completed_successfully', { resource: t('landed_cost.title') }));
  } catch (error) {
    const message = error?.response?.data?.message
      || error?.response?.data?.errors?.allocated_total?.[0]
      || error?.response?.data?.errors?.items?.[0]
      || t('landed_cost.unable_to_post');
    toast.error(message);
  }
};

const rowTotal = (row) => (Number(row.quantity) || 0) * (Number(row.unit_cost) || 0);
const totalRowCost = computed(() => form.items.reduce((sum, row) => sum + rowTotal(row), 0));
const totalQuantity = computed(() => form.items.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0));

watch(() => form.allocation_method, (method, previous) => {
  if (method === 'manual' && previous && previous !== 'manual') {
    seedManualAllocations(previous);
  }
});

watch(() => props.landedCost, (value) => {
  const normalized = normalizeRecord(value);

  if (!normalized) {
    return;
  }

  setRecordFromResponse(normalized);
}, { deep: true });

let sidebar = null;
try {
  sidebar = useSidebar();
} catch (e) {
  sidebar = null;
}
const prevSidebarOpen = ref(true);

onMounted(() => {
  if (sidebar) {
    prevSidebarOpen.value = sidebar.open.value;
    sidebar.setOpen(false);
  }

  if (props.landedCost) {
    setRecordFromResponse(normalizeRecord(props.landedCost));
  }
});

onUnmounted(() => {
  if (sidebar) {
    sidebar.setOpen(prevSidebarOpen.value);
  }
});
</script>

<template>
  <AppLayout :title="pageTitle" :sidebar-collapsed="true">
    <FormPageToolbar back-route="landed-costs.index" module="landed_cost" />
    <form @submit.prevent="handleSubmitAction(false)">
      <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
          {{ pageTitle }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextDate
            v-model="form.date"
            :current-date="true"
            :disabled="isPosted"
            :error="form.errors?.date"
            :label="t('landed_cost.date')"
          />
          <NextSelect
            autofocus
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
            :error="form.errors?.purchase_ids || form.errors?.purchase_id"
          />
          <NextInput
            v-model="form.total_cost"
            type="number"
            step="any"
            :disabled="isPosted"
            :error="form.errors?.total_cost"
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
            :error="form.errors?.allocation_method"
          />
          <NextTextarea
            v-model="form.notes"
            :disabled="isPosted"
            :label="t('landed_cost.notes')"
            :error="form.errors?.notes"
            rows="2"
            class="md:col-span-2"
          />
        </div>
      </div>

      <div class="rounded-xl border border-violet-500 bg-card shadow-sm overflow-x-auto">
        <div class="p-4 border-b">
          <h3 class="font-semibold text-violet-500">{{ t('landed_cost.items') }}</h3>
          <p v-if="form.errors?.items" class="mt-1 text-sm text-destructive">{{ form.errors.items }}</p>
        </div>
        <table class="w-full table-fixed min-w-[1100px] purchase-table border-separate">
          <thead class="bg-card sticky top-0 z-[200]">
            <tr class="text-muted-foreground font-semibold text-sm text-violet-500">
              <th class="px-1 py-1 w-8">#</th>
              <th class="px-1 py-1 w-28">{{ t('landed_cost.purchase_order') }}</th>
              <th class="px-1 py-1 min-w-28">{{ t('landed_cost.item') }}</th>
              <th class="px-1 py-1 w-20">{{ t('general.qty') }}</th>
              <th class="px-1 py-1 w-24">{{ t('general.unit_price') }}</th>
              <th class="px-1 py-1 w-24">{{ t('general.batch') }}</th>
              <th class="px-1 py-1 w-28">{{ t('general.expire_date') }}</th>
              <th class="px-1 py-1 w-24">{{ t('landed_cost.line_total') }}</th>
              <th class="px-1 py-1 w-24">{{ t('landed_cost.allocated_percentage') }}</th>
              <th class="px-1 py-1 w-28">{{ t('landed_cost.allocated_amount') }}</th>
              <th class="px-1 py-1 w-28">{{ t('landed_cost.landed_unit_cost') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!form.items.length">
              <td colspan="11" class="px-4 py-8 text-center text-sm text-muted-foreground">
                {{ t('landed_cost.select_purchase_first') }}
              </td>
            </tr>
            <tr
              v-for="(row, index) in form.items"
              :key="row.purchase_item_id || `${row.item_id}-${index}`"
              class="hover:bg-muted/40 transition-colors"
            >
              <td class="px-1 py-2 align-middle text-center">{{ index + 1 }}</td>
              <td class="px-1 py-2 align-middle text-sm font-medium">
                {{ row.purchase_number || '-' }}
              </td>
              <td class="px-1 py-2 align-middle">
                <div class="text-sm font-medium">{{ row.item_name || row.selected_item?.name || '-' }}</div>
                <div v-if="row.item_code || row.selected_item?.code" class="text-xs text-muted-foreground">
                  {{ row.item_code || row.selected_item?.code }}
                </div>
              </td>
              <td class="px-1 py-2 align-middle text-center tabular-nums">{{ Number(row.quantity || 0) }}</td>
              <td class="px-1 py-2 align-middle text-center tabular-nums">{{ Number(row.unit_cost || 0).toFixed(2) }}</td>
              <td class="px-1 py-2 align-middle text-center">{{ row.batch || '-' }}</td>
              <td class="px-1 py-2 align-middle text-center">{{ row.expire_date || '-' }}</td>
              <td class="px-1 py-2 align-middle text-center tabular-nums">{{ rowTotal(row).toFixed(2) }}</td>
              <td class="px-1 py-2 align-middle text-center tabular-nums">
                {{ Number(previewForRow(row)?.allocated_percentage ?? 0).toFixed(2) }}%
              </td>
              <td class="px-1 py-2 align-middle">
                <NextInput
                  v-if="isManualAllocation && !isPosted"
                  v-model="row.allocated_amount"
                  type="number"
                  step="any"
                  inputmode="decimal"
                />
                <span v-else class="block text-center tabular-nums">
                  {{ Number(previewForRow(row)?.allocated_amount ?? row.allocated_amount ?? 0).toFixed(2) }}
                </span>
              </td>
              <td class="px-1 py-2 align-middle text-center tabular-nums">
                {{ Number(previewForRow(row)?.landed_unit_cost ?? row.unit_cost ?? 0).toFixed(4) }}
              </td>
            </tr>
          </tbody>
          <tfoot v-if="form.items.length" class="sticky bottom-0 bg-card">
            <tr class="bg-violet-500/10 font-semibold">
              <td></td>
              <td></td>
              <td class="px-1 py-3 text-center">{{ form.items.length }}</td>
              <td class="px-1 py-3 text-center">{{ totalQuantity }}</td>
              <td></td>
              <td></td>
              <td></td>
              <td class="px-1 py-3 text-center">{{ totalRowCost.toFixed(2) }}</td>
              <td></td>
              <td class="px-1 py-3 text-center">{{ Number(previewAllocatedTotal || 0).toFixed(2) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <Alert
        v-if="allocationWarning"
        variant="destructive"
        class="mt-4 border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
      >
        <div class="flex items-start gap-2">
          <AlertCircle class="h-4 w-4 mt-0.5 text-amber-600" />
          <div>
            <AlertTitle>{{ t('landed_cost.allocation_mismatch_title') }}</AlertTitle>
            <AlertDescription class="mt-1">
              {{ allocationWarning.message }}
            </AlertDescription>
          </div>
        </div>
      </Alert>

      <div class="mt-3 flex flex-wrap items-center gap-2">
        <SubmitButtons
          :create-label="isEditMode ? t('general.update') : t('general.create', { name: t('landed_cost.title') })"
          :create-and-new-label="t('general.create_and_new')"
          :cancel-label="t('general.cancel')"
          :creating-label="isEditMode ? t('general.updating', { name: t('landed_cost.title') }) : t('general.creating', { name: t('landed_cost.title') })"
          :create-loading="createLoading"
          :create-and-new-loading="createAndNewLoading"
          :show-create-and-new="!isEditMode"
          :disabled="isPosted"
          @create-and-new="handleSubmitAction(true)"
          @cancel="() => router.visit(route('landed-costs.index'))"
        />
        <button
          v-if="isEditMode && !isPosted"
          type="button"
          class="rounded-md border border-green-600 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50 disabled:opacity-50"
          :disabled="!canPost"
          @click="postLandedCost"
        >
          {{ t('landed_cost.post') }}
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
