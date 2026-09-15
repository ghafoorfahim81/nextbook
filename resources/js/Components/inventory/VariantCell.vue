<script setup>
/**
 * One line's variant picker, shared by every stock-moving form's item table.
 *
 * A plain (single-variant) item shows a dash — there is nothing to choose.
 * An item with real variants gets a dropdown, labelled by attribute values
 * ("16GB / Black"), defaulting to the item's own default variant.
 */
import NextSelect from '@/Components/next/NextSelect.vue'

defineProps({
  modelValue: { type: Object, default: null },
  itemVariants: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  id: { type: String, default: () => 'variant-' + Math.random().toString(36).slice(2) },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <NextSelect
    v-if="(itemVariants?.length || 0) > 1"
    :model-value="modelValue"
    :options="itemVariants"
    label-key="display_name"
    value-key="id"
    :reduce="(v) => v"
    :disabled="disabled"
    :id="id"
    :show-arrow="false"
    :append-to-body="true"
    :error="error"
    @update:modelValue="(value) => $emit('update:modelValue', value)"
  />
  <span v-else class="text-sm text-muted-foreground">—</span>
</template>
