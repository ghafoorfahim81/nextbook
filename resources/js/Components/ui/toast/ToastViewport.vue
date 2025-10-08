<script setup>
import { reactiveOmit } from "@vueuse/core";
import { ToastViewport } from "reka-ui";
import { cn } from "@/lib/utils";
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  hotkey: { type: Array, required: false },
  label: { type: [String, Function], required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: null, required: false },
});

const delegatedProps = reactiveOmit(props, "class");

// Respect RTL languages so the toast sits in the visual corner
const { locale } = useI18n()
const isRTL = computed(() => ['fa','ps','pa','ar','ur','he'].includes(locale.value))
</script>

<template>
  <ToastViewport
    v-bind="delegatedProps"
    :class="
      cn(
        'pointer-events-none fixed top-0 z-[2147483647] flex max-h-screen w-full flex-col-reverse m-6 sm:top-auto sm:bottom-0 sm:w-auto sm:flex-col ',
        isRTL ? 'sm:left-0' : 'sm:right-0',
        props.class,
      )
    "
  />
</template>
