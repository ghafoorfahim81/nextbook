<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { useToast } from '@/Components/ui/toast/use-toast';
import { useAuth } from '@/composables/useAuth';
import {
  ArrowLeft,
  ArrowLeftRight,
  Banknote,
  Calendar,
  Coins,
  DollarSign,
  FileText,
  Layers,
  Package2,
  Scale,
  SquarePen,
  StickyNote,
  Truck,
  User,
} from 'lucide-vue-next';

const { t } = useI18n();
const { toast } = useToast();
const { can } = useAuth();
const page = usePage();

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
  payload.category_allocations = normalizeCollection(payload.category_allocations);
  payload.purchase_numbers = Array.isArray(payload.purchase_numbers) ? payload.purchase_numbers : [];
  payload.purchase_ids = Array.isArray(payload.purchase_ids) ? payload.purchase_ids : [];

  return payload;
};

const record = ref(normalizeRecord(props.landedCost));

const refreshRecord = (value) => {
  record.value = normalizeRecord(value);
};

watch(() => props.landedCost, (value) => {
  if (value) {
    refreshRecord(value);
  }
}, { deep: true });

const formatAmount = (value) => Number(value || 0).toFixed(2);
const formatUnit = (value) => Number(value || 0).toFixed(4);

const statusBadgeClasses = computed(() => ({
  draft: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
  allocated: 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
  posted: 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300',
}[record.value?.status_id] || 'border-border bg-muted text-foreground'));

const canEdit = computed(() => record.value?.status_id !== 'posted' && can('landed_costs.update'));

const purchaseNumbersLabel = computed(() => {
  const numbers = record.value?.purchase_numbers?.length
    ? record.value.purchase_numbers
    : (record.value?.purchase_number ? [record.value.purchase_number] : []);

  return numbers.length ? numbers.join(', ') : '-';
});

// Prefer the value the API derived; fall back to the local division so a row
// saved before landed_unit_cost existed still renders something meaningful.
const landedUnitCost = (row) => {
  if (row?.landed_unit_cost !== undefined && row?.landed_unit_cost !== null) {
    return Number(row.landed_unit_cost);
  }

  return Number(row?.quantity ? (row.item_cost_after / row.quantity) : (row?.unit_cost || 0));
};

const previewRows = computed(() => (record.value?.items || []).map((row) => ({
  ...row,
  landed_unit_cost: landedUnitCost(row),
})));

const totalLanded = computed(() =>
  previewRows.value.reduce((sum, row) => sum + Number(row.allocated_amount || 0), 0)
);

const totalBaseCost = computed(() =>
  previewRows.value.reduce((sum, row) => sum + Number(row.item_cost_before || 0), 0)
);

const totalLandedCost = computed(() =>
  previewRows.value.reduce((sum, row) => sum + Number(row.item_cost_after || 0), 0)
);

const totalQuantity = computed(() =>
  previewRows.value.reduce((sum, row) => sum + Number(row.quantity || 0), 0)
);

const categoryRows = computed(() => record.value?.category_allocations || []);
const categoryTotal = computed(() =>
  categoryRows.value.reduce((sum, row) => sum + Number(row.amount || 0), 0)
);

const currency = computed(() => record.value?.currency ?? null);
const currencySymbol = computed(() => currency.value?.symbol || '');
const currencyLabel = computed(() =>
  [currency.value?.code, currency.value?.name].filter(Boolean).join(' — ') || '-'
);

const exchangeRate = computed(() => Number(record.value?.rate ?? 1));
// Base currency posts at rate 1; only a foreign currency needs the rate and the
// home-currency equivalent spelled out.
const isForeignCurrency = computed(() => exchangeRate.value !== 1 && !currency.value?.is_base_currency);
const baseCurrencyCode = computed(() => page.props?.homeCurrency?.code || '');
const formattedRate = computed(() => exchangeRate.value.toLocaleString(undefined, { maximumFractionDigits: 4 }));
const formattedBaseTotal = computed(() => (Number(record.value?.total_cost || 0) * exchangeRate.value).toFixed(2));

const allocationDifference = computed(() => Number(record.value?.total_cost || 0) - totalLanded.value);

const isFullyAllocated = computed(() => Math.abs(allocationDifference.value) <= 0.01);

const canPost = computed(() =>
  record.value?.status_id !== 'posted' && can('landed_costs.post') && isFullyAllocated.value
);

const posting = ref(false);

const postLandedCost = async () => {
  if (!record.value?.id || !canPost.value || posting.value) {
    return;
  }

  posting.value = true;

  try {
    const { data } = await axios.post(`/api/landed-costs/${record.value.id}/post`);
    refreshRecord(data?.data);
    toast({
      title: t('general.success'),
      description: t('general.completed_successfully', { resource: t('landed_cost.title') }),
      variant: 'success',
      class: 'bg-green-600 text-white',
    });
  } catch (error) {
    toast({
      title: t('general.error'),
      description: t('landed_cost.unable_to_post'),
      variant: 'destructive',
      class: 'bg-red-600 text-white',
    });
  } finally {
    posting.value = false;
  }
};
</script>

<template>
  <AppLayout :title="`${t('landed_cost.title')} #${record.number || record.id}`">
    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <Button variant="outline" size="sm" @click="router.visit(route('landed-costs.index'))">
          <ArrowLeft class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
          {{ t('general.back') }}
        </Button>
        <div class="flex flex-wrap items-center gap-2">
          <Button
            v-if="canPost"
            size="sm"
            class="bg-green-600 text-white hover:bg-green-700"
            :disabled="posting"
            @click="postLandedCost"
          >
            <Truck class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
            {{ t('landed_cost.post') }}
          </Button>
          <Button
            v-if="canEdit"
            variant="default"
            size="sm"
            class="gap-1.5 bg-primary text-primary-foreground"
            @click="router.visit(route('landed-costs.edit', record.id))"
          >
            <SquarePen class="h-4 w-4" />
            {{ t('datatable.edit') }}
          </Button>
        </div>
      </div>

      <!-- Info card -->
      <fieldset class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 text-card-foreground shadow-sm">
        <legend class="px-2 flex items-center gap-1.5">
          <span class="text-sm font-semibold text-violet-500">
            {{ t('landed_cost.title') }} #{{ record.number || record.id }}
          </span>
          <Badge :class="statusBadgeClasses">{{ record.status }}</Badge>
        </legend>
        <div class="mb-4 flex items-center gap-2">
          <FileText class="h-5 w-5 text-violet-500" />
          <h3 class="text-base font-semibold text-foreground">{{ t('general.details') }}</h3>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><Calendar class="h-3 w-3" />{{ t('general.date') }}</div>
            <div class="text-sm font-medium text-foreground">{{ record.date || '-' }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><Package2 class="h-3 w-3" />{{ t('landed_cost.purchase_orders') }}</div>
            <div class="text-sm font-medium text-foreground">{{ purchaseNumbersLabel }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><Scale class="h-3 w-3" />{{ t('landed_cost.allocation_method') }}</div>
            <div class="text-sm font-medium text-foreground">{{ record.allocation_method || '-' }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><Banknote class="h-3 w-3" />{{ t('landed_cost.bank_account') }}</div>
            <div class="text-sm font-medium text-foreground">{{ record.bank_account_name || '-' }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><Coins class="h-3 w-3" />{{ t('general.currency') }}</div>
            <div class="text-sm font-medium text-foreground">{{ currencyLabel }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><ArrowLeftRight class="h-3 w-3" />{{ t('general.exchange_rate') }}</div>
            <div class="text-sm font-medium text-foreground">
              <template v-if="isForeignCurrency">1 {{ currency?.code }} = {{ formattedRate }} {{ baseCurrencyCode }}</template>
              <template v-else>{{ formattedRate }}</template>
            </div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><DollarSign class="h-3 w-3" />{{ t('landed_cost.total_additional_cost') }}</div>
            <div class="text-sm font-medium text-foreground">{{ currencySymbol }} {{ formatAmount(record.total_cost) }}</div>
            <div v-if="isForeignCurrency" class="text-xs text-muted-foreground">
              ≈ {{ formattedBaseTotal }} {{ baseCurrencyCode }}
            </div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><DollarSign class="h-3 w-3" />{{ t('landed_cost.allocated_amount') }}</div>
            <div class="text-sm font-medium text-foreground">{{ currencySymbol }} {{ formatAmount(totalLanded) }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><FileText class="h-3 w-3" />{{ t('landed_cost.transaction_status') }}</div>
            <div class="text-sm font-medium text-foreground">{{ record.transaction_status || '-' }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><User class="h-3 w-3" />{{ t('general.created_by') }}</div>
            <div class="text-sm font-medium text-foreground">{{ record.created_by?.name || '-' }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><User class="h-3 w-3" />{{ t('general.updated_by') }}</div>
            <div class="text-sm font-medium text-foreground">{{ record.updated_by?.name || '-' }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><Calendar class="h-3 w-3" />{{ t('general.updated_at') }}</div>
            <div class="text-sm font-medium text-foreground">{{ record.updated_at || '-' }}</div>
          </div>
          <div v-if="record.notes" class="space-y-1.5 sm:col-span-2 xl:col-span-4">
            <div class="flex items-center gap-2 text-xs text-muted-foreground"><StickyNote class="h-3 w-3" />{{ t('landed_cost.notes') }}</div>
            <div class="whitespace-pre-wrap text-sm font-medium text-foreground">{{ record.notes }}</div>
          </div>
        </div>
      </fieldset>

      <!-- Allocation mismatch warning -->
      <div
        v-if="record.status_id !== 'posted' && !isFullyAllocated"
        class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-300"
      >
        {{ allocationDifference > 0
          ? t('landed_cost.allocation_not_fully_allocated', { amount: formatAmount(allocationDifference) })
          : t('landed_cost.allocation_exceeds_additional_cost', { amount: formatAmount(Math.abs(allocationDifference)) }) }}
      </div>

      <!-- Category breakdown -->
      <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex items-center gap-2 border-b border-border bg-muted/30 px-4 py-3">
          <Layers class="h-5 w-5 text-violet-500" />
          <h3 class="text-base font-semibold text-foreground">{{ t('landed_cost.category_breakdown') }}</h3>
        </div>

        <div v-if="!categoryRows.length" class="px-4 py-6 text-sm text-muted-foreground">
          {{ t('landed_cost.no_category_breakdown') }}
        </div>

        <template v-else>
          <!-- Mobile cards -->
          <div class="space-y-3 p-4 md:hidden">
            <div
              v-for="(row, index) in categoryRows"
              :key="row.id || row.landed_cost_category_id || index"
              class="flex items-center justify-between gap-3 rounded-xl border border-border bg-background/70 p-4"
            >
              <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">#{{ index + 1 }}</div>
                <div class="truncate text-sm font-semibold text-foreground">{{ row.category_name || '-' }}</div>
              </div>
              <div class="text-right">
                <div class="text-xs text-muted-foreground">{{ t('general.amount') }}</div>
                <div class="text-sm font-semibold text-violet-600 dark:text-violet-400">{{ currencySymbol }} {{ formatAmount(row.amount) }}</div>
              </div>
            </div>
            <div class="flex items-center justify-between gap-3 rounded-xl border border-border bg-muted/20 p-4 text-sm">
              <span class="text-muted-foreground">{{ t('landed_cost.total_assigned') }}</span>
              <span class="text-base font-bold text-violet-600 dark:text-violet-400">{{ currencySymbol }} {{ formatAmount(categoryTotal) }}</span>
            </div>
          </div>

          <!-- Desktop table -->
          <div class="hidden overflow-x-auto md:block">
            <table class="w-full text-sm">
              <thead class="border-b border-border bg-muted/40">
                <tr>
                  <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">#</th>
                  <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.category') }}</th>
                  <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.amount') }}</th>
                  <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.share_of_total') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                <tr
                  v-for="(row, index) in categoryRows"
                  :key="row.id || row.landed_cost_category_id || index"
                  class="bg-background/40 transition-colors hover:bg-muted/40"
                >
                  <td class="px-3 py-3 text-foreground">{{ index + 1 }}</td>
                  <td class="px-3 py-3 font-medium text-foreground">{{ row.category_name || '-' }}</td>
                  <td class="px-3 py-3 text-right text-foreground">{{ formatAmount(row.amount) }}</td>
                  <td class="px-3 py-3 text-right text-foreground">
                    {{ categoryTotal > 0 ? ((Number(row.amount || 0) / categoryTotal) * 100).toFixed(2) : '0.00' }}%
                  </td>
                </tr>
              </tbody>
              <tfoot class="border-t border-border bg-muted/30">
                <tr>
                  <td colspan="2" class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ t('landed_cost.total_assigned') }}:</td>
                  <td class="px-3 py-4 text-right text-lg font-bold text-violet-600 dark:text-violet-400">{{ currencySymbol }} {{ formatAmount(categoryTotal) }}</td>
                  <td class="px-3 py-4"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </template>
      </div>

      <!-- Items table -->
      <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex items-center gap-2 border-b border-border bg-muted/30 px-4 py-3">
          <Package2 class="h-5 w-5 text-violet-500" />
          <h3 class="text-base font-semibold text-foreground">{{ t('landed_cost.items') }}</h3>
        </div>

        <!-- Mobile cards -->
        <div class="space-y-3 p-4 md:hidden">
          <div
            v-for="(row, index) in previewRows"
            :key="row.id"
            class="rounded-xl border border-border bg-background/70 p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">#{{ index + 1 }}</div>
                <div class="truncate text-sm font-semibold text-foreground">{{ row.item_name || row.item_id }}</div>
                <div class="text-xs text-muted-foreground">{{ row.item_code || '-' }}</div>
              </div>
              <div class="text-right">
                <div class="text-xs text-muted-foreground">{{ t('landed_cost.landed_unit_cost') }}</div>
                <div class="text-sm font-semibold text-violet-600 dark:text-violet-400">{{ formatUnit(row.landed_unit_cost) }}</div>
              </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
              <div><div class="text-xs text-muted-foreground">{{ t('landed_cost.purchase_order') }}</div><div class="font-medium text-foreground">{{ row.purchase_number || '-' }}</div></div>
              <div><div class="text-xs text-muted-foreground">{{ t('landed_cost.quantity') }}</div><div class="font-medium text-foreground">{{ row.quantity }}</div></div>
              <div><div class="text-xs text-muted-foreground">{{ t('landed_cost.base_unit_cost') }}</div><div class="font-medium text-foreground">{{ formatAmount(row.unit_cost) }}</div></div>
              <div><div class="text-xs text-muted-foreground">{{ t('landed_cost.allocated_percentage') }}</div><div class="font-medium text-foreground">{{ Number(row.allocated_percentage || 0).toFixed(4) }}%</div></div>
              <div><div class="text-xs text-muted-foreground">{{ t('landed_cost.item_cost_before') }}</div><div class="font-medium text-foreground">{{ formatAmount(row.item_cost_before) }}</div></div>
              <div><div class="text-xs text-muted-foreground">{{ t('landed_cost.allocated_amount') }}</div><div class="font-medium text-foreground">{{ formatAmount(row.allocated_amount) }}</div></div>
              <div><div class="text-xs text-muted-foreground">{{ t('landed_cost.item_cost_after') }}</div><div class="font-medium text-foreground">{{ formatAmount(row.item_cost_after) }}</div></div>
            </div>
          </div>
        </div>

        <!-- Desktop table -->
        <div class="hidden overflow-x-auto md:block">
          <table class="w-full text-sm">
            <thead class="border-b border-border bg-muted/40">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">#</th>
                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.purchase_order') }}</th>
                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.item') }}</th>
                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.quantity') }}</th>
                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.base_unit_cost') }}</th>
                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.item_cost_before') }}</th>
                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.allocated_percentage') }}</th>
                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.allocated_amount') }}</th>
                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.item_cost_after') }}</th>
                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('landed_cost.landed_unit_cost') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr
                v-for="(row, index) in previewRows"
                :key="row.id"
                class="bg-background/40 transition-colors hover:bg-muted/40"
              >
                <td class="px-3 py-3 text-foreground">{{ index + 1 }}</td>
                <td class="px-3 py-3 text-foreground">{{ row.purchase_number || row.purchase_item?.purchase?.number || '-' }}</td>
                <td class="px-3 py-3 text-foreground">
                  <div class="font-medium">{{ row.item_name || row.item_id }}</div>
                  <div class="text-xs text-muted-foreground">{{ row.item_code }}</div>
                </td>
                <td class="px-3 py-3 text-right text-foreground">{{ row.quantity }}</td>
                <td class="px-3 py-3 text-right text-foreground">{{ formatAmount(row.unit_cost) }}</td>
                <td class="px-3 py-3 text-right text-foreground">{{ formatAmount(row.item_cost_before) }}</td>
                <td class="px-3 py-3 text-right text-foreground">{{ Number(row.allocated_percentage || 0).toFixed(4) }}%</td>
                <td class="px-3 py-3 text-right text-foreground">{{ formatAmount(row.allocated_amount) }}</td>
                <td class="px-3 py-3 text-right text-foreground">{{ formatAmount(row.item_cost_after) }}</td>
                <td class="px-3 py-3 text-right font-semibold text-violet-600 dark:text-violet-400">{{ formatUnit(row.landed_unit_cost) }}</td>
              </tr>
            </tbody>
            <tfoot class="border-t border-border bg-muted/30">
              <tr>
                <td colspan="3" class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ t('general.total') }}:</td>
                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ totalQuantity }}</td>
                <td class="px-3 py-4"></td>
                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formatAmount(totalBaseCost) }}</td>
                <td class="px-3 py-4"></td>
                <td class="px-3 py-4 text-right text-lg font-bold text-violet-600 dark:text-violet-400">{{ currencySymbol }} {{ formatAmount(totalLanded) }}</td>
                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formatAmount(totalLandedCost) }}</td>
                <td class="px-3 py-4"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
td, th { white-space: nowrap; }
</style>
