<script setup>
/**
 * Switch company (phase 5): every company the user may act in, and — for a
 * super-admin — where a new one is started. Reached from the user menu.
 */
import { reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { ArrowRight, Building2, Check, Home, Loader2, Plus, Settings, Store } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Layout.vue'

const props = defineProps({
    companies: { type: Array, required: true },
    canCreate: { type: Boolean, default: false },
})

const { t } = useI18n()
const switchingTo = ref(null)
// A logo whose file is missing falls back to the initials, not the alt text.
const brokenLogos = reactive({})

const initials = (name) => (name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()

function switchTo(company) {
    if (company.is_active || !company.can_switch || switchingTo.value) return

    switchingTo.value = company.id
    router.post(route('companies.switch'), { company_id: company.id }, {
        onFinish: () => { switchingTo.value = null },
    })
}
</script>

<template>
    <AppLayout :title="t('workspace.companies_title')">
        <div class="w-full px-4 py-6 sm:px-6">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ t('workspace.companies_title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('workspace.companies_subtitle') }}</p>
                </div>
                <Link
                    v-if="canCreate"
                    :href="route('companies.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                >
                    <Plus class="size-4" />
                    {{ t('workspace.new_company') }}
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                <div
                    v-for="company in companies"
                    :key="company.id"
                    class="group relative flex flex-col rounded-xl border bg-card p-5 shadow-sm transition"
                    :class="company.is_active
                        ? 'border-primary ring-2 ring-primary/20'
                        : 'border-border hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md'"
                >
                    <div class="flex items-start gap-4">
                        <img
                            v-if="company.logo_url && !brokenLogos[company.id]"
                            :src="company.logo_url"
                            :alt="company.name"
                            class="size-12 shrink-0 rounded-lg object-cover"
                            @error="brokenLogos[company.id] = true"
                        />
                        <div
                            v-else
                            class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-violet-400 text-base font-semibold text-white"
                        >
                            {{ initials(company.name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-base font-semibold text-foreground" :title="company.name">{{ company.name }}</h2>
                            <p class="truncate text-sm text-muted-foreground">
                                {{ company.business_type || '—' }}<template v-if="company.city"> · {{ company.city }}</template>
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                        <span v-if="company.is_active" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">
                            <Check class="size-3" /> {{ t('workspace.current') }}
                        </span>
                        <span v-if="company.is_home" class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-muted-foreground">
                            <Home class="size-3" /> {{ t('workspace.home') }}
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-muted-foreground">
                            <Store class="size-3" /> {{ t('workspace.branches_count', { count: company.branches }, company.branches) }}
                        </span>
                    </div>

                    <div class="mt-5 flex items-center justify-end border-t border-border pt-4">
                        <Link
                            v-if="company.is_active"
                            :href="route('company.show')"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                        >
                            <Settings class="size-4" /> {{ t('workspace.settings') }}
                        </Link>
                        <span v-else-if="!company.can_switch" class="text-xs text-muted-foreground">
                            {{ t('workspace.no_branch') }}
                        </span>
                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary/40 px-3 py-1.5 text-sm font-medium text-primary transition hover:bg-primary hover:text-primary-foreground disabled:opacity-50"
                            :disabled="switchingTo !== null"
                            @click="switchTo(company)"
                        >
                            <Loader2 v-if="switchingTo === company.id" class="size-4 motion-safe:animate-spin" />
                            <template v-else>{{ t('workspace.switch') }} <ArrowRight class="size-4 rtl:rotate-180" /></template>
                        </button>
                    </div>
                </div>

                <!-- Starting a new company lives here, beside the ones you have. -->
                <Link
                    v-if="canCreate"
                    :href="route('companies.create')"
                    class="flex min-h-[200px] flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-border p-5 text-center text-muted-foreground transition hover:border-primary/50 hover:bg-primary/5 hover:text-primary"
                >
                    <span class="flex size-12 items-center justify-center rounded-full bg-muted">
                        <Building2 class="size-6" />
                    </span>
                    <span class="text-sm font-medium">{{ t('workspace.new_company') }}</span>
                    <span class="text-xs">{{ t('workspace.new_company_hint') }}</span>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
