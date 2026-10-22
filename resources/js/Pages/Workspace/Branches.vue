<script setup>
/**
 * Switch branch (phase 5): the branches of the active company this user may
 * act in. Reached from the user menu.
 */
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { ArrowRight, Check, Home, Loader2, MapPin, Star, Store } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Layout.vue'

defineProps({
    branches: { type: Array, required: true },
    companyName: { type: String, default: '' },
})

const { t } = useI18n()
const switchingTo = ref(null)

function switchTo(branch) {
    if (branch.is_active || switchingTo.value) return

    switchingTo.value = branch.id
    router.post(route('branches.switch'), { branch_id: branch.id }, {
        onFinish: () => { switchingTo.value = null },
    })
}
</script>

<template>
    <AppLayout :title="t('workspace.branches_title')">
        <div class="w-full px-4 py-6 sm:px-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ t('workspace.branches_title') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('workspace.branches_subtitle', { company: companyName }) }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                <div
                    v-for="branch in branches"
                    :key="branch.id"
                    class="flex flex-col rounded-xl border bg-card p-5 shadow-sm transition"
                    :class="branch.is_active
                        ? 'border-primary ring-2 ring-primary/20'
                        : 'border-border hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md'"
                >
                    <div class="flex items-start gap-4">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Store class="size-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-base font-semibold text-foreground" :title="branch.name">{{ branch.name }}</h2>
                            <p v-if="branch.location" class="flex items-center gap-1 truncate text-sm text-muted-foreground">
                                <MapPin class="size-3.5 shrink-0" /> {{ branch.location }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                        <span v-if="branch.is_active" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">
                            <Check class="size-3" /> {{ t('workspace.current') }}
                        </span>
                        <span v-if="branch.is_main" class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-amber-600 dark:text-amber-400">
                            <Star class="size-3" /> {{ t('workspace.main') }}
                        </span>
                        <span v-if="branch.is_home" class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-muted-foreground">
                            <Home class="size-3" /> {{ t('workspace.home') }}
                        </span>
                    </div>

                    <div class="mt-5 flex items-center justify-end border-t border-border pt-4">
                        <span v-if="branch.is_active" class="text-sm text-muted-foreground">{{ t('workspace.working_here') }}</span>
                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary/40 px-3 py-1.5 text-sm font-medium text-primary transition hover:bg-primary hover:text-primary-foreground disabled:opacity-50"
                            :disabled="switchingTo !== null"
                            @click="switchTo(branch)"
                        >
                            <Loader2 v-if="switchingTo === branch.id" class="size-4 motion-safe:animate-spin" />
                            <template v-else>{{ t('workspace.switch') }} <ArrowRight class="size-4 rtl:rotate-180" /></template>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
