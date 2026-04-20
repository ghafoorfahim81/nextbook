<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
  landedCost: {
    type: Object,
    required: true,
  },
});

const normalizeRecord = (value) => {
  if (!value) {
    return {};
  }

  const payload = value.data && typeof value.data === 'object'
    ? { ...value.data }
    : { ...value };

  const items = payload.items;
  payload.items = Array.isArray(items)
    ? items
    : Array.isArray(items?.data)
      ? items.data
      : [];

  return payload;
};

const record = ref(normalizeRecord(props.landedCost));

const refreshRecord = (value) => {
  record.value = normalizeRecord(value);
};

const statusClasses = computed(() => ({
  draft: 'border-amber-200 bg-amber-50 text-amber-700',
  allocated: 'border-blue-200 bg-blue-50 text-blue-700',
  posted: 'border-green-200 bg-green-50 text-green-700',
}[record.value?.status_id] || 'border-border bg-muted text-foreground'));

const canPost = computed(() => record.value?.status_id !== 'posted');
const canEdit = computed(() => record.value?.status_id !== 'posted');
const landedUnitCost = (row) => Number(
  row.quantity ? (row.item_cost_after / row.quantity) : (row.unit_cost || 0),
).toFixed(4);

const round = (value, precision = 2) => {
  const factor = 10 ** precision;
  return Math.round((Number(value) || 0) * factor) / factor;
};

const previewRows = computed(() => {
  const rows = Array.isArray(record.value?.items) ? record.value.items : [];
  const method = record.value?.allocation_method_id || 'by_value';
  const totalCost = Number(record.value?.total_cost || 0);

  const prepared = rows
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
    return rows.map((row) => ({
      ...row,
      landed_unit_cost: landedUnitCost(row),
    }));
  }

  let remaining = round(totalCost, 2);
  const lastIndex = prepared.length - 1;

  return prepared.map((row, index) => {
    const allocation = index === lastIndex
      ? round(remaining, 2)
      : round((round(totalCost, 2) * Number(row.basis_value || 0)) / basisTotal, 2);

    remaining = round(remaining - allocation, 2);

    const itemCostAfter = round(row.item_cost_before + allocation, 2);
    const landedUnit = row.quantity > 0
      ? round(itemCostAfter / row.quantity, 4)
      : round(row.unit_cost, 4);

    return {
      ...row,
      allocated_percentage: round((Number(row.basis_value || 0) / basisTotal) * 100, 4),
      allocated_amount: allocation,
      item_cost_after: itemCostAfter,
      landed_unit_cost: landedUnit,
    };
  });
});

const postLandedCost = async () => {
  if (!record.value?.id || !canPost.value) {
    return;
  }

  try {
    const { data } = await axios.post(`/api/landed-costs/${record.value.id}/post`);
    refreshRecord(data?.data);
    toast.success(t('general.completed_successfully', { resource: t('landed_cost.title') }));
  } catch (error) {
    toast.error(t('landed_cost.unable_to_post'));
  }
};

watch(() => props.landedCost, (value) => {
  if (value) {
    refreshRecord(value);
  }
}, { deep: true });

const totalLanded = computed(() => Number(
  previewRows.value.reduce((sum, row) => sum + Number(row.allocated_amount || 0), 0),
).toFixed(2));
</script>

<template>
  <AppLayout :title="t('landed_cost.title')">
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold">{{ t('landed_cost.title') }} #{{ record.number || record.id }}</h1>
          <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            <span>{{ t('general.date') }}: {{ record.date }}</span>
            <span>|</span>
            <span>{{ t('landed_cost.purchase_order') }}: {{ record.purchase_number || '-' }}</span>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            v-if="canEdit"
            type="button"
            class="rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted"
            @click="router.visit(route('landed-costs.edit', record.id))"
          >
            {{ t('general.edit', { name: t('landed_cost.title') }) }}
          </button>
          <button
            v-if="canPost"
            type="button"
            class="rounded-md border border-green-600 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50"
            @click="postLandedCost"
          >
            {{ t('landed_cost.post') }}
          </button>
          <button
            type="button"
            class="rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted"
            @click="router.visit(route('landed-costs.index'))"
          >
            {{ t('landed_cost.back') }}
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-card p-4 shadow-sm">
          <div class="text-xs text-muted-foreground">{{ t('general.status') }}</div>
          <div class="mt-1 inline-flex rounded-full border px-3 py-1 text-sm font-medium" :class="statusClasses">
            {{ record.status }}
          </div>
        </div>
        <div class="rounded-xl border bg-card p-4 shadow-sm">
          <div class="text-xs text-muted-foreground">{{ t('landed_cost.total_additional_cost') }}</div>
          <div class="mt-1 text-lg font-semibold tabular-nums">{{ Number(record.total_cost || 0).toFixed(2) }}</div>
        </div>
        <div class="rounded-xl border bg-card p-4 shadow-sm">
          <div class="text-xs text-muted-foreground">{{ t('landed_cost.allocated_amount') }}</div>
          <div class="mt-1 text-lg font-semibold tabular-nums">{{ totalLanded }}</div>
        </div>
        <div class="rounded-xl border bg-card p-4 shadow-sm">
          <div class="text-xs text-muted-foreground">{{ t('landed_cost.allocation_method') }}</div>
          <div class="mt-1 text-lg font-semibold">{{ record.allocation_method }}</div>
        </div>
      </div>

      <div class="rounded-xl border bg-card p-4 shadow-sm" v-if="record.notes">
        <div class="text-sm font-semibold text-violet-600">{{ t('landed_cost.notes') }}</div>
        <div class="mt-2 text-sm text-muted-foreground whitespace-pre-wrap">{{ record.notes }}</div>
      </div>

      <div class="rounded-xl border bg-card shadow-sm">
        <div class="border-b px-4 py-3">
          <h2 class="font-semibold text-violet-600">{{ t('landed_cost.items') }}</h2>
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
              <tr v-for="row in previewRows" :key="row.id" class="border-t">
                <td class="px-3 py-2">
                  {{ row.item_name || row.item?.name || row.item_id }}
                </td>
                <td class="px-3 py-2">{{ Number(row.unit_cost || 0).toFixed(2) }}</td>
                <td class="px-3 py-2">{{ Number(row.allocated_percentage || 0).toFixed(4) }}%</td>
                <td class="px-3 py-2">{{ Number(row.allocated_amount || 0).toFixed(2) }}</td>
                <td class="px-3 py-2">{{ landedUnitCost(row) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-muted/30">
              <tr>
                <td class="px-3 py-3 font-semibold">{{ t('general.total') }}</td>
                <td></td>
                <td></td>
                <td class="px-3 py-3 font-semibold">{{ totalLanded }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
