<script setup>
import { cn } from '@/lib/utils';
import { Primitive } from 'radix-vue';

// Props
const props = defineProps({
  class: { type: String, default: '' },
  as: { type: String, default: 'div' }, // Assuming default 'as' value
  asChild: { type: Boolean, default: false }, // Assuming default for asChild
});
</script>

<template>
  <Primitive
    data-sidebar="group-action"
    :class="cn(
      'absolute right-3 top-3.5 flex aspect-square w-5 items-center justify-center rounded-md p-0 text-sidebar-foreground outline-none ring-sidebar-ring transition-transform hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 [&>svg]:size-4 [&>svg]:shrink-0',
      'after:absolute after:-inset-2 after:md:hidden',
      'group-data-[collapsible=icon]:hidden',
      props.class
    )"
  >
    <slot />
  </Primitive>
</template>
