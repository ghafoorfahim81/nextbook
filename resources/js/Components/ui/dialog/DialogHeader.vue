<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
  class: { type: null, required: false },
});
</script>

<template>
  <div
    data-dialog-drag
    :class="cn('flex flex-col gap-y-1.5 text-center sm:text-left rtl:sm:text-right cursor-grab active:cursor-grabbing', props.class)"
  >
    <slot />
  </div>
</template>
