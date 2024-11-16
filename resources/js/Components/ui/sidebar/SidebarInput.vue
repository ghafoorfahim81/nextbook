<script setup>
import Input from '@/Components/ui/input/Input.vue';
import { cn } from '@/lib/utils';

// Props
const props = defineProps({
  class: { type: String, default: '' },
});
</script>

<template>
  <Input
    data-sidebar="input"
    :class="cn(
      'h-8 w-full bg-background shadow-none focus-visible:ring-2 focus-visible:ring-sidebar-ring',
      props.class,
    )"
  >
    <slot />
  </Input>
</template>
