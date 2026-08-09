<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { ArrowLeft, SquarePen, Printer, Hash, Mail, Phone, MessageCircle } from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';
import LedgerListTable from '@/Components/reports/LedgerListTable.vue';
import { paymentStatusBadgeClass, PAYMENT_STATUS_BADGE_BASE } from '@/utils/paymentStatus';
import { getCreditSummary } from '@/composables/useCreditLimit';
import AttachmentList from '@/Components/AttachmentList.vue';
import PhotoUpload from '@/Components/next/PhotoUpload.vue';
import LedgerStatement from '@/Components/ledger/LedgerStatement.vue';

const props = defineProps({
    supplier: { type: Object, required: true },
    purchases: { type: Object, required: false },
    receipts: { type: Object, required: false },
    payments: { type: Object, required: false },
    ledgerStatement: { type: Object, required: false, default: () => ({}) },
});

const { t } = useI18n();
const { can } = useAuth();

const supplierData = computed(() => props.supplier?.data ?? props.supplier ?? {});
const statement = computed(() => supplierData.value.statement ?? {});
const openings = computed(() => {
    if (Array.isArray(supplierData.value.openings)) return supplierData.value.openings;
    return supplierData.value.opening ? [supplierData.value.opening] : [];
});

const purchaseRows = computed(() => props.purchases?.data ?? props.purchases ?? []);
const receiptRows = computed(() => props.receipts?.data ?? props.receipts ?? []);
const paymentRows = computed(() => props.payments?.data ?? props.payments ?? []);

const activeMainTab = ref('general');
const activeTxnTab = ref('purchases');
const photo = ref(null);
const photoError = ref('');

const formatAmount = (value) => {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const exportUrl = (list) => route('suppliers.export', {
    supplier: supplierData.value.id,
    list,
});

const openPrint = (routeName, id) => {
    if (!routeName || !id) return;
    window.open(route(routeName, id), '_blank');
};

const openTransaction = (routeName, id) => {
    if (routeName && id) router.visit(route(routeName, id));
};

const uploadPhoto = (file) => {
    if (!file || !supplierData.value.id) return;

    photo.value = file;
    photoError.value = '';
    router.post(route('suppliers.photo.update', supplierData.value.id), { photo: file }, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => { photoError.value = errors.photo ?? ''; },
        onFinish: () => { photo.value = null; },
    });
};

const creditTermsLabel = (terms) => {
    if (terms === 'strict') return t('ledger.credit_terms_strict');
    if (terms === 'warning') return t('ledger.credit_terms_warning');
    if (terms === 'flexible') return t('ledger.credit_terms_flexible');
    return '-';
};

const currencyCode = computed(() => supplierData.value.currency?.code || supplierData.value.currency?.name || '');
const creditSummary = computed(() => getCreditSummary(supplierData.value));

const createTransactionRoute = (type) => {
    const id = supplierData.value.id;
    const routes = {
        purchases: route('purchases.create', { supplier_id: id }),
        receipts: route('receipts.create', { ledger_id: id }),
        payments: route('payments.create', { ledger_id: id }),
    };
    return routes[type];
};

const supplierPurchaseTableRows = computed(() => purchaseRows.value.map((row) => ({
    id: row.id,
    number: row.number || row.reference_id || row.id,
    date: row.date,
    type: row.type || '-',
    amount: row.amount,
    status: row.payment_status_label || row.payment_status || '-',
    payment_status: row.payment_status,
    description: row.description || '-',
    showRoute: 'purchases.show',
})));

const supplierReceiptTableRows = computed(() => receiptRows.value.map((row) => ({
    id: row.id,
    number: row.number || row.reference_id || row.id,
    date: row.date,
    amount: row.amount,
    currency: row.currency_code || row.transaction?.currency?.code || row.transaction?.currency?.name || '',
    rate: row.rate || 0,
    payment_mode: row.payment_mode_label || row.payment_mode || '-',
    description: row.narration || row.description || '-',
    printRoute: 'receipts.print',
    showRoute: 'receipts.show',
})));

const supplierPaymentTableRows = computed(() => paymentRows.value.map((row) => ({
    id: row.id,
    number: row.number || row.reference_id || row.id,
    date: row.date,
    amount: row.amount,
    currency: row.currency_code || row.transaction?.currency?.code || row.transaction?.currency?.name || '',
    rate: row.rate || 0,
    payment_mode: row.payment_mode_label || row.payment_mode || '-',
    description: row.narration || row.description || '-',
    printRoute: 'payments.print',
    showRoute: 'payments.show',
})));

const supplierPurchaseColumns = computed(() => [
    { key: 'number', label: t('general.number') },
    { key: 'date', label: t('general.date') },
    { key: 'type', label: t('general.type') },
    { key: 'amount', label: t('general.amount'), type: 'money', align: 'right' },
    { key: 'status', label: t('general.status') },
    { key: 'description', label: t('general.description') },
]);

const supplierMovementColumns = computed(() => [
    { key: 'number', label: t('general.number') },
    { key: 'date', label: t('general.date') },
    { key: 'amount', label: t('general.amount'), type: 'money', align: 'right' },
    { key: 'currency', label: t('admin.currency.currency') },
    { key: 'rate', label: t('general.rate'), type: 'money', align: 'right' },
    { key: 'payment_mode', label: t('general.payment_method') },
    { key: 'description', label: t('general.description') },
    { key: 'actions', label: t('general.actions'), align: 'right' },
]);
</script>

<template>
    <AppLayout :title="`${t('ledger.supplier.supplier')} - ${supplierData.name || ''}`">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Button variant="outline" size="sm" @click="router.visit(route('suppliers.index'))">
                    <ArrowLeft class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
                    {{ t('general.back') }}
                </Button>
                <Button
                    v-if="can('suppliers.update') && supplierData.id"
                    variant="default"
                    size="sm"
                    class="gap-1.5 bg-primary text-primary-foreground"
                    @click="router.visit(route('suppliers.edit', supplierData.id))"
                >
                    <SquarePen class="h-4 w-4" />
                    {{ t('datatable.edit') }}
                </Button>
            </div>
            <!-- Top tabs: General / Opening -->
            <div class="border-b border-border flex gap-4">
                <button
                    type="button"
                    class="px-4 py-2 -mb-px border-b-2 transition-colors"
                    :class="activeMainTab === 'general'
                        ? 'border-primary text-primary font-semibold'
                        : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeMainTab = 'general'"
                >
                    {{ t('general.general') }}
                </button>
                <button
                    type="button"
                    class="px-4 py-2 -mb-px border-b-2 transition-colors"
                    :class="activeMainTab === 'opening'
                        ? 'border-primary text-primary font-semibold'
                        : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeMainTab = 'opening'"
                >
                    {{ t('item.opening') }}
                </button>
                <button
                    type="button"
                    class="px-4 py-2 -mb-px border-b-2 transition-colors"
                    :class="activeMainTab === 'statement'
                        ? 'border-primary text-primary font-semibold'
                        : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeMainTab = 'statement'"
                >
                    {{ t('report.statement') }}
                </button>
            </div>

            <!-- GENERAL TAB -->
            <div v-if="activeMainTab === 'general'" class="space-y-4">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:items-start">
                    <!-- Left column: profile, contact and statement cards -->
                    <div class="space-y-4 lg:self-start">
                        <!-- Profile card -->
                        <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-5 flex flex-col items-center gap-3">
                            <PhotoUpload
                                v-if="can('suppliers.update')"
                                :model-value="photo"
                                :current="supplierData.photo_url"
                                :error="photoError"
                                :show-remove="false"
                                @update:model-value="uploadPhoto"
                            />
                            <div v-else class="w-20 h-20 overflow-hidden rounded-full bg-gradient-to-tr from-emerald-500 to-teal-500 flex items-center justify-center text-white text-2xl font-bold">
                                <img v-if="supplierData.photo_url" :src="supplierData.photo_url" alt="" class="h-full w-full object-cover" />
                                <template v-else>{{ (supplierData.name || '').charAt(0).toUpperCase() }}</template>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-semibold text-primary">{{ supplierData.name }}</div>
                                <div class="mt-1 text-xs text-muted-foreground/70">{{ t('ledger.supplier.supplier') }}</div>
                            </div>
                        </div>

                        <!-- Contact card -->
                        <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border divide-y divide-border overflow-hidden">
                            <div class="flex items-center gap-3 px-4 py-2.5">
                                <Hash class="h-4 w-4 shrink-0 text-violet-500" />
                                <span class="text-sm font-medium text-foreground">{{ supplierData.code || '-' }}</span>
                            </div>
                            <a
                                :href="supplierData.phone_no ? `tel:${supplierData.phone_no}` : undefined"
                                class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-muted/50"
                            >
                                <Phone class="h-4 w-4 shrink-0 text-emerald-500" />
                                <span class="text-sm text-foreground">{{ supplierData.phone_no || '-' }}</span>
                            </a>
                            <a
                                v-if="supplierData.whatsapp_number"
                                :href="`https://wa.me/${String(supplierData.whatsapp_number).replace(/[^0-9]/g, '')}`"
                                target="_blank"
                                rel="noopener"
                                class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-muted/50"
                            >
                                <MessageCircle class="h-4 w-4 shrink-0 text-green-500" />
                                <span class="text-sm text-foreground">{{ supplierData.whatsapp_number }}</span>
                            </a>
                            <a
                                :href="supplierData.email ? `mailto:${supplierData.email}` : undefined"
                                class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-muted/50"
                            >
                                <Mail class="h-4 w-4 shrink-0 text-blue-500" />
                                <span class="truncate text-sm text-foreground">{{ supplierData.email || '-' }}</span>
                            </a>
                        </div>

                        <!-- Statement card -->
                        <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border divide-y divide-border overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-2.5">
                                <span class="text-sm text-muted-foreground">{{ t('general.credit') }}</span>
                                <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ statement.total_credit }}</span>
                            </div>
                            <div class="flex items-center justify-between px-4 py-2.5">
                                <span class="text-sm text-muted-foreground">{{ t('general.debit') }}</span>
                                <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">{{ statement.total_debit }}</span>
                            </div>
                            <div class="flex items-center justify-between px-4 py-2.5">
                                <span class="text-sm text-muted-foreground">{{ t('general.balance') }}</span>
                                <span
                                    class="text-sm font-semibold"
                                    :class="statement.balance_nature === 'cr' ? 'text-emerald-600 dark:text-emerald-400' : 'text-blue-600 dark:text-blue-400'"
                                >{{ statement.balance }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: basic info -->
                    <div class="lg:col-span-2 bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.name') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.name }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('ledger.contact_person') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.contact_person }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.phone') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.phone_no }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.email') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.email }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('admin.currency.currency') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.currency?.name || '' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('ledger.customer_group') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.group?.localized_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('ledger.payment_term') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.payment_term?.name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('ledger.country') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.country?.localized_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('ledger.province') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.province?.localized_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('ledger.whatsapp_number') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.whatsapp_number || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('ledger.credit_limit') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.credit_limit ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('ledger.credit_terms') }}</div>
                                <div class="font-medium text-foreground">{{ creditTermsLabel(supplierData.credit_terms) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('ledger.discount') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.discount ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.branch') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.branch?.name || '' }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <div class="text-xs text-muted-foreground">{{ t('general.address') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.address }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.created_by') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.created_by?.name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.updated_by') }}</div>
                                <div class="font-medium text-foreground">{{ supplierData.updated_by?.name || '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Credit summary (only when credit limit tracking is enabled) -->
                <div
                    v-if="creditSummary.enabled"
                    class="rounded-xl border p-5 shadow-sm"
                    :class="creditSummary.classes.card"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-foreground">{{ t('ledger.supplier_credit_summary') }}</h3>
                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium" :class="creditSummary.classes.badge">
                            {{ creditSummary.classes.dot }} {{ creditTermsLabel(creditSummary.terms) }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <div class="text-xs text-muted-foreground">{{ t('ledger.supplier_credit_limit') }}</div>
                            <div class="mt-1 text-lg font-semibold text-foreground">{{ formatAmount(creditSummary.limit) }} {{ currencyCode }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">{{ t('ledger.current_payable') }}</div>
                            <div class="mt-1 text-lg font-semibold text-foreground">{{ formatAmount(creditSummary.used) }} {{ currencyCode }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">{{ t('ledger.available_credit') }}</div>
                            <div class="mt-1 text-lg font-bold" :class="creditSummary.classes.text">{{ formatAmount(creditSummary.available) }} {{ currencyCode }}</div>
                        </div>
                    </div>
                    <!-- Utilization bar: how much of the limit is used -->
                    <div class="mt-4">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="creditSummary.level === 'red' ? 'bg-red-500' : creditSummary.level === 'yellow' ? 'bg-amber-500' : 'bg-green-500'"
                                :style="{ width: Math.min(100, Math.max(0, creditSummary.limit > 0 ? (creditSummary.used / creditSummary.limit) * 100 : 0)) + '%' }"
                            ></div>
                        </div>
                        <div class="mt-1 flex justify-between text-xs text-muted-foreground">
                            <span>{{ t('ledger.used') }}: {{ formatAmount(creditSummary.used) }}</span>
                            <span>{{ Math.round(creditSummary.limit > 0 ? (creditSummary.used / creditSummary.limit) * 100 : 0) }}%</span>
                        </div>
                    </div>
                </div>

                <!-- Attachments -->
                <div
                    v-if="supplierData.attachments && supplierData.attachments.length"
                    class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-5"
                >
                    <h3 class="mb-3 text-sm font-semibold text-foreground">{{ t('general.attachments') }}</h3>
                    <AttachmentList :items="supplierData.attachments" />
                </div>

                <!-- Purchases / Receipts / Payments tables -->
                <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm rounded-full transition-colors"
                            :class="activeTxnTab === 'purchases'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            @click="activeTxnTab = 'purchases'"
                        >
                            {{ t('purchase.purchases') }}
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm rounded-full transition-colors"
                            :class="activeTxnTab === 'receipts'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            @click="activeTxnTab = 'receipts'"
                        >
                            {{ t('receipt.receipts') }}
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm rounded-full transition-colors"
                            :class="activeTxnTab === 'payments'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            @click="activeTxnTab = 'payments'"
                        >
                            {{ t('payment.payments') }}
                        </button>
                    </div>

                    <LedgerListTable
                        v-if="activeTxnTab === 'purchases'"
                        :title="t('purchase.purchases')"
                        :rows="supplierPurchaseTableRows"
                        :columns="supplierPurchaseColumns"
                        :empty-message="t('general.no_data_found')"
                        :export-url="exportUrl('purchases')"
                        :export-label="t('report.export_excel')"
                        :add-route="createTransactionRoute('purchases')"
                        :add-label="t('general.add_new')"
                        :row-number-label="t('report.columns.no')"
                        default-sort-key="date"
                        default-sort-direction="desc"
                        @row-click="openTransaction($event.showRoute, $event.id)"
                    >
                        <template #cell-status="{ row }">
                            <span :class="[PAYMENT_STATUS_BADGE_BASE, paymentStatusBadgeClass(row.payment_status)]">
                                {{ row.status }}
                            </span>
                        </template>
                    </LedgerListTable>
                    <LedgerListTable
                        v-else-if="activeTxnTab === 'receipts'"
                        :title="t('receipt.receipts')"
                        :rows="supplierReceiptTableRows"
                        :columns="supplierMovementColumns"
                        :empty-message="t('general.no_data_found')"
                        :export-url="exportUrl('receipts')"
                        :export-label="t('report.export_excel')"
                        :add-route="createTransactionRoute('receipts')"
                        :add-label="t('general.add_new')"
                        :row-number-label="t('report.columns.no')"
                        default-sort-key="date"
                        default-sort-direction="desc"
                        @row-click="openTransaction($event.showRoute, $event.id)"
                    >
                        <template #cell-actions="{ row }">
                            <Button variant="outline" size="icon" :title="t('datatable.print')" @click.stop="openPrint(row.printRoute, row.id)">
                                <Printer class="h-4 w-4" />
                            </Button>
                        </template>
                    </LedgerListTable>
                    <LedgerListTable
                        v-else
                        :title="t('payment.payments')"
                        :rows="supplierPaymentTableRows"
                        :columns="supplierMovementColumns"
                        :empty-message="t('general.no_data_found')"
                        :export-url="exportUrl('payments')"
                        :export-label="t('report.export_excel')"
                        :add-route="createTransactionRoute('payments')"
                        :add-label="t('general.add_new')"
                        :row-number-label="t('report.columns.no')"
                        default-sort-key="date"
                        default-sort-direction="desc"
                        @row-click="openTransaction($event.showRoute, $event.id)"
                    >
                        <template #cell-actions="{ row }">
                            <Button variant="outline" size="icon" :title="t('datatable.print')" @click.stop="openPrint(row.printRoute, row.id)">
                                <Printer class="h-4 w-4" />
                            </Button>
                        </template>
                    </LedgerListTable>
                </div>
            </div>

            <!-- STATEMENT TAB -->
            <LedgerStatement
                v-else-if="activeMainTab === 'statement'"
                :statement="ledgerStatement"
                route-name="suppliers.show"
                param-key="supplier"
                :ledger-id="supplierData.id"
                export-route-name="suppliers.export"
            />

            <!-- OPENING TAB -->
            <div v-else class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                <div class="text-sm font-semibold mb-3 text-foreground">
                    {{ t('item.opening') }}
                </div>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left rtl:text-right text-muted-foreground">
                            <th class="py-2 pr-4">{{ t('admin.currency.currency') }}</th>
                            <th class="py-2 pr-4">{{ t('general.amount') }}</th>
                            <th class="py-2 pr-4">{{ t('general.rate') }}</th>
                            <th class="py-2 pr-4">{{ t('general.type') }}</th>
                            <th class="py-2 pr-4">{{ t('general.date') }}</th>
                            <th class="py-2 pr-4">{{ t('general.remark') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!openings.length">
                            <td colspan="6" class="py-4 text-center text-muted-foreground">
                                {{ t('general.no_data_found') }}
                            </td>
                        </tr>
                        <tr
                            v-for="opening in openings"
                            :key="opening.id"
                            class="border-b border-border last:border-b-0 text-foreground"
                        >
                            <td class="py-2 pr-4">{{ opening.currency?.name || '' }}</td>
                            <td class="py-2 pr-4">{{ formatAmount(opening.amount) }}</td>
                            <td class="py-2 pr-4">{{ opening.rate }}</td>
                            <td class="py-2 pr-4 capitalize">{{ opening.type }}</td>
                            <td class="py-2 pr-4">{{ opening.date }}</td>
                            <td class="py-2 pr-4">{{ opening.remark || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
