<script setup>
import Separator from '@/Components/ui/separator/Separator.vue';

import { cn } from '@/lib/utils';

// Props
const props = defineProps({
  class: String,
});
</script>

<template>
  <Separator
    data-sidebar="separator"
    :class="cn('mx-2 w-auto bg-sidebar-border', props.class)"
  >
    <slot />
  </Separator>
</template>
