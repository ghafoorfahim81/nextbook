<script setup>
import { cn } from '@/lib/utils';
import { X } from 'lucide-vue-next';
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  useForwardPropsEmits,
} from 'radix-vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { unrefElement } from '@vueuse/core';

const props = defineProps({
  forceMount: { type: Boolean, required: false },
  trapFocus: { type: Boolean, required: false },
  disableOutsidePointerEvents: { type: Boolean, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: null, required: false },
  overlayClass: { type: null, required: false },
  draggable: { type: Boolean, default: true },
});
const emits = defineEmits([
  'escapeKeyDown',
  'pointerDownOutside',
  'focusOutside',
  'interactOutside',
  'openAutoFocus',
  'closeAutoFocus',
]);

const delegatedProps = computed(() => {
  const { class: _, overlayClass: __, draggable: ___, ...delegated } = props;

  return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);

const offset = reactive({ x: 0, y: 0 })
const dragging = ref(false)
const dragStart = reactive({ x: 0, y: 0, offsetX: 0, offsetY: 0 })

/*
 * Centring with translate(-50%, -50%) puts the dialog on a fraction of a
 * pixel whenever its size (or the window's) is odd, and everything inside
 * inherits that fraction. Field outlines are the casualty: their top border
 * is cut around the floated label, and at a fractional position the browser
 * blurs the border's edge into the cut, so a hairline ran through every
 * label — only in dialogs, because pages lay out on whole pixels. `snap` is
 * the nudge that lands the box on a whole device pixel again.
 */
const snap = reactive({ x: 0, y: 0 })
const contentRef = ref(null)

const contentStyle = computed(() => ({
  transform: `translate(calc(-50% + ${offset.x + snap.x}px), calc(-50% + ${offset.y + snap.y}px))`,
}))

const contentElement = () => {
  const el = unrefElement(contentRef)
  return el && el.nodeType === 1 ? el : null
}

const snapToPixels = () => {
  const el = contentElement()
  if (!el) return

  const rect = el.getBoundingClientRect()
  const ratio = window.devicePixelRatio || 1
  const dx = Math.round(rect.left * ratio) / ratio - rect.left
  const dy = Math.round(rect.top * ratio) / ratio - rect.top

  // Already aligned (within float noise): leave it, or the observer below
  // would keep re-rendering for nothing.
  if (Math.abs(dx) > 0.01) snap.x += dx
  if (Math.abs(dy) > 0.01) snap.y += dy
}

const scheduleSnap = () => requestAnimationFrame(snapToPixels)

let resizeObserver = null
let observedElement = null

const detachSnap = () => {
  observedElement?.removeEventListener('animationend', scheduleSnap)
  window.removeEventListener('resize', scheduleSnap)
  resizeObserver?.disconnect()
  resizeObserver = null
  observedElement = null
}

// This wrapper is mounted while the dialog is still closed; the content
// element only exists once it opens. So follow the element itself rather
// than this component's lifecycle.
watch(
  () => contentElement(),
  (el) => {
    detachSnap()
    if (!el) {
      snap.x = 0
      snap.y = 0
      return
    }

    observedElement = el
    // The open animation scales the box, so measure once it has settled, and
    // again whenever its height or the window's size moves the centre.
    el.addEventListener('animationend', scheduleSnap)
    window.addEventListener('resize', scheduleSnap)
    if (typeof ResizeObserver !== 'undefined') {
      resizeObserver = new ResizeObserver(scheduleSnap)
      resizeObserver.observe(el)
    }
    scheduleSnap()
  },
  { flush: 'post' },
)

onBeforeUnmount(detachSnap)

const isDragHandle = (target) => {
  if (!(target instanceof Element)) {
    return false
  }

  if (target.closest('button, a, input, textarea, select, [data-no-drag]')) {
    return false
  }

  return Boolean(target.closest('[data-dialog-drag]'))
}

const onPointerMove = (event) => {
  if (!dragging.value) {
    return
  }

  offset.x = dragStart.offsetX + event.clientX - dragStart.x
  offset.y = dragStart.offsetY + event.clientY - dragStart.y
}

const stopDrag = () => {
  if (!dragging.value) {
    return
  }

  dragging.value = false
  window.removeEventListener('pointermove', onPointerMove)
  window.removeEventListener('pointerup', stopDrag)
}

const onPointerDown = (event) => {
  if (!props.draggable || event.button !== 0 || !isDragHandle(event.target)) {
    return
  }

  dragging.value = true
  dragStart.x = event.clientX
  dragStart.y = event.clientY
  dragStart.offsetX = offset.x
  dragStart.offsetY = offset.y
  window.addEventListener('pointermove', onPointerMove)
  window.addEventListener('pointerup', stopDrag)
}

onBeforeUnmount(stopDrag)
</script>

<template>
  <DialogPortal>
    <DialogOverlay
      :class="cn(
        'fixed inset-0 z-50 bg-black/10 shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 backdrop-blur-[1px]',
        props.overlayClass,
      )"
    />
    <DialogContent
      ref="contentRef"
      v-bind="forwarded"
      :style="contentStyle"
      :class="
        cn(
          'fixed left-1/2 top-1/2 z-50 grid w-full max-w-lg gap-4 border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 sm:rounded-lg',
          dragging && 'select-none',
          props.class,
        )
      "
      @pointerdown="onPointerDown"
    >
      <slot />

      <DialogClose
        class="absolute right-4 rtl:right-auto rtl:left-4 top-4 rounded-sm opacity-100 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground"
      >
        <X class="w-4 h-4" />
        <span class="sr-only">Close</span>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>
