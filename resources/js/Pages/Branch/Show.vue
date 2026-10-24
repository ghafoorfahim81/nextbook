<script setup>
/**
 * One branch, read-only. Editing and deleting start here; the list of branches
 * is the workspace picker.
 */
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, Check, MapPin, Pencil, Star, Store, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Layout.vue'
import { useDeleteResource } from '@/composables/useDeleteResource'

const props = defineProps({
    branch: { type: Object, required: true },
    isActive: { type: Boolean, default: false },
    can: { type: Object, default: () => ({ update: false, delete: false }) },
})

const { t } = useI18n()
const { deleteResource } = useDeleteResource()

function remove() {
    deleteResource('branches.destroy', props.branch.id, {
        title: t('general.delete', { name: t('admin.branch.branch') }),
        name: props.branch.name,
        successMessage: t('general.delete_success', { name: t('admin.branch.branch') }),
    })
}

const rows = () => [
    [t('general.name'), props.branch.name],
    [t('admin.branch.location'), props.branch.location],
    [t('admin.shared.parent'), props.branch.parent?.name],
    [t('admin.branch.company'), props.branch.company_name],
    [t('admin.shared.remark'), props.branch.remark],
    [t('admin.branch.created_on'), props.branch.created_at],
    [t('general.created_by'), props.branch.created_by?.name],
]
</script>

<template>
    <AppLayout :title="branch.name">
        <div class="w-full px-4 py-6 sm:px-6">
            <Link
                :href="route('workspace.branches')"
                class="mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
            >
                <ArrowLeft class="size-4 rtl:rotate-180" /> {{ t('admin.branch.back') }}
            </Link>

            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Store class="size-8" />
                    </div>
                    <div class="min-w-0">
                        <h1 dir="auto" class="truncate text-start text-2xl font-semibold tracking-tight text-foreground">{{ branch.name }}</h1>
                        <p v-if="branch.location" class="flex items-center gap-1 truncate text-sm text-muted-foreground">
                            <MapPin class="size-3.5 shrink-0" /> {{ branch.location }}
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span v-if="isActive" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">
                                <Check class="size-3" /> {{ t('workspace.current') }}
                            </span>
                            <span v-if="branch.is_main" class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-amber-600 dark:text-amber-400">
                                <Star class="size-3" /> {{ t('workspace.main') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="can.delete"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-500/40 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-500/10"
                        @click="remove"
                    >
                        <Trash2 class="size-4" /> {{ t('general.delete', { name: t('admin.branch.branch') }) }}
                    </button>
                    <Link
                        v-if="can.update"
                        :href="route('branches.edit', branch.id)"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                    >
                        <Pencil class="size-4" /> {{ t('admin.branch.edit') }}
                    </Link>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
                <section class="rounded-2xl border border-border bg-card shadow-sm">
                    <h2 class="border-b border-border px-5 py-3 text-sm font-semibold text-foreground">{{ t('admin.branch.details') }}</h2>
                    <dl class="grid gap-x-8 gap-y-3 px-5 py-4 text-sm sm:grid-cols-2">
                        <div v-for="[label, value] in rows()" :key="label" class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">{{ label }}</dt>
                            <dd dir="auto" class="truncate text-end font-medium" :class="value ? 'text-foreground' : 'text-muted-foreground/60'">
                                {{ value || t('company.wizard.not_set') }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="h-fit rounded-2xl border border-border bg-card shadow-sm">
                    <h2 class="border-b border-border px-5 py-3 text-sm font-semibold text-foreground">{{ t('admin.branch.sub_branches') }}</h2>
                    <ul v-if="branch.children?.length" class="divide-y divide-border">
                        <li v-for="child in branch.children" :key="child.id">
                            <Link :href="route('branches.show', child.id)" class="flex items-center gap-3 px-5 py-3 text-sm transition hover:bg-muted/50">
                                <Store class="size-4 shrink-0 text-muted-foreground" />
                                <span class="min-w-0 flex-1">
                                    <span dir="auto" class="block truncate font-medium text-foreground">{{ child.name }}</span>
                                    <span v-if="child.location" class="block truncate text-xs text-muted-foreground">{{ child.location }}</span>
                                </span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-4 text-sm text-muted-foreground">{{ t('admin.branch.no_sub_branches') }}</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
