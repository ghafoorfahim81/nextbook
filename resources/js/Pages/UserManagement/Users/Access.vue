<script setup>
/**
 * Company & branch access for one user (phase 5). Super-admins only; shows
 * just the companies the super-admin belongs to, and saving touches grants in
 * those alone (UserAccessService::syncAccess).
 */
import { useForm, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, Building2, Home, Loader2, Save, ShieldCheck, Star } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Layout.vue'

const props = defineProps({
    user: { type: Object, required: true },
    userIsSuperAdmin: { type: Boolean, default: false },
    companies: { type: Array, required: true },
    granted: { type: Object, required: true },
    // Phase 6: the shared role set, and which of them the user holds where.
    roles: { type: Array, default: () => [] },
    rolesByCompany: { type: Object, default: () => ({}) },
})

const { t } = useI18n()

const form = useForm({
    companies: [...props.granted.companies],
    branches: [...props.granted.branches],
    roles: Object.fromEntries(Object.entries(props.rolesByCompany).map(([id, ids]) => [id, [...ids]])),
})

const superAdminRoleId = props.roles.find((role) => role.slug === 'super-admin')?.id
// Roles are per company, so is super-admin: read it off this company's picker.
const superAdminIn = (company) => (form.roles[company.id] ?? []).includes(superAdminRoleId)

function toggleRole(company, role) {
    if (!company.can_manage_roles || !hasCompany(company)) return
    const held = form.roles[company.id] ?? []
    form.roles = {
        ...form.roles,
        [company.id]: held.includes(role.id) ? held.filter((id) => id !== role.id) : [...held, role.id],
    }
}

const hasCompany = (company) => company.is_home || form.companies.includes(company.id)

function toggleCompany(company) {
    if (company.is_home) return

    if (form.companies.includes(company.id)) {
        form.companies = form.companies.filter((id) => id !== company.id)
        // A branch cannot outlive its company's grant.
        const ids = company.branches.map((branch) => branch.id)
        form.branches = form.branches.filter((id) => !ids.includes(id))
    } else {
        form.companies = [...form.companies, company.id]
    }
}

function toggleBranch(company, branch) {
    if (!hasCompany(company) || superAdminIn(company)) return

    form.branches = form.branches.includes(branch.id)
        ? form.branches.filter((id) => id !== branch.id)
        : [...form.branches, branch.id]
}

const save = () => form.put(route('users.access.update', props.user.id), { preserveScroll: true })
</script>

<template>
    <AppLayout :title="t('workspace.access.title')">
        <div class="w-full px-4 pt-6 sm:px-6">
            <Link :href="route('users.edit', user.id)" class="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground">
                <ArrowLeft class="size-4 rtl:rotate-180" /> {{ t('workspace.access.back') }}
            </Link>

            <div class="mb-6">
                <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ t('workspace.access.title') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('workspace.access.subtitle', { name: user.name }) }}</p>
                <p class="text-xs text-muted-foreground/80">{{ user.email }}</p>
            </div>

            <div v-if="companies.some(superAdminIn)" class="mb-4 flex items-start gap-2 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm text-foreground">
                <ShieldCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                {{ t('workspace.access.all_branches') }}
            </div>

            <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                <section
                    v-for="company in companies"
                    :key="company.id"
                    class="rounded-xl border bg-card shadow-sm transition"
                    :class="hasCompany(company) ? 'border-primary/40' : 'border-border'"
                >
                    <div class="flex items-center gap-3 border-b border-border px-4 py-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Building2 class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p dir="auto" class="truncate text-start font-semibold text-foreground">{{ company.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ company.business_type || '—' }}</p>
                        </div>
                        <span v-if="company.is_home" class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                            <Home class="size-3" /> {{ t('workspace.home') }}
                        </span>
                        <button
                            v-else
                            type="button"
                            role="switch"
                            :aria-checked="hasCompany(company)"
                            :aria-label="t('workspace.access.has_access')"
                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                            :class="hasCompany(company) ? 'bg-primary' : 'bg-muted-foreground/30'"
                            @click="toggleCompany(company)"
                        >
                            <span
                                class="inline-block size-5 rounded-full bg-white shadow transition"
                                :class="hasCompany(company) ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"
                            />
                        </button>
                    </div>

                    <div class="px-4 py-3">
                        <p v-if="company.is_home" class="mb-3 text-xs text-muted-foreground">{{ t('workspace.access.home_locked') }}</p>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ t('workspace.access.branches') }}</p>
                        <p v-if="!company.branches.length" class="mt-2 text-sm text-muted-foreground">{{ t('workspace.access.no_branches') }}</p>
                        <div v-else class="mt-2 grid gap-2 sm:grid-cols-2" :class="!hasCompany(company) ? 'pointer-events-none opacity-40' : ''">
                            <label
                                v-for="branch in company.branches"
                                :key="branch.id"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                                :class="(superAdminIn(company) || form.branches.includes(branch.id)) && hasCompany(company)
                                    ? 'border-primary/50 bg-primary/5 text-foreground'
                                    : 'border-border text-muted-foreground hover:border-primary/30'"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-border text-primary focus:ring-primary/40"
                                    :checked="superAdminIn(company) || form.branches.includes(branch.id)"
                                    :disabled="superAdminIn(company) || !hasCompany(company)"
                                    @change="toggleBranch(company, branch)"
                                />
                                <span dir="auto" class="min-w-0 flex-1 truncate text-start">{{ branch.name }}</span>
                                <Star v-if="branch.is_main" class="size-3.5 shrink-0 text-amber-500" :title="t('workspace.main')" />
                                <Home v-if="branch.is_home" class="size-3.5 shrink-0 text-muted-foreground" :title="t('workspace.home')" />
                            </label>
                        </div>
                        <p v-if="company.branches.length && !superAdminIn(company) && hasCompany(company)" class="mt-2 text-xs text-muted-foreground">
                            {{ t('workspace.access.branches_hint') }}
                        </p>
                        <!-- Roles in this company (phase 6). -->
                        <div class="mt-4 border-t border-border pt-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ t('workspace.access.roles') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2" :class="!hasCompany(company) ? 'pointer-events-none opacity-40' : ''">
                                <button
                                    v-for="role in roles"
                                    :key="role.id"
                                    type="button"
                                    class="rounded-full border px-3 py-1 text-xs font-medium transition disabled:cursor-not-allowed"
                                    :class="(form.roles[company.id] ?? []).includes(role.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:border-primary/40'"
                                    :disabled="!company.can_manage_roles || !hasCompany(company)"
                                    :aria-pressed="(form.roles[company.id] ?? []).includes(role.id)"
                                    @click="toggleRole(company, role)"
                                >
                                    {{ role.name }}
                                </button>
                            </div>
                            <p v-if="!company.can_manage_roles" class="mt-2 text-xs text-muted-foreground">{{ t('workspace.access.roles_locked') }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Sticky within the content column, so it never covers the sidebar. -->
        <div class="sticky bottom-0 z-20 mt-6 border-t border-border bg-background/90 px-4 py-3 backdrop-blur sm:px-6">
            <div class="flex justify-end">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:opacity-60"
                    :disabled="form.processing || !form.isDirty"
                    @click="save"
                >
                    <Loader2 v-if="form.processing" class="size-4 motion-safe:animate-spin" />
                    <Save v-else class="size-4" />
                    {{ t('workspace.access.save') }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
