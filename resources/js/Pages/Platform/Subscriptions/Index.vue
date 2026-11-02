<script setup>
/**
 * Customers (phase 10): every company with its package, state, end date and
 * how much of the package it uses. Platform admin only.
 */
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/Layout.vue'
import { Badge } from '@/Components/ui/badge'

const props = defineProps({
    companies: Object,
    filters: { type: Object, default: () => ({}) },
    totals: { type: Object, default: () => ({}) },
})

const { t } = useI18n()

const search = ref(props.filters.search ?? '')
let timer = null
const searchChanged = () => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get(route('platform.subscriptions.index'), { search: search.value || undefined }, { preserveState: true, replace: true })
    }, 300)
}

const stateClass = {
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    expiring: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    expired: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    blocked: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    none: 'bg-muted text-muted-foreground',
}

const usage = (used, max) => (max ? `${used} / ${max}` : `${used}`)
</script>

<template>
    <AppLayout :title="t('platform.customers')">
        <p class="text-sm text-muted-foreground">{{ t('platform.subscriptions_hint') }}</p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="key in ['companies', 'subscribed', 'expiring', 'blocked_or_expired']" :key="key" class="rounded-lg border p-4">
                <div class="text-xs text-muted-foreground">{{ t(`platform.totals.${key}`) }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ totals[key] ?? 0 }}</div>
            </div>
        </div>

        <input
            v-model="search"
            type="search"
            :placeholder="t('platform.search')"
            class="h-10 w-full max-w-sm rounded-md border bg-background px-3 text-sm"
            @input="searchChanged"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-xs text-muted-foreground">
                    <tr>
                        <th class="p-3 text-start">{{ t('platform.company') }}</th>
                        <th class="p-3 text-start">{{ t('platform.package') }}</th>
                        <th class="p-3 text-start">{{ t('platform.state') }}</th>
                        <th class="p-3 text-start">{{ t('platform.ends_on') }}</th>
                        <th class="p-3 text-start">{{ t('platform.users') }}</th>
                        <th class="p-3 text-start">{{ t('platform.branches') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="company in companies.data" :key="company.id" class="border-t hover:bg-muted/30">
                        <td class="p-3">
                            <Link :href="route('platform.subscriptions.show', company.id)" class="font-medium text-primary hover:underline">{{ company.name }}</Link>
                        </td>
                        <td class="p-3">{{ company.package ?? '—' }}</td>
                        <td class="p-3">
                            <Badge :class="stateClass[company.state]" variant="secondary">{{ t(`platform.states.${company.state}`) }}</Badge>
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            {{ company.ends_on ?? '—' }}
                            <span v-if="company.state === 'expiring'" class="text-xs text-muted-foreground">({{ t('platform.days_left', { n: company.days_left }) }})</span>
                        </td>
                        <td class="p-3">{{ usage(company.users, company.max_users) }}</td>
                        <td class="p-3">{{ usage(company.branches, company.max_branches) }}</td>
                    </tr>
                    <tr v-if="!companies.data.length">
                        <td colspan="6" class="p-6 text-center text-muted-foreground">—</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="companies.links && companies.last_page > 1" class="flex flex-wrap gap-1">
            <Link
                v-for="(link, index) in companies.links"
                :key="index"
                :href="link.url ?? ''"
                preserve-state
                class="rounded border px-3 py-1 text-sm"
                :class="[link.active ? 'bg-primary text-primary-foreground' : '', link.url ? '' : 'pointer-events-none opacity-40']"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
