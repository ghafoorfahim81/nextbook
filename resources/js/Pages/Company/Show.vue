<script setup>
/**
 * One company, read-only: who it is, how its books are kept, how to reach it,
 * its licence and its branches. Reached from the companies page; the edit page
 * is the same steps as the set-up wizard.
 */
import { computed, reactive, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, Check, Home, Pencil, Star, Store } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Layout.vue'
import CompanySettingsForm from '@/Components/Company/CompanySettingsForm.vue'

const props = defineProps({
    company: { type: Object, required: true },
    isActive: { type: Boolean, default: false },
    isHome: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    branches: { type: Array, default: () => [] },
    canViewBranches: { type: Boolean, default: false },
    // The option lists every page receives (LookupShared).
    businessTypes: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
    calendarTypes: { type: Array, default: () => [] },
    workingStyles: { type: Array, default: () => [] },
    costingMethods: { type: Array, default: () => [] },
    // The company's shared settings (phase 6), read-only here.
    settings: { type: Object, default: () => ({}) },
    cashAccounts: { type: Array, default: () => [] },
})

// The form edits in place; give it a copy so nothing here can touch the prop.
const settingsView = reactive(JSON.parse(JSON.stringify(props.settings)))

const { t } = useI18n()
const logoBroken = ref(false)

const labelOf = (options, id) => options.find((option) => option.id === id)?.name ?? id ?? null
const initials = computed(() => (props.company.name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase())

const sections = computed(() => {
    const c = props.company

    return [
        {
            key: 'identity',
            rows: [
                [t('company.name_en'), c.name_en],
                [t('company.name_fa'), c.name_fa],
                [t('company.name_pa'), c.name_pa],
                [t('company.abbreviation'), c.abbreviation],
                [t('company.business_type'), labelOf(props.businessTypes, c.business_type)],
            ],
        },
        {
            key: 'settings',
            rows: [
                [t('company.currency'), c.currency],
                [t('company.wizard.language'), labelOf(props.locales, c.locale)],
                [t('company.wizard.calendar'), labelOf(props.calendarTypes, c.calendar_type)],
                [t('company.wizard.working_style'), labelOf(props.workingStyles, c.working_style)],
                [t('company.costing_method'), labelOf(props.costingMethods, c.costing_method)],
            ],
        },
        {
            key: 'contact',
            rows: [
                [t('company.phone'), c.phone],
                [t('company.email'), c.email],
                [t('company.website'), c.website],
                [t('company.city'), [c.city, c.country].filter(Boolean).join(', ')],
                [t('company.address'), c.address],
                [t('company.wizard.license_number'), c.license_number],
                [t('company.wizard.license_issued_at'), c.license_issued_at],
                [t('company.wizard.license_expires_at'), c.license_expires_at],
                [t('company.show.created_on'), c.created_at],
            ],
        },
    ]
})
</script>

<template>
    <AppLayout :title="company.name">
        <div class="w-full px-4 py-6 sm:px-6">
            <Link
                :href="route('workspace.companies')"
                class="mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
            >
                <ArrowLeft class="size-4 rtl:rotate-180" /> {{ t('company.wizard.back_to_companies') }}
            </Link>

            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
                <div class="flex min-w-0 items-center gap-4">
                    <img
                        v-if="company.logo_url && !logoBroken"
                        :src="company.logo_url"
                        :alt="company.name"
                        class="size-16 shrink-0 rounded-xl object-cover ring-1 ring-border"
                        @error="logoBroken = true"
                    />
                    <div
                        v-else
                        class="flex size-16 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-violet-400 text-xl font-semibold text-white"
                    >
                        {{ initials }}
                    </div>
                    <div class="min-w-0">
                        <h1 dir="auto" class="truncate text-start text-2xl font-semibold tracking-tight text-foreground">{{ company.name }}</h1>
                        <p class="truncate text-sm text-muted-foreground">{{ labelOf(businessTypes, company.business_type) || '—' }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span v-if="isActive" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">
                                <Check class="size-3" /> {{ t('workspace.current') }}
                            </span>
                            <span v-if="isHome" class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-muted-foreground">
                                <Home class="size-3" /> {{ t('workspace.home') }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-muted-foreground">
                                <Store class="size-3" /> {{ t('workspace.branches_count', { count: branches.length }, branches.length) }}
                            </span>
                        </div>
                    </div>
                </div>

                <Link
                    v-if="canEdit"
                    :href="route('companies.edit', company.id)"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                >
                    <Pencil class="size-4" /> {{ t('company.show.edit') }}
                </Link>
                <p v-else-if="!isActive" class="text-sm text-muted-foreground">{{ t('company.show.edit_active_only') }}</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
                <div class="space-y-6">
                    <section v-for="section in sections" :key="section.key" class="rounded-2xl border border-border bg-card shadow-sm">
                        <h2 class="border-b border-border px-5 py-3 text-sm font-semibold text-foreground">
                            {{ t(`company.wizard.steps.${section.key}`) }}
                        </h2>
                        <dl class="grid gap-x-8 gap-y-3 px-5 py-4 text-sm sm:grid-cols-2">
                            <div v-for="[label, value] in section.rows" :key="label" class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">{{ label }}</dt>
                                <dd dir="auto" class="truncate text-end font-medium" :class="value ? 'text-foreground' : 'text-muted-foreground/60'">
                                    {{ value || t('company.wizard.not_set') }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border border-border bg-card shadow-sm">
                        <div class="border-b border-border px-5 py-3">
                            <h2 class="text-sm font-semibold text-foreground">{{ t('company.settings.title') }}</h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ t('company.settings.description') }}</p>
                        </div>
                        <div class="px-5 py-4">
                            <CompanySettingsForm :settings="settingsView" :cash-accounts="cashAccounts" disabled />
                        </div>
                    </section>
                </div>

                <section class="h-fit rounded-2xl border border-border bg-card shadow-sm">
                    <h2 class="border-b border-border px-5 py-3 text-sm font-semibold text-foreground">{{ t('company.show.branches') }}</h2>
                    <ul v-if="branches.length" class="divide-y divide-border">
                        <li v-for="branch in branches" :key="branch.id">
                            <component
                                :is="canViewBranches ? Link : 'div'"
                                v-bind="canViewBranches ? { href: route('branches.show', branch.id) } : {}"
                                class="flex items-center gap-3 px-5 py-3 text-sm"
                                :class="canViewBranches ? 'transition hover:bg-muted/50' : ''"
                            >
                                <Store class="size-4 shrink-0 text-muted-foreground" />
                                <span class="min-w-0 flex-1">
                                    <span dir="auto" class="block truncate font-medium text-foreground">{{ branch.name }}</span>
                                    <span v-if="branch.location" class="block truncate text-xs text-muted-foreground">{{ branch.location }}</span>
                                </span>
                                <Star v-if="branch.is_main" class="size-4 shrink-0 text-amber-500" :title="t('workspace.main')" />
                            </component>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-4 text-sm text-muted-foreground">{{ t('company.show.no_branches') }}</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
