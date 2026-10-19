<script setup>
/**
 * Gmail-style "moved to trash" toast.
 *
 * Layout mirrors Gmail's: a plain sentence, an Undo action, and — where Gmail
 * puts a bare X — a ring that drains as the toast's time runs out, so the
 * operator can see how long Undo stays available. The X moves to the start.
 *
 * The ring is driven by a stroke-dashoffset transition rather than a timer
 * tick: one style write, no per-frame work, and it stays smooth under load.
 */
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { X } from 'lucide-vue-next'

const props = defineProps({
    message: { type: String, required: true },
    undoLabel: { type: String, required: true },
    closeLabel: { type: String, default: 'Close' },
    // Kept in step with the toast's own duration by the caller.
    duration: { type: Number, default: 5000 },
    onUndo: { type: Function, default: null },
    onClose: { type: Function, default: null },
    onExpire: { type: Function, default: null },
})

// r=9 in a 20x20 box leaves room for the 2px stroke without clipping.
const RADIUS = 9
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

// Starts full, then drains to empty over `duration`.
const dashOffset = ref(0)
const undone = ref(false)
const expired = ref(false)
let raf = null
let timer = null

onMounted(() => {
    // Two frames: the first paints the full ring, the second starts the
    // transition. Setting both in one frame would skip the animation.
    raf = requestAnimationFrame(() => {
        raf = requestAnimationFrame(() => { dashOffset.value = CIRCUMFERENCE })
    })

    // The component owns the countdown rather than leaning on the toast's own
    // duration, because sonner pauses that on hover while a CSS transition
    // keeps running. Owning it keeps the ring, the Undo button and the
    // dismissal agreeing with each other whether or not the pointer is over
    // the toast — once the ring is empty the window really is closed.
    timer = setTimeout(() => {
        expired.value = true
        props.onExpire?.()
    }, props.duration)
})

onBeforeUnmount(() => {
    if (raf) cancelAnimationFrame(raf)
    clearTimeout(timer)
})

// Undo is one-shot — a second click after the row is already restored would
// fire a second restore request against a record that is no longer trashed.
const handleUndo = () => {
    if (undone.value || expired.value) return
    undone.value = true
    props.onUndo?.()
}
</script>

<template>
    <!-- The Toaster already supplies the card (border, background, shadow); this
         only lays out the row inside it, so the two do not double-box. -->
    <div
        class="flex w-full items-center gap-3"
        role="status"
        aria-live="polite"
    >
        <!-- Dismiss sits at the start; the ring has taken its usual place. -->
        <button
            type="button"
            class="shrink-0 rounded-full p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
            :aria-label="closeLabel"
            @click="onClose?.()"
        >
            <X class="h-4 w-4" />
        </button>

        <span class="min-w-0 flex-1 truncate text-sm text-foreground">{{ message }}</span>

        <button
            v-if="!expired"
            type="button"
            class="shrink-0 rounded px-1 text-sm font-semibold text-violet-600 transition hover:underline disabled:opacity-50 dark:text-violet-400"
            :disabled="undone"
            @click="handleUndo"
        >
            {{ undoLabel }}
        </button>

        <!-- Countdown ring: how much time is left to hit Undo. -->
        <svg
            class="h-5 w-5 shrink-0 -rotate-90"
            viewBox="0 0 20 20"
            aria-hidden="true"
        >
            <circle
                cx="10" cy="10" :r="RADIUS"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                class="text-violet-500/20"
            />
            <circle
                cx="10" cy="10" :r="RADIUS"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                class="text-violet-500"
                :stroke-dasharray="CIRCUMFERENCE"
                :stroke-dashoffset="dashOffset"
                :style="{ transition: `stroke-dashoffset ${duration}ms linear` }"
            />
        </svg>
    </div>
</template>
