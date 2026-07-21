<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import AttachmentList from '@/Components/AttachmentList.vue';
import { ref, computed, onMounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Button } from '@/Components/ui/button';
import {
    Package, Hash, Pill, Box, Tag, Layers, TrendingUp, TrendingDown,
    DollarSign, Palette, Ruler, MapPin, Barcode, Search,
    Building, Target, User, Download, ArrowLeft, SquarePen, HandCoins
} from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';
import JsBarcode from 'jsbarcode';
import { COLOR_OPTIONS } from '@/constants/colors';

const { t } = useI18n();
const { can } = useAuth();

const props = defineProps({
    item: { type: Object, required: true },
    stockByWarehouse: { type: Array, default: () => [] },
});

const itemData = computed(() => props.item?.data ?? props.item ?? {});

const activeTab = ref('in');
const inRecords = ref([]);
const outRecords = ref([]);
const inPage = ref(1);
const outPage = ref(1);
const inHasMore = ref(true);
const outHasMore = ref(true);
const loading = ref(false);
const itemBarcodeSvg = ref(null);

const currentRecords = computed(() => activeTab.value === 'in' ? inRecords.value : outRecords.value);

const itemDetails = computed(() => [
    { label: t('general.name'), value: itemData.value?.name, icon: Package },
    { label: t('item.code'), value: itemData.value?.code, icon: Hash },
    { label: t('item.generic_name'), value: itemData.value?.generic_name, icon: Pill },
    { label: t('item.packing'), value: itemData.value?.packing, icon: Box },
    { label: t('item.barcode'), value: itemData.value?.barcode, icon: Barcode },
    { label: t('item.sku'), value: itemData.value?.sku, icon: Barcode },
    { label: t('item.item_type'), value: itemData.value?.item_type, icon: Tag },
    { label: t('item.unit_measure'), value: itemData.value?.measure, icon: Ruler },
    { label: t('item.brand'), value: itemData.value?.brand_name, icon: Tag },
    { label: t('item.category'), value: itemData.value?.category, icon: Layers },
    { label: t('item.asset_account'), value: itemData.value?.asset_account?.name, icon: Building },
    { label: t('item.income_account'), value: itemData.value?.income_account?.name, icon: TrendingUp },
    { label: t('item.cost_account'), value: itemData.value?.cost_account?.name, icon: TrendingDown },
    { label: t('item.minimum_stock'), value: itemData.value?.minimum_stock, icon: TrendingDown },
    { label: t('item.maximum_stock'), value: itemData.value?.maximum_stock, icon: TrendingUp },
    { label: t('item.current_stock'), value: itemData.value?.on_hand || 0, icon: Target },
    { label: t('item.purchase_price'), value: itemData.value?.purchase_price, icon: DollarSign },
    { label: t('item.average_cost'), value: itemData.value?.avg_cost, icon: DollarSign },
    { label: t('item.sale_price'), value: itemData.value?.sale_price, icon: DollarSign },
    { label: t('item.margin_percentage'), value: itemData.value?.margin_percentage, icon: DollarSign },
    { label: t('item.rate_a'), value: itemData.value?.rate_a, icon: DollarSign },
    { label: t('item.rate_b'), value: itemData.value?.rate_b, icon: DollarSign },
    { label: t('item.rate_c'), value: itemData.value?.rate_c, icon: DollarSign },
    { label: t('item.rack_no'), value: itemData.value?.rack_no, icon: MapPin },
    { label: t('item.fast_search'), value: itemData.value?.fast_search, icon: Search },
    { label: t('general.created_by'), value: itemData.value?.created_by?.name || '—', icon: User },
    { label: t('general.updated_by'), value: itemData.value?.updated_by?.name || '—', icon: User },
]);

const itemColors = computed(() => {
    const colors = Array.isArray(itemData.value?.colors) ? itemData.value.colors : [];
    return colors.map((value) => ({
        value,
        name: t(`colors.${value}`),
        hex: COLOR_OPTIONS.find((c) => c.value === value)?.hex ?? '#9ca3af',
    }));
});

// Reorder signal: compare on-hand against the item's configured minimum stock.
// `on_hand` arrives pre-formatted (e.g. "1,234.00"), so strip separators first.
const toNumber = (value) => {
    const parsed = Number(String(value ?? '').replace(/,/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
};

const stockStatus = computed(() => {
    const onHand = toNumber(itemData.value?.on_hand);
    const min = itemData.value?.minimum_stock;

    if (onHand <= 0) {
        return { label: t('item.out_of_stock'), class: 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400' };
    }
    if (min !== null && min !== undefined && min !== '' && onHand <= toNumber(min)) {
        return { label: t('item.low_stock'), class: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400' };
    }
    return { label: t('item.in_stock'), class: 'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400' };
});

// Only surface the tracking toggles that are actually enabled.
const trackingFlags = computed(() => [
    { key: 'batch', label: t('item.is_batch_tracked'), on: !!itemData.value?.is_batch_tracked },
    { key: 'expiry', label: t('item.is_expiry_tracked'), on: !!itemData.value?.is_expiry_tracked },
    { key: 'color', label: t('item.is_color_tracked'), on: !!itemData.value?.is_color_tracked },
    { key: 'size', label: t('item.is_size_tracked'), on: !!itemData.value?.is_size_tracked },
].filter(flag => flag.on));

const stockRows = computed(() => props.stockByWarehouse ?? []);

const formatQty = (value) => Number(value ?? 0).toLocaleString(undefined, { maximumFractionDigits: 4 });
const formatMoney = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Resolve a single stored color value (e.g. "red") to its label + swatch.
const resolveColor = (value) => {
    if (!value) return null;
    return {
        name: t(`colors.${value}`),
        hex: COLOR_OPTIONS.find((c) => c.value === value)?.hex ?? '#9ca3af',
    };
};

const fetchRecords = async (type, page) => {
    const res = await axios.get(route(`items.${type}-records`, itemData.value.id), {
        params: { page, per_page: 10 },
    });
    return {
        data: res.data.data || [],
        hasMore: res.data.meta?.current_page < res.data.meta?.last_page,
    };
};

const loadMore = async () => {
    if (loading.value) return;
    const isIn = activeTab.value === 'in';
    if (isIn ? !inHasMore.value : !outHasMore.value) return;

    loading.value = true;
    const page = isIn ? inPage.value : outPage.value;
    try {
        const { data, hasMore } = await fetchRecords(activeTab.value, page);
        if (isIn) {
            inRecords.value.push(...data);
            inPage.value++;
            inHasMore.value = hasMore;
        } else {
            outRecords.value.push(...data);
            outPage.value++;
            outHasMore.value = hasMore;
        }
    } finally {
        loading.value = false;
    }
};

const onScroll = (e) => {
    const el = e.target;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 40) loadMore();
};

const switchTab = (tab) => {
    if (activeTab.value === tab) return;
    activeTab.value = tab;
    if (tab === 'in' && inRecords.value.length === 0) loadMore();
    if (tab === 'out' && outRecords.value.length === 0) loadMore();
};

const exportCurrentRecords = () => {
    const routeName = activeTab.value === 'in' ? 'items.in-records.export' : 'items.out-records.export';
    window.location.href = route(routeName, itemData.value.id);
};

const renderBarcode = async (retries = 4) => {
    const barcode = itemData.value?.barcode;
    if (!barcode) return;
    await nextTick();
    if (!itemBarcodeSvg.value) {
        if (retries > 0) requestAnimationFrame(() => renderBarcode(retries - 1));
        return;
    }
    JsBarcode(itemBarcodeSvg.value, barcode, {
        format: 'CODE128', width: 2, height: 60, displayValue: true, margin: 0,
    });
};

onMounted(() => {
    loadMore();
    renderBarcode();
});
</script>

<template>
    <AppLayout :title="`${t('item.item')} - ${itemData.name || ''}`">
        <div class="space-y-6">
            <!-- Page header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-8 gap-1.5 bg-background border-primary/60 hover:bg-primary/40 hover:text-balck"
                @click="router.visit(route('items.index'))"
            >
                <ArrowLeft class="h-4 w-4 rtl:rotate-180 text-primary" />
                {{ t('general.back') }}
            </Button>
                <Button
                    v-if="can('items.update') && itemData.id"
                    variant="default"
                    size="sm"
                    class="gap-1.5 bg-primary text-primary-foreground"
                    @click="router.visit(route('items.edit', itemData.id))"
                >
                    <SquarePen class="h-4 w-4" />
                    {{ t('datatable.edit') }}
                </Button>
            </div>

            <!-- Info section -->
            <fieldset class="rounded-xl border border-border bg-muted/40 px-5 pb-5 pt-3">
                <legend class="px-2 text-sm font-semibold text-violet-500">{{ itemData.name }}</legend>
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <div class="bg-violet-500 text-white p-1.5 rounded">
                        <Layers class="w-4 h-4" />
                    </div>
                    <h3 class="text-sm font-semibold text-foreground">{{ t('general.details') }}</h3>

                    <!-- Reorder signal -->
                    <span
                        class="ms-1 rounded-full border px-2 py-0.5 text-xs font-medium"
                        :class="stockStatus.class"
                    >
                        {{ stockStatus.label }}
                    </span>

                    <!-- Enabled tracking toggles -->
                    <span
                        v-for="flag in trackingFlags"
                        :key="flag.key"
                        class="rounded-full border border-violet-500/30 bg-violet-500/10 px-2 py-0.5 text-xs font-medium text-violet-600 dark:text-violet-300"
                    >
                        {{ flag.label }}
                    </span>
                </div>
                <div class="grid gap-x-6 gap-y-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
                    <div v-for="detail in itemDetails" :key="detail.label" class="flex items-start gap-2">
                        <component :is="detail.icon" class="w-4 h-4 text-violet-500 mt-0.5 flex-shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-muted-foreground">{{ detail.label }}</p>
                            <p class="text-sm font-medium text-foreground truncate">{{ detail.value ?? '—' }}</p>
                        </div>
                    </div>
                    <div v-if="itemColors.length" class="flex items-start gap-2">
                        <Palette class="w-4 h-4 text-violet-500 mt-0.5 flex-shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-muted-foreground">{{ t('item.colors') }}</p>
                            <div class="flex flex-wrap gap-x-2 gap-y-1 mt-0.5">
                                <span v-for="color in itemColors" :key="color.value" class="flex items-center gap-1 text-sm font-medium text-foreground">
                                    <span class="h-3 w-3 shrink-0 rounded-full border border-black/10" :style="{ backgroundColor: color.hex }" />
                                    {{ color.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-if="itemData.barcode" class="col-span-2 rounded-lg border border-border p-3">
                        <p class="text-xs text-muted-foreground mb-2">{{ t('item.barcode') }}</p>
                        <svg ref="itemBarcodeSvg" class="w-full h-[80px]"></svg>
                    </div>
                </div>

                <hr class="my-4 border-border" />
                <div class="grid gap-x-6 gap-y-3 grid-cols-2 sm:grid-cols-4">

                    <div class="flex items-start gap-2">
                        <div class="bg-violet-500 text-white p-1.5 rounded"><TrendingDown class="w-4 h-4" /></div>
                        <div><p class="text-xs text-muted-foreground font-bold">{{ t('item.in_records') }}</p><p class="text-sm font-medium text-foreground">{{ itemData.stock_count ?? '—' }}</p></div>
                    </div>
                    <div class="flex items-start gap-2">
                        <div class="bg-violet-500 text-white p-1.5 rounded"><TrendingUp class="w-4 h-4" /></div>
                        <div><p class="text-xs text-muted-foreground">{{ t('item.out_records') }}</p><p class="text-sm font-medium text-foreground">{{ itemData.stock_out_count ?? '—' }}</p></div>
                    </div>
                    <div class="flex items-start gap-2">
                        <div class="bg-violet-500 text-white p-1.5 rounded"><Target class="w-4 h-4" /></div>
                        <div><p class="text-xs text-muted-foreground">{{ t('general.on_hand') }}</p><p class="text-sm font-medium text-foreground">{{ itemData.on_hand ?? '—' }}</p></div>
                    </div>
                    <div class="flex items-start gap-2">
                        <div class="bg-violet-500 text-white p-1.5 rounded"><HandCoins class="w-4 h-4" /></div>
                        <div><p class="text-xs text-muted-foreground">{{ t('item.stock_value') }}</p><p class="text-sm font-medium text-foreground">{{ itemData.stock_value ?? '—' }}</p></div>
                    </div>
                </div>

                <!-- Stock by warehouse -->
                <template v-if="stockRows.length">
                    <hr class="my-4 border-border" />
                    <div class="flex items-center gap-2 mb-3">
                        <div class="bg-violet-500 text-white p-1.5 rounded"><Building class="w-4 h-4" /></div>
                        <h3 class="text-sm font-semibold text-foreground">{{ t('item.stock_by_warehouse') }}</h3>
                    </div>
                    <div class="rounded-lg border border-border overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-muted/60 text-muted-foreground">
                                    <th class="py-2 px-3 text-start font-medium whitespace-nowrap">{{ t('admin.warehouse.warehouse') }}</th>
                                    <th class="py-2 px-3 text-end font-medium whitespace-nowrap">{{ t('general.on_hand') }}</th>
                                    <th class="py-2 px-3 text-end font-medium whitespace-nowrap">{{ t('item.reserved') }}</th>
                                    <th class="py-2 px-3 text-end font-medium whitespace-nowrap">{{ t('item.available') }}</th>
                                    <th class="py-2 px-3 text-end font-medium whitespace-nowrap">{{ t('item.stock_value') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in stockRows"
                                    :key="row.warehouse_id"
                                    class="border-t border-border/60 hover:bg-muted/40 transition"
                                >
                                    <td class="py-2 px-3 whitespace-nowrap font-medium text-foreground">{{ row.warehouse_name || '—' }}</td>
                                    <td class="py-2 px-3 text-end whitespace-nowrap text-foreground">{{ formatQty(row.quantity) }}</td>
                                    <td class="py-2 px-3 text-end whitespace-nowrap text-muted-foreground">{{ formatQty(row.reserved) }}</td>
                                    <td class="py-2 px-3 text-end whitespace-nowrap font-semibold text-foreground">{{ formatQty(row.available) }}</td>
                                    <td class="py-2 px-3 text-end whitespace-nowrap text-muted-foreground">{{ formatMoney(row.stock_value) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </fieldset>

            <!-- Records table -->
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <!-- Tabs + export -->
                <div class="px-4 pt-3 flex items-center justify-between gap-3 border-b border-border bg-background">
                    <div class="flex gap-2">
                        <button class="px-3 py-2 text-sm rounded-t-md border-b-2"
                            :class="activeTab === 'in' ? 'border-violet-500 text-violet-600 font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                            @click="switchTab('in')">
                            {{ t('item.in_records') }}
                        </button>
                        <button class="px-3 py-2 text-sm rounded-t-md border-b-2"
                            :class="activeTab === 'out' ? 'border-violet-500 text-violet-600 font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                            @click="switchTab('out')">
                            {{ t('item.out_records') }}
                        </button>
                    </div>
                    <button
                        class="inline-flex items-center gap-2 rounded-md bg-violet-500 px-4 py-2 text-xs font-semibold text-white transition hover:bg-violet-600 disabled:opacity-60"
                        :disabled="loading || !itemData.id"
                        @click="exportCurrentRecords">
                        <Download class="h-3.5 w-3.5" />
                        {{ activeTab === 'in' ? t('item.export_in_records') : t('item.export_out_records') }}
                    </button>
                </div>

                <!-- Scrollable table -->
                <div class="overflow-x-auto max-h-[500px] overflow-y-auto" @scroll="onScroll">
                    <table class="w-full text-xs text-foreground">
                        <thead class="sticky top-0 border-b-2 border-border z-10 bg-violet-500 text-white">
                            <tr class="text-xs uppercase tracking-wide font-semibold">
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">#</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('general.ledger') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('general.bill_number') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('general.date') }}</th>
                                <th class="py-3 px-3 text-center whitespace-nowrap rtl:text-right">{{ t('general.quantity') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('general.unit_price') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('general.total') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('general.source') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('admin.unit_measure.unit_measure') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('item.batch') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('item.color') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('item.size') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('item.expire_date') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('general.status') }}</th>
                                <th class="py-3 px-3 text-left whitespace-nowrap rtl:text-right">{{ t('admin.warehouse.warehouse') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in currentRecords" :key="row.id || index"
                                class="border-b border-border/60 hover:bg-muted/40 transition ">
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">{{ index + 1 }}</td>
                                <td class="py-3 px-3 whitespace-nowrap font-medium rtl:text-right">{{ row.ledger_name || '—' }}</td>
                                <td class="py-3 px-3 whitespace-nowrap font-semibold rtl:text-right">{{ row.bill_number || '—' }}</td>
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">{{ row.date }}</td>
                                <td class="py-3 px-3 text-center whitespace-nowrap font-semibold rtl:text-right">{{ row.quantity }}</td>
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">{{ row.unit_price }}</td>
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">{{ (Number(row.quantity || 0) * Number(row.unit_price || 0)).toFixed(4) }}</td>
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">{{ row.source }}</td>
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">{{ row.unit_measure_name }}</td>
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">{{ row.batch || '—' }}</td>
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">
                                    <span v-if="resolveColor(row.color)" class="flex items-center gap-1.5">
                                        <span class="h-3 w-3 shrink-0 rounded-full border border-muted-foreground/40" :style="{ backgroundColor: resolveColor(row.color).hex }" />
                                        {{ resolveColor(row.color).name }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">{{ row.size_name || '—' }}</td>
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">{{ row.expire_date || '—' }}</td>
                                <td class="py-3 px-3 whitespace-nowrap  rtl:text-right" :class="`text-${row.status_color ?? 'gray'}`">{{ row.status_label }}</td>
                                <td class="py-3 px-3 whitespace-nowrap text-muted-foreground rtl:text-right">{{ row.warehouse_name }}</td>
                            </tr>
                            <tr v-if="!loading && currentRecords.length === 0">
                                <td colspan="15" class="py-8 text-center text-muted-foreground">{{ t('general.no_record_available') }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="loading" class="py-3 flex items-center justify-center text-muted-foreground text-xs gap-2">
                        <span class="h-3 w-3 rounded-full border-2 border-border border-t-violet-500 animate-spin" />
                        Loading more...
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <AttachmentList :items="itemData.attachments || []" :label="t('general.attachments')" />
            </div>
        </div>
    </AppLayout>
</template>
