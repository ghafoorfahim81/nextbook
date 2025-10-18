<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
  class: String,
});
</script>

<template>
  <span
    :class="
      cn('inline-flex items-center gap-1', props.class)
    "
  >
    <slot />
  </span>
</template>
