<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/Layout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
} from '@/Components/ui/dropdown-menu';
import {
    Search, CircleX, ChevronDown, ChevronLeft, ChevronRight, Ellipsis,
    SquarePen, Trash2, Eye, FileDown, FileX, ChevronsDownUp, ChevronsUpDown,
    CheckCircle2, AlertTriangle,
} from 'lucide-vue-next';
import AddNewButton from '@/Components/next/AddNewButton.vue';
import { useAuth } from '@/composables/useAuth';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useSoundPreferences } from '@/composables/useSoundPreferences';

const props = defineProps({
    accounts: Object,
    filters: Object,
    summary: Object,
});

const { t, locale } = useI18n();
const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value));
const { can } = useAuth();
const { deleteResource } = useDeleteResource();
const { play } = useSoundPreferences();

const page = usePage();
const currencySymbol = computed(() => {
    const c = page.props.homeCurrency;
    return c?.symbol || c?.code || '';
});

const fmtAmount = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

// With an explicit nature: an absolute figure tagged "؋ 1,700 DR" (category
// subtotals). Without one: the signed figure as-is (equation terms).
const fmtBalance = (amount, nature) => {
    const num = Number(amount ?? 0);
    const prefix = currencySymbol.value ? `${currencySymbol.value} ` : '';
    if (nature) {
        const magnitude = Math.abs(num);
        return magnitude
            ? `${prefix}${fmtAmount(magnitude)} ${String(nature).toUpperCase()}`
            : `${prefix}0`;
    }
    return num ? `${prefix}${fmtAmount(num)}` : `${prefix}0`;
};

const balanceToneClass = (nature) => {
    if (nature === 'dr') return 'text-emerald-600 dark:text-emerald-400';
    if (nature === 'cr') return 'text-amber-600 dark:text-amber-400';
    return 'text-muted-foreground';
};

const groupSummary = (nature) => props.summary?.groups?.[nature] ?? null;

// The trial-balance strip is only meaningful over the whole chart; a filtered
// subset will not balance, so it is hidden while searching.
const equation = computed(() => {
    if (props.summary?.is_filtered) return null;
    return props.summary?.equation ?? null;
});

const equationParts = computed(() => {
    const e = equation.value;
    if (!e) return [];
    return [
        { key: 'asset', op: null, value: e.assets },
        { key: 'liability', op: '=', value: e.liabilities },
        { key: 'equity', op: '+', value: e.equity },
        { key: 'income', op: '+', value: e.income },
        { key: 'expense', op: '−', value: e.expense },
    ];
});

const rows = computed(() => props.accounts?.data ?? props.accounts ?? []);
const totalCount = computed(() => rows.value.length);
const isEmpty = computed(() => totalCount.value === 0);

// The five accounting-equation categories, plus non-posting control accounts.
// Any nature not listed here is appended after these, in first-seen order.
const CATEGORY_ORDER = ['asset', 'liability', 'equity', 'income', 'expense', 'non-posting'];
const CATEGORY_EN = {
    asset: 'Asset',
    liability: 'Liability',
    equity: 'Equity',
    income: 'Income',
    expense: 'Expense',
    'non-posting': 'Non-Posting',
};

const natureLabel = (nature) => {
    const key = `account.natures.${nature}`;
    const translated = t(key);
    return translated === key ? (CATEGORY_EN[nature] || nature) : translated;
};

const groups = computed(() => {
    const byNature = new Map();
    for (const account of rows.value) {
        const nature = account.account_type?.nature || 'other';
        if (!byNature.has(nature)) byNature.set(nature, []);
        byNature.get(nature).push(account);
    }

    const ordered = [];
    const seen = new Set();
    for (const nature of CATEGORY_ORDER) {
        if (byNature.has(nature)) { ordered.push(nature); seen.add(nature); }
    }
    for (const nature of byNature.keys()) {
        if (!seen.has(nature)) ordered.push(nature);
    }

    return ordered.map((nature) => ({
        nature,
        label: natureLabel(nature),
        labelEn: CATEGORY_EN[nature] || nature,
        accounts: byNature.get(nature),
    }));
});

// Collapsed groups, remembered per browser.
const STORAGE_KEY = 'chart-of-accounts:collapsed-groups';
const collapsed = ref(new Set());
try {
    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    if (Array.isArray(stored)) collapsed.value = new Set(stored);
} catch (e) { /* storage unavailable — start with everything expanded */ }

const persist = () => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([...collapsed.value]));
    } catch (e) { /* ignore */ }
};

const isExpanded = (nature) => !collapsed.value.has(nature);
const toggleGroup = (nature) => {
    const next = new Set(collapsed.value);
    if (next.has(nature)) next.delete(nature); else next.add(nature);
    collapsed.value = next;
    persist();
};

const allExpanded = computed(() => groups.value.every((g) => isExpanded(g.nature)));
const toggleAll = () => {
    collapsed.value = allExpanded.value
        ? new Set(groups.value.map((g) => g.nature))
        : new Set();
    persist();
};

// Search runs a server round-trip, matching the previous list behaviour.
const search = ref(props.filters?.search || '');
const loading = ref(false);
const runSearch = () => {
    router.get(route('chart-of-accounts.index'), { search: search.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { loading.value = true; },
        onFinish: () => { loading.value = false; },
    });
};
const debouncedSearch = debounce(runSearch, 500);
const clearSearch = () => { search.value = ''; runSearch(); };

const editItem = (item) => router.visit(route('chart-of-accounts.edit', item.id));
const showItem = (id) => router.visit(route('chart-of-accounts.show', id));

const deleteItem = (item) => {
    if (item.is_main) {
        play('warning');
        toast.error(t('account.cannot_delete_main_account_title'), {
            description: t('account.cannot_delete_main_account_desc'),
            class: 'bg-pink-600 text-white',
            duration: 8000,
        });
        return;
    }

    deleteResource('chart-of-accounts.destroy', item.id, {
        title: t('general.delete', { name: t('account.account') }),
        description: t('general.delete_description', { name: t('account.account') }),
        successMessage: t('general.delete_success', { name: t('account.account') }),
    });
};

const exportToExcel = () => {
    window.location.href = route('chart-of-accounts.export', { search: search.value || undefined });
};

const natureBadgeClass = (nature) => ({
    asset: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400',
    liability: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
    equity: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400',
    income: 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400',
    expense: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
}[nature] || 'bg-muted text-muted-foreground');

const secondaryName = (account) => {
    const other = locale.value === 'en' ? account.local_name : account.english_name;
    if (!other || other === account.name) return '';
    return other;
};
</script>

<template>
    <AppLayout :title="t('account.chart_of_accounts')">
        <div class="flex min-h-0 flex-1 flex-col gap-4">
            <!-- Toolbar -->
            <div class="shrink-0 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:w-auto">
                    <h1 class="text-lg font-semibold text-primary whitespace-nowrap" :class="isRTL ? 'ml-2' : 'mr-2'">
                        {{ t('account.chart_of_accounts') }}
                    </h1>
                    <div class="relative w-full sm:w-[300px] lg:w-[340px]">
                        <input
                            id="search"
                            v-model="search"
                            @input="debouncedSearch"
                            type="text"
                            :placeholder="`${t('datatable.search')} ${t('account.chart_of_accounts')}`"
                            class="flex h-9 w-full rounded-md border border-primary bg-background px-3 py-2 text-sm placeholder:text-muted-foreground outline-none focus:ring-2 focus:ring-ring ps-8 pe-10 text-primary"
                        />
                        <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
                            <Search class="size-4 text-primary" />
                        </span>
                        <button
                            v-if="search"
                            type="button"
                            class="absolute end-0 inset-y-0 flex items-center px-2 text-primary"
                            @click="clearSearch"
                        >
                            <CircleX class="size-4" />
                        </button>
                    </div>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="toggleAll"
                        class="h-9 shrink-0 whitespace-nowrap border-primary text-primary hover:bg-primary hover:text-white"
                    >
                        <component :is="allExpanded ? ChevronsDownUp : ChevronsUpDown" class="h-4 w-4" />
                        <span class="ms-1">{{ allExpanded ? t('account.collapse_all') : t('account.expand_all') }}</span>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="exportToExcel"
                        class="h-9 shrink-0 whitespace-nowrap border-green-800 text-green-600 hover:bg-green-700 hover:text-white"
                    >
                        <FileDown class="h-4 w-4" />
                        <span class="ms-1">{{ t('general.excel') }}</span>
                    </Button>
                    <AddNewButton
                        v-if="can('accounts.create')"
                        :title="t('account.account')"
                        action="redirect"
                        route="chart-of-accounts.create"
                        variant="outline"
                        class="h-9 shrink-0 whitespace-nowrap border-primary text-primary hover:bg-primary hover:text-white"
                    />
                </div>
            </div>

            <!-- Trial-balance strip: Assets = Liabilities + Equity + Income − Expenses -->
            <div
                v-if="equation"
                class="shrink-0 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-md border px-4 py-2.5 text-sm"
                :class="equation.balanced
                    ? 'border-emerald-500/30 bg-emerald-500/5'
                    : 'border-amber-500/40 bg-amber-500/10'"
            >
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1" dir="ltr">
                    <template v-for="(part, i) in equationParts" :key="part.key">
                        <span v-if="part.op" class="text-muted-foreground">{{ part.op }}</span>
                        <span class="flex items-baseline gap-1.5">
                            <span class="text-xs text-muted-foreground">{{ natureLabel(part.key) }}</span>
                            <span class="font-semibold tabular-nums">{{ fmtBalance(part.value) }}</span>
                        </span>
                    </template>
                </div>
                <div
                    class="flex items-center gap-1.5 font-medium ms-auto"
                    :class="equation.balanced
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-amber-600 dark:text-amber-400'"
                >
                    <component :is="equation.balanced ? CheckCircle2 : AlertTriangle" class="h-4 w-4" />
                    <span>{{ equation.balanced ? t('account.books_balanced') : t('account.books_unbalanced', { amount: fmtBalance(equation.difference) }) }}</span>
                </div>
            </div>

            <!-- Grouped table -->
            <div
                class="min-h-0 flex-1 rounded-md border border-primary overflow-hidden [&>div]:h-full [&>div]:overflow-auto transition-opacity"
                :class="{ 'opacity-60 pointer-events-none': loading }"
            >
                <Table class="min-w-[820px] table-fixed">
                    <colgroup>
                        <col class="w-[96px]" />
                        <col />
                        <col class="w-[300px]" />
                        <col class="w-[120px]" />
                        <col class="w-[100px]" />
                    </colgroup>
                    <TableHeader class="sticky top-0 z-10 bg-primary">
                        <TableRow class="bg-primary hover:bg-purple-500 h-9 text-white">
                            <TableHead class="h-9 py-1 px-3 text-white font-medium text-start whitespace-nowrap">{{ t('general.number') }}</TableHead>
                            <TableHead class="h-9 py-1 px-3 text-white font-medium text-start whitespace-nowrap">{{ t('general.name') }}</TableHead>
                            <TableHead class="h-9 py-1 px-3 text-white font-medium text-start whitespace-nowrap">{{ t('account.account_type') }}</TableHead>
                            <TableHead class="h-9 py-1 px-3 text-white font-medium text-start whitespace-nowrap">{{ t('general.balance') }}</TableHead>
                            <TableHead class="h-9 py-1 px-3 text-white font-medium text-start whitespace-nowrap">{{ t('general.actions') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="isEmpty">
                            <TableCell :colspan="5" class="h-48 text-center">
                                <div class="flex flex-col items-center justify-center space-y-4 py-8">
                                    <div class="rounded-full bg-violet-100 p-4">
                                        <FileX class="h-8 w-8 text-violet-600" />
                                    </div>
                                    <h3 class="text-base font-semibold">{{ t('general.no_record_available') }}</h3>
                                </div>
                            </TableCell>
                        </TableRow>

                        <template v-for="group in groups" :key="group.nature">
                            <!-- Category header -->
                            <TableRow
                                class="bg-muted/60 hover:bg-muted cursor-pointer border-y border-primary/20"
                                @click="toggleGroup(group.nature)"
                            >
                                <TableCell :colspan="5" class="py-2 px-3">
                                    <div class="flex items-center gap-2">
                                        <component
                                            :is="isExpanded(group.nature) ? ChevronDown : (isRTL ? ChevronLeft : ChevronRight)"
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                        <span class="font-semibold text-sm">{{ group.label }}</span>
                                        <span v-if="locale !== 'en'" class="text-xs text-muted-foreground">({{ group.labelEn }})</span>
                                        <span class="ms-1 inline-flex items-center justify-center rounded-full bg-primary/10 text-primary text-xs font-medium px-2 py-0.5">
                                            {{ group.accounts.length }}
                                        </span>
                                        <span
                                            v-if="groupSummary(group.nature) && groupSummary(group.nature).net"
                                            class="ms-auto text-xs font-semibold tabular-nums"
                                            :class="balanceToneClass(groupSummary(group.nature).nature)"
                                            dir="ltr"
                                        >
                                            {{ fmtBalance(groupSummary(group.nature).net, groupSummary(group.nature).nature) }}
                                        </span>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <!-- Accounts in this category -->
                            <template v-if="isExpanded(group.nature)">
                                <TableRow
                                    v-for="account in group.accounts"
                                    :key="account.id"
                                    class="h-10 hover:bg-muted/50"
                                >
                                    <TableCell class="py-1 px-3 text-sm cursor-pointer tabular-nums" @click="showItem(account.id)">
                                        {{ account.number }}
                                    </TableCell>
                                    <TableCell class="py-1 px-3 text-sm cursor-pointer" @click="showItem(account.id)">
                                        <div class="flex min-w-0 flex-col leading-tight">
                                            <span class="truncate">{{ account.name }}</span>
                                            <span v-if="secondaryName(account)" class="truncate text-xs text-muted-foreground">
                                                {{ secondaryName(account) }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="py-1 px-3 text-sm">
                                        <span
                                            class="inline-block max-w-full truncate align-bottom rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="natureBadgeClass(group.nature)"
                                            :title="account.account_type?.name || group.label"
                                        >
                                            {{ account.account_type?.name || group.label }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="py-1 px-3 text-sm">
                                        <span dir="ltr" class="inline-block tabular-nums">{{ account.balance }}</span>
                                    </TableCell>
                                    <TableCell class="py-1 px-3 text-sm" @click.stop>
                                        <div class="flex items-center gap-0.5">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="h-6 w-6 hover:bg-violet-500 hover:text-white">
                                                        <Ellipsis class="w-4 h-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent class="w-48 rtl:text-right" side="bottom" :align="isRTL ? 'end' : 'start'">
                                                    <DropdownMenuLabel class="rtl:text-right text-xs">{{ t('datatable.actions') }}</DropdownMenuLabel>
                                                    <DropdownMenuItem
                                                        v-if="can('accounts.update')"
                                                        :class="[isRTL ? 'flex-row-reverse gap-2' : 'gap-2', '[&:hover]:bg-violet-500 [&:hover]:text-white [&:focus]:bg-violet-500 [&:focus]:text-white text-xs py-1.5']"
                                                        @click="editItem(account)"
                                                    ><SquarePen class="h-3 w-3" /> {{ t('datatable.edit') }}</DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        v-if="can('accounts.delete')"
                                                        :class="[isRTL ? 'flex-row-reverse gap-2' : 'gap-2', '[&:hover]:bg-violet-500 [&:hover]:text-white [&:focus]:bg-violet-500 [&:focus]:text-white text-xs py-1.5']"
                                                        @click="deleteItem(account)"
                                                    ><Trash2 class="h-3 w-3" /> {{ t('datatable.delete') }}</DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        v-if="can('accounts.view')"
                                                        :class="[isRTL ? 'flex-row-reverse gap-2' : 'gap-2', '[&:hover]:bg-violet-500 [&:hover]:text-white [&:focus]:bg-violet-500 [&:focus]:text-white text-xs py-1.5']"
                                                        @click="showItem(account.id)"
                                                    ><Eye class="h-3 w-3" /> {{ t('datatable.show') }}</DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </template>
                    </TableBody>
                </Table>
            </div>

            <!-- Footer -->
            <div class="shrink-0 border-t pt-2 text-xs text-muted-foreground">
                {{ t('account.total_accounts', { count: totalCount }) }}
            </div>
        </div>
    </AppLayout>
</template>
