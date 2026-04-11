<script setup>
import { cn } from '@/lib/utils';
import { useVModel } from '@vueuse/core';
import { nextTick, onMounted, ref } from 'vue';
import { shouldAutoFocusElement } from '@/lib/autofocus';

const props = defineProps({
  defaultValue: { type: [String, Number], required: false },
  modelValue: { type: [String, Number], required: false },
  class: { type: null, required: false },
});

const emits = defineEmits(['update:modelValue']);

const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
});

const inputRef = ref(null);

const focusInput = () => {
  if (shouldAutoFocusElement(inputRef.value)) {
    inputRef.value.focus?.();
  }
};

onMounted(() => {
  nextTick(() => {
    focusInput();
    requestAnimationFrame(focusInput);
    setTimeout(focusInput, 50);
  });
});

defineExpose({
  focus: () => inputRef.value?.focus?.(),
});
</script>

<template>
  <input
    ref="inputRef"
    v-model="modelValue"
    :class="
      cn(
        'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  />
</template>
