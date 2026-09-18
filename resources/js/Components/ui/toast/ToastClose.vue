<script setup>
import { reactiveOmit } from "@vueuse/core";
import { X } from "lucide-vue-next";
import { ToastClose } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: null, required: false },
});

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <ToastClose
    v-bind="delegatedProps"
    :class="
      cn(
        // `text-current` so the X follows whatever foreground the variant set;
        // the old `text-foreground/60` was a dark token that disappeared on
        // every coloured surface.
        'pointer-events-auto absolute end-1 top-1 rounded-md p-1 text-current opacity-60 transition-opacity hover:opacity-100 focus:opacity-100 focus:outline-none focus:ring-1 focus:ring-current',
        props.class,
      )
    "
  >
    <X class="h-4 w-4" />
  </ToastClose>
</template>
