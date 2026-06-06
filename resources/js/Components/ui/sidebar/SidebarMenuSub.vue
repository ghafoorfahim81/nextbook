<script setup>
import { cn } from '@/lib/utils';

// Props
const props = defineProps({
  class: { type: String, default: '' },
});
</script>

<template>
  <ul
    data-sidebar="menu-badge"
    :class="cn(
      'mx-3.5 flex min-w-0 flex-col gap-1 border-sidebar-border px-2.5 py-0.5 ltr:translate-x-px ltr:border-l rtl:-translate-x-px rtl:border-r',
      'group-data-[collapsible=icon]:hidden',
      props.class,
    )"
  >
    <slot />
  </ul>
</template>
