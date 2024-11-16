<script setup>
import Tooltip from '@/Components/ui/tooltip/Tooltip.vue';
import TooltipContent from '@/Components/ui/tooltip/TooltipContent.vue';
import TooltipTrigger from '@/Components/ui/tooltip/TooltipTrigger.vue';
import { computed } from 'vue';
import SidebarMenuButtonChild from './SidebarMenuButtonChild.vue';
import { useSidebar } from './utils';

// Options
defineOptions({
  inheritAttrs: false,
});

// Props
const props = defineProps({
  as: { type: String, default: 'button' },
  variant: { type: String, default: 'default' },
  size: { type: String, default: 'default' },
  tooltip: { type: [String, Object], default: undefined },
});

// Sidebar state
const { isMobile, state } = useSidebar();

// Delegated props (excluding tooltip)
const delegatedProps = computed(() => {
  const { tooltip, ...delegated } = props;
  return delegated;
});
</script>

<template>
  <SidebarMenuButtonChild v-if="!tooltip" v-bind="{ ...delegatedProps, ...$attrs }">
    <slot />
  </SidebarMenuButtonChild>

  <Tooltip v-else>
    <TooltipTrigger as-child>
      <SidebarMenuButtonChild v-bind="{ ...delegatedProps, ...$attrs }">
        <slot />
      </SidebarMenuButtonChild>
    </TooltipTrigger>
    <TooltipContent
      side="right"
      align="center"
      :hidden="state !== 'collapsed' || isMobile"
    >
      <template v-if="typeof tooltip === 'string'">
        {{ tooltip }}
      </template>
      <component :is="tooltip" v-else />
    </TooltipContent>
  </Tooltip>
</template>
