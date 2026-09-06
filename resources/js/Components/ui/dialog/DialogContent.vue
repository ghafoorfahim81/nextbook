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
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

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

const contentStyle = computed(() => ({
  transform: `translate(calc(-50% + ${offset.x}px), calc(-50% + ${offset.y}px))`,
}))

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
