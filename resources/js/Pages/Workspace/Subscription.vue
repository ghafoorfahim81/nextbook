<script setup>
/**
 * "My subscription" (phase 10): the company's own package, end date, usage,
 * and invoices. Read only.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/Layout.vue'
import { Badge } from '@/Components/ui/badge'

const props = defineProps({
    plan: { type: Object, default: null },
    usage: Object,
    availableModules: { type: Array, default: () => [] },
    invoices: { type: Array, default: () => [] },
})

const { t } = useI18n()
const moduleName = (key) => t(`company.modules.${key}`)

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

const limit = (used, max) => (max ? `${used} / ${max}` : `${used} (${t('platform.unlimited')})`)
const includedModules = computed(() => props.plan?.modules ?? props.availableModules)
</script>

<template>
    <AppLayout :title="t('platform.my_subscription')">
        <p class="text-sm text-muted-foreground">{{ t('platform.my_subscription_hint') }}</p>

        <p v-if="!plan" class="rounded-lg border p-4 text-sm">{{ t('platform.no_plan') }}</p>

        <template v-else>
            <section class="grid gap-3 rounded-lg border p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-semibold">{{ plan.package }}</h2>
                    <Badge :class="stateClass[plan.state]" variant="secondary">{{ t(`platform.states.${plan.state}`) }}</Badge>
                    <span v-if="plan.state === 'expiring'" class="text-sm text-muted-foreground">{{ t('platform.days_left', { n: plan.days_left }) }}</span>
                </div>
                <p v-if="plan.description" class="text-sm text-muted-foreground">{{ plan.description }}</p>
                <p v-if="plan.block_reason" class="text-sm text-destructive">{{ t('platform.banner.blocked_reason', { reason: plan.block_reason }) }}</p>
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><span class="text-muted-foreground">{{ t('platform.starts_on') }}:</span> {{ plan.starts_on }}</div>
                    <div><span class="text-muted-foreground">{{ t('platform.ends_on') }}:</span> {{ plan.ends_on }}</div>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border p-4">
                    <div class="text-xs text-muted-foreground">{{ t('platform.users') }}</div>
                    <div class="mt-1 text-lg font-semibold">{{ limit(usage.users, plan.max_users) }}</div>
                </div>
                <div class="rounded-lg border p-4">
                    <div class="text-xs text-muted-foreground">{{ t('platform.branches') }}</div>
                    <div class="mt-1 text-lg font-semibold">{{ limit(usage.branches, plan.max_branches) }}</div>
                </div>
            </section>

            <section class="grid gap-2 rounded-lg border p-4">
                <h3 class="text-sm font-medium">{{ t('platform.package_modules') }}</h3>
                <div class="flex flex-wrap gap-1">
                    <Badge v-for="key in includedModules" :key="key" variant="secondary">{{ moduleName(key) }}</Badge>
                </div>
            </section>

            <section v-if="invoices.length" class="grid gap-3">
                <h3 class="font-semibold">{{ t('platform.invoices') }}</h3>
                <div v-for="row in invoices" :key="row.number" class="rounded-lg border">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 p-3 text-sm">
                        <span class="font-medium">{{ row.number }}</span>
                        <Badge :class="invoiceClass[row.state]" variant="secondary">{{ t(`platform.invoice_states.${row.state}`) }}</Badge>
                        <span>{{ row.issued_on }}</span>
                        <span>{{ t('platform.amount') }}: <strong>{{ row.amount }} {{ row.currency }}</strong></span>
                        <span>{{ t('platform.paid') }}: {{ row.paid }}</span>
                        <span>{{ t('platform.balance') }}: {{ row.balance }}</span>
                    </div>
                    <p v-if="row.description" class="px-3 pb-2 text-xs text-muted-foreground">{{ row.description }}</p>
                    <ul v-if="row.payments.length" class="border-t bg-muted/30 text-sm">
                        <li v-for="(p, i) in row.payments" :key="i" class="flex flex-wrap gap-x-6 gap-y-1 px-3 py-2">
                            <span>{{ p.paid_on }}</span>
                            <span class="font-medium">{{ p.amount }}</span>
                            <span>{{ t(`platform.methods.${p.method}`) }}</span>
                            <span v-if="p.reference" class="text-muted-foreground">{{ p.reference }}</span>
                        </li>
                    </ul>
                </div>
            </section>
        </template>
    </AppLayout>
</template>
