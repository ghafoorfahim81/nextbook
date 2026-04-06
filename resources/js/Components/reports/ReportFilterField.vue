<script setup>
defineProps({
  label: { type: String, required: true },
})
</script>

<template>
  <div class="space-y-2">
    <div class="text-sm font-medium text-foreground">{{ label }}</div>
    <slot />
  </div>
</template>
