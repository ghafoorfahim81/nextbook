<script setup>
import { h } from 'vue';
import Button from '@/Components/ui/button/Button.vue';
import { cn } from '@/lib/utils';
import { PanelLeft } from 'lucide-vue-next';
import { useSidebar } from './utils';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/Components/ui/tooltip';
import {
  Kbd,
  KbdGroup,
} from '@/Components/ui/kbd';

// Props
const props = defineProps({
  class: String,
});

// Sidebar utility
const { toggleSidebar } = useSidebar();
</script>

<template>
  <TooltipProvider>
    <Tooltip>
      <TooltipTrigger as-child>
        <Button
          data-sidebar="trigger"
          variant="ghost"
          size="icon"
          :class="cn('h-7 w-7', props.class)"
          @click="toggleSidebar"
        >
          <PanelLeft />
        </Button>
      </TooltipTrigger>
      <TooltipContent>
        <p class="flex items-center gap-2">

          <KbdGroup>
            <Kbd>Ctrl</Kbd>
            <span>+</span>
            <Kbd>B</Kbd>
          </KbdGroup>
        </p>
      </TooltipContent>
    </Tooltip>
  </TooltipProvider>
</template>
