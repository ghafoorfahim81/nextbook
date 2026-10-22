<script setup>
/**
 * The set-up screen shown while a new company is created (phase 5).
 *
 * Steps follow CompanySetupService::SUMMARY_STEPS, in the order the server
 * does the work. The server answers once, at the end, so while the request is
 * in flight the parent advances `activeIndex` on a timer and holds the last
 * step open; when the answer arrives every step is ticked with the count the
 * server actually wrote (`summary`). Nothing here claims a step is done before
 * the server has said so.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Check, Loader2 } from 'lucide-vue-next'
import ApplicationMark from '@/Components/ApplicationMark.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    steps: { type: Array, required: true },
    activeIndex: { type: Number, default: 0 },
    // Filled once the server has answered.
    summary: { type: Object, default: null },
})

const { t } = useI18n()

const finished = computed(() => props.summary !== null)

const stepState = (index) => {
    if (finished.value || index < props.activeIndex) return 'done'
    if (index === props.activeIndex) return 'active'
    return 'pending'
}

// The bar never reaches the end before the server answers.
const progress = computed(() => {
    if (finished.value) return 100
    return Math.min(95, Math.round(((props.activeIndex + 0.5) / props.steps.length) * 100))
})

// Steps that are one row by nature (the company, its branch, your access)
// read better without "1 created".
const countFor = (key) => {
    const count = props.summary?.[key]
    return typeof count === 'number' && count > 1 ? t('company.setup.created', { count }) : null
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="opacity-0"
    >
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            :aria-label="t('company.setup.title')"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
                <div class="flex flex-col items-center gap-3 px-6 pt-8 text-center">
                    <div class="relative flex size-16 items-center justify-center">
                        <span
                            v-if="!finished"
                            class="absolute inset-0 rounded-full bg-primary/20 motion-safe:animate-ping"
                        />
                        <span
                            class="relative flex size-16 items-center justify-center rounded-full transition-colors duration-500"
                            :class="finished ? 'bg-emerald-500 text-white' : 'bg-primary/10 text-primary'"
                        >
                            <Check v-if="finished" class="size-8 motion-safe:animate-[setup-pop_400ms_ease-out]" />
                            <ApplicationMark v-else class="size-8" />
                        </span>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">
                        {{ finished ? t('company.setup.done_title') : t('company.setup.title') }}
                    </h2>
                    <p class="text-sm text-muted-foreground" aria-live="polite">
                        {{ finished ? t('company.setup.done_subtitle') : t('company.setup.subtitle') }}
                    </p>
                </div>

                <div class="px-6 pt-5">
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full transition-[width] duration-700 ease-out"
                            :class="finished ? 'bg-emerald-500' : 'bg-primary'"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                </div>

                <ol class="max-h-[50vh] space-y-1 overflow-y-auto px-6 py-5">
                    <li
                        v-for="(step, index) in steps"
                        :key="step"
                        class="flex items-center gap-3 rounded-lg px-2 py-1.5 transition-colors duration-300"
                        :class="stepState(index) === 'active' ? 'bg-primary/5' : ''"
                    >
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full border transition-all duration-300"
                            :class="{
                                'border-emerald-500 bg-emerald-500 text-white': stepState(index) === 'done',
                                'border-primary text-primary': stepState(index) === 'active',
                                'border-border text-muted-foreground/50': stepState(index) === 'pending',
                            }"
                        >
                            <Check v-if="stepState(index) === 'done'" class="size-3.5 motion-safe:animate-[setup-pop_300ms_ease-out]" />
                            <Loader2 v-else-if="stepState(index) === 'active'" class="size-3.5 motion-safe:animate-spin" />
                            <span v-else class="size-1.5 rounded-full bg-current" />
                        </span>
                        <span
                            class="flex-1 text-sm transition-colors duration-300"
                            :class="{
                                'text-foreground': stepState(index) !== 'pending',
                                'font-medium': stepState(index) === 'active',
                                'text-muted-foreground/60': stepState(index) === 'pending',
                            }"
                        >
                            {{ t(`company.setup.steps.${step}`) }}
                        </span>
                        <span
                            v-if="countFor(step)"
                            class="shrink-0 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            {{ countFor(step) }}
                        </span>
                    </li>
                </ol>
            </div>
        </div>
    </Transition>
</template>

<style>
@keyframes setup-pop {
    0% { transform: scale(0.4); opacity: 0; }
    70% { transform: scale(1.15); opacity: 1; }
    100% { transform: scale(1); }
}
</style>
