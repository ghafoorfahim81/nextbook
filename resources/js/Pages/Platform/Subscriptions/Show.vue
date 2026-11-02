<script setup>
/**
 * One customer (phase 10): its package and dates, renew / block, usage, and its
 * invoices with their payments. Platform admin only.
 */
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/Layout.vue'
import ModalDialog from '@/Components/next/Dialog.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextDatePicker from '@/Components/next/NextDatePicker.vue'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'

const props = defineProps({
    company: Object,
    plan: { type: Object, default: null },
    usage: Object,
    packages: { type: Array, default: () => [] },
    invoices: { type: Array, default: () => [] },
    methods: { type: Array, default: () => [] },
    moduleNames: { type: Array, default: () => [] },
})

const { t } = useI18n()
const id = props.company.id
// Named `plan` on the wire: `subscription` is the shared banner prop and would shadow it here.
const subscription = computed(() => props.plan)

const stateClass = {
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    expiring: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    expired: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    blocked: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
}
const invoiceClass = {
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    partial: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    unpaid: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    void: 'bg-muted text-muted-foreground',
}

const packageOptions = computed(() => props.packages.map((p) => ({ id: p.id, label: `${p.name} — ${Number(p.price)} ${p.currency}` })))
const methodOptions = computed(() => props.methods.map((m) => ({ id: m, label: t(`platform.methods.${m}`) })))
const selectedPackage = computed(() => props.packages.find((p) => p.id === assign.package_id))

// --- Package and dates ------------------------------------------------------
const assign = useForm({
    package_id: props.plan?.package_id ?? null,
    starts_on: props.plan?.starts_on ?? '',
    ends_on: props.plan?.ends_on ?? '',
    notes: props.plan?.notes ?? '',
})
const saveAssign = () => assign.put(route('platform.subscriptions.update', id), { preserveScroll: true })

// --- Renew ------------------------------------------------------------------
const renewOpen = ref(false)
const renew = useForm({ months: null, invoice: true })
const doRenew = () => renew.post(route('platform.subscriptions.renew', id), {
    preserveScroll: true,
    onSuccess: () => { renewOpen.value = false },
})

// --- Block ------------------------------------------------------------------
const blockOpen = ref(false)
const block = useForm({ reason: '' })
const doBlock = () => block.post(route('platform.subscriptions.block', id), {
    preserveScroll: true,
    onSuccess: () => { blockOpen.value = false },
})
const unblock = () => router.post(route('platform.subscriptions.unblock', id), {}, { preserveScroll: true })

// --- Invoices ---------------------------------------------------------------
const invoiceOpen = ref(false)
const invoice = useForm({
    issued_on: '', due_on: '', period_from: '', period_to: '', amount: '',
    currency: 'AFN', description: '',
})
const openInvoice = () => {
    invoice.reset()
    invoice.currency = selectedPackage.value?.currency ?? 'AFN'
    invoice.amount = selectedPackage.value?.price ?? ''
    invoiceOpen.value = true
}
const saveInvoice = () => invoice.post(route('platform.subscriptions.invoices.store', id), {
    preserveScroll: true,
    onSuccess: () => { invoiceOpen.value = false },
})

const voidInvoice = (row) => {
    if (!window.confirm(t('platform.void_confirm'))) return
    router.post(route('platform.subscriptions.invoices.void', [id, row.id]), {}, { preserveScroll: true })
}

// --- Payments ---------------------------------------------------------------
const paymentOpen = ref(false)
const paymentFor = ref(null)
const payment = useForm({ amount: '', paid_on: '', method: 'cash', reference: '', note: '' })
const openPayment = (row) => {
    paymentFor.value = row
    payment.reset()
    payment.amount = row.balance
    payment.method = 'cash'
    paymentOpen.value = true
}
const savePayment = () => payment.post(route('platform.subscriptions.payments.store', [id, paymentFor.value.id]), {
    preserveScroll: true,
    onSuccess: () => { paymentOpen.value = false },
})
const removePayment = (row) => {
    if (!window.confirm(t('general.delete', { name: t('platform.payments') }))) return
    router.delete(route('platform.subscriptions.payments.destroy', [id, row.id]), { preserveScroll: true })
}

const limit = (used, max) => (max ? `${used} / ${max}` : `${used} (${t('platform.unlimited')})`)
</script>

<template>
    <AppLayout :title="company.name">
        <Link :href="route('platform.subscriptions.index')" class="text-sm text-primary hover:underline">← {{ t('platform.back') }}</Link>

        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-xl font-semibold">{{ company.name }}</h1>
            <Badge v-if="subscription" :class="stateClass[subscription.state]" variant="secondary">{{ t(`platform.states.${subscription.state}`) }}</Badge>
            <Badge v-else variant="secondary">{{ t('platform.states.none') }}</Badge>
            <span v-if="subscription?.state === 'expiring'" class="text-sm text-muted-foreground">{{ t('platform.days_left', { n: subscription.days_left }) }}</span>
        </div>
        <p v-if="subscription?.block_reason" class="text-sm text-destructive">{{ t('platform.banner.blocked_reason', { reason: subscription.block_reason }) }}</p>

        <!-- Package and dates -->
        <section class="grid gap-4 rounded-lg border p-4">
            <h2 class="font-semibold">{{ t('platform.assign') }}</h2>
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="saveAssign">
                <NextSelect
                    v-model="assign.package_id"
                    :options="packageOptions"
                    label-key="label"
                    value-key="id"
                    :reduce="(option) => option.id"
                    :floating-text="t('platform.package')"
                    :error="assign.errors.package_id"
                />
                <NextDatePicker v-model="assign.starts_on" :label="t('platform.starts_on')" :error="assign.errors.starts_on" />
                <NextDatePicker v-model="assign.ends_on" :label="t('platform.ends_on')" :error="assign.errors.ends_on" />
                <NextInput v-model="assign.notes" :label="t('platform.notes')" :error="assign.errors.notes" />
                <div class="sm:col-span-2 lg:col-span-4">
                    <Button type="submit" :disabled="assign.processing">{{ t('platform.assign_save') }}</Button>
                </div>
            </form>
        </section>

        <!-- Usage -->
        <section class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border p-4">
                <div class="text-xs text-muted-foreground">{{ t('platform.users') }}</div>
                <div class="mt-1 text-lg font-semibold">{{ limit(usage.users, selectedPackage?.max_users ?? null) }}</div>
            </div>
            <div class="rounded-lg border p-4">
                <div class="text-xs text-muted-foreground">{{ t('platform.branches') }}</div>
                <div class="mt-1 text-lg font-semibold">{{ limit(usage.branches, selectedPackage?.max_branches ?? null) }}</div>
            </div>
        </section>

        <!-- Renew / block -->
        <section v-if="subscription" class="flex flex-wrap items-center gap-2">
            <Button @click="renewOpen = true">{{ t('platform.renew') }}</Button>
            <Button v-if="subscription.state !== 'blocked'" variant="outline" class="text-destructive" @click="blockOpen = true">{{ t('platform.block') }}</Button>
            <Button v-else variant="outline" @click="unblock">{{ t('platform.unblock') }}</Button>
        </section>

        <!-- Invoices -->
        <section v-if="subscription" class="grid gap-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">{{ t('platform.invoices') }}</h2>
                <Button variant="outline" size="sm" @click="openInvoice">{{ t('platform.new_invoice') }}</Button>
            </div>

            <p v-if="!invoices.length" class="text-sm text-muted-foreground">{{ t('platform.no_invoices') }}</p>

            <div v-for="row in invoices" :key="row.id" class="rounded-lg border">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 p-3 text-sm">
                    <span class="font-medium">{{ row.number }}</span>
                    <Badge :class="invoiceClass[row.state]" variant="secondary">{{ t(`platform.invoice_states.${row.state}`) }}</Badge>
                    <span>{{ row.issued_on }}</span>
                    <span v-if="row.period" class="text-muted-foreground">{{ row.period }}</span>
                    <span>{{ t('platform.amount') }}: <strong>{{ row.amount }} {{ row.currency }}</strong></span>
                    <span>{{ t('platform.paid') }}: {{ row.paid }}</span>
                    <span>{{ t('platform.balance') }}: {{ row.balance }}</span>
                    <span class="ms-auto flex gap-1">
                        <Button v-if="row.state !== 'void' && row.balance > 0" size="sm" variant="outline" @click="openPayment(row)">{{ t('platform.record_payment') }}</Button>
                        <Button v-if="row.state !== 'void' && !row.payments.length" size="sm" variant="ghost" class="text-destructive" @click="voidInvoice(row)">{{ t('platform.void') }}</Button>
                    </span>
                </div>
                <p v-if="row.description" class="px-3 pb-2 text-xs text-muted-foreground">{{ row.description }}</p>
                <ul v-if="row.payments.length" class="border-t bg-muted/30 text-sm">
                    <li v-for="p in row.payments" :key="p.id" class="flex flex-wrap items-center gap-x-6 gap-y-1 px-3 py-2">
                        <span>{{ p.paid_on }}</span>
                        <span class="font-medium">{{ p.amount }}</span>
                        <span>{{ t(`platform.methods.${p.method}`) }}</span>
                        <span v-if="p.reference" class="text-muted-foreground">{{ p.reference }}</span>
                        <span v-if="p.note" class="text-muted-foreground">{{ p.note }}</span>
                        <Button size="sm" variant="ghost" class="ms-auto text-destructive" @click="removePayment(p)">{{ t('general.delete', { name: '' }) }}</Button>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Renew dialog -->
        <ModalDialog
            :open="renewOpen" :title="t('platform.renew')" :confirmText="t('platform.renew')"
            :cancel-text="t('general.close')" :closeable="true" :submitting="renew.processing"
            @update:open="renewOpen = $event" @confirm="doRenew" @cancel="renewOpen = false"
        >
            <form class="grid gap-4 py-4" @submit.prevent="doRenew">
                <NextInput v-model="renew.months" type="number" :label="t('platform.renew_months')" :error="renew.errors.months" />
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="renew.invoice" type="checkbox" />
                    {{ t('platform.renew_invoice') }}
                </label>
            </form>
        </ModalDialog>

        <!-- Block dialog -->
        <ModalDialog
            :open="blockOpen" :title="t('platform.block')" :confirmText="t('platform.block')"
            :cancel-text="t('general.close')" :closeable="true" :submitting="block.processing"
            @update:open="blockOpen = $event" @confirm="doBlock" @cancel="blockOpen = false"
        >
            <form class="grid gap-4 py-4" @submit.prevent="doBlock">
                <p class="text-sm text-muted-foreground">{{ t('platform.block_hint') }}</p>
                <NextInput v-model="block.reason" :label="t('platform.block_reason')" :error="block.errors.reason" />
            </form>
        </ModalDialog>

        <!-- Invoice dialog -->
        <ModalDialog
            :open="invoiceOpen" :title="t('platform.new_invoice')" :confirmText="t('general.create')"
            :cancel-text="t('general.close')" :closeable="true" :submitting="invoice.processing"
            @update:open="invoiceOpen = $event" @confirm="saveInvoice" @cancel="invoiceOpen = false"
        >
            <form class="grid gap-4 py-4" @submit.prevent="saveInvoice">
                <div class="grid gap-4 sm:grid-cols-2">
                    <NextDatePicker v-model="invoice.issued_on" :label="t('platform.issued_on')" is-required :error="invoice.errors.issued_on" />
                    <NextDatePicker v-model="invoice.due_on" :label="t('platform.due_on')" :error="invoice.errors.due_on" />
                    <NextDatePicker v-model="invoice.period_from" :label="t('platform.period_from')" :error="invoice.errors.period_from" />
                    <NextDatePicker v-model="invoice.period_to" :label="t('platform.period_to')" :error="invoice.errors.period_to" />
                    <NextInput v-model="invoice.amount" type="number" is-required :label="t('platform.amount')" :error="invoice.errors.amount" />
                    <NextInput v-model="invoice.currency" is-required :label="t('platform.currency')" :error="invoice.errors.currency" />
                </div>
                <NextInput v-model="invoice.description" :label="t('platform.description')" :error="invoice.errors.description" />
            </form>
        </ModalDialog>

        <!-- Payment dialog -->
        <ModalDialog
            :open="paymentOpen" :title="t('platform.record_payment')" :confirmText="t('platform.record_payment')"
            :cancel-text="t('general.close')" :closeable="true" :submitting="payment.processing"
            @update:open="paymentOpen = $event" @confirm="savePayment" @cancel="paymentOpen = false"
        >
            <form class="grid gap-4 py-4" @submit.prevent="savePayment">
                <p v-if="paymentFor" class="text-sm text-muted-foreground">{{ paymentFor.number }} — {{ t('platform.balance') }}: {{ paymentFor.balance }} {{ paymentFor.currency }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <NextInput v-model="payment.amount" type="number" is-required :label="t('platform.amount')" :error="payment.errors.amount" />
                    <NextDatePicker v-model="payment.paid_on" :label="t('platform.paid_on')" is-required :error="payment.errors.paid_on" />
                    <NextSelect
                        v-model="payment.method"
                        :options="methodOptions"
                        label-key="label"
                        value-key="id"
                        :reduce="(option) => option.id"
                        :floating-text="t('platform.method')"
                        :error="payment.errors.method"
                    />
                    <NextInput v-model="payment.reference" :label="t('platform.reference')" :error="payment.errors.reference" />
                </div>
                <NextInput v-model="payment.note" :label="t('platform.note')" :error="payment.errors.note" />
            </form>
        </ModalDialog>
    </AppLayout>
</template>
