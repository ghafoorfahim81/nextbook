<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import BookLoader from '@/Components/next/BookLoader.vue'

const { t } = useI18n()
const visible = ref(false)

// Avoid flicker on fast navigations, and avoid an instant flash-hide once shown.
const SHOW_DELAY_MS = 150
const MIN_VISIBLE_MS = 400

let showTimer = null
let hideTimer = null
let shownAt = 0

function handleStart() {
    clearTimeout(hideTimer)
    clearTimeout(showTimer)
    showTimer = setTimeout(() => {
        visible.value = true
        shownAt = Date.now()
    }, SHOW_DELAY_MS)
}

function handleFinish() {
    clearTimeout(showTimer)
    if (!visible.value) return

    const remaining = MIN_VISIBLE_MS - (Date.now() - shownAt)
    if (remaining > 0) {
        hideTimer = setTimeout(() => { visible.value = false }, remaining)
    } else {
        visible.value = false
    }
}

let offStart
let offFinish

onMounted(() => {
    offStart = router.on('start', handleStart)
    offFinish = router.on('finish', handleFinish)
})

onBeforeUnmount(() => {
    clearTimeout(showTimer)
    clearTimeout(hideTimer)
    offStart?.()
    offFinish?.()
})
</script>

<template>
    <Transition name="page-loader-fade">
        <div
            v-if="visible"
            class="fixed inset-0 z-[100] flex flex-col items-center justify-center gap-4 bg-background/70 backdrop-blur-sm"
            role="status"
            aria-live="polite"
            :aria-label="t('general.loading')"
        >
            <BookLoader :size="140" />
            <span class="text-sm font-medium text-muted-foreground">{{ t('general.loading') }}</span>
        </div>
    </Transition>
</template>

<style scoped>
.page-loader-fade-enter-active,
.page-loader-fade-leave-active {
    transition: opacity 0.15s ease;
}

.page-loader-fade-enter-from,
.page-loader-fade-leave-to {
    opacity: 0;
}
</style>
