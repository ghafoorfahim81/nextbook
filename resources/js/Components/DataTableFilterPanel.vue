<script setup>
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import NextSelect from '@/Components/next/NextSelect.vue'

const { t } = useI18n()

const props = defineProps({
  title: { type: String, default: null },
  fields: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'apply', 'clear'])

const local = reactive({})

const normalizedFields = computed(() => {
  return (props.fields || []).map((f) => ({
    key: f.key,
    label: f.label,
    type: f.type || 'text', // text | date | daterange | number | numberrange | select
    placeholder: f.placeholder || '',
    options: Array.isArray(f.options) ? f.options : null,
    labelKey: f.labelKey || 'name',
    valueKey: f.valueKey || 'id',
    reduce: typeof f.reduce === 'function' ? f.reduce : null,
    clearable: f.clearable ?? true,
  }))
})

function hydrateFromModelValue() {
  const source = props.modelValue || {}
  for (const f of normalizedFields.value) {
    if (f.type === 'daterange') {
      local[`${f.key}_from`] = source?.[`${f.key}_from`] ?? ''
      local[`${f.key}_to`] = source?.[`${f.key}_to`] ?? ''
      continue
    }
    if (f.type === 'numberrange') {
      local[`${f.key}_min`] = source?.[`${f.key}_min`] ?? ''
      local[`${f.key}_max`] = source?.[`${f.key}_max`] ?? ''
      continue
    }
    local[f.key] = source?.[f.key] ?? ''
  }
}

watch(
  () => [props.modelValue, props.fields],
  () => hydrateFromModelValue(),
  { deep: true, immediate: true },
)

function currentPayload() {
  const payload = {}
  for (const f of normalizedFields.value) {
    if (f.type === 'daterange') {
      payload[`${f.key}_from`] = local[`${f.key}_from`] || null
      payload[`${f.key}_to`] = local[`${f.key}_to`] || null
      continue
    }
    if (f.type === 'numberrange') {
      payload[`${f.key}_min`] = local[`${f.key}_min`] || null
      payload[`${f.key}_max`] = local[`${f.key}_max`] || null
      continue
    }
    payload[f.key] = local[f.key] || null
  }

  return Object.fromEntries(Object.entries(payload).filter(([, v]) => v !== null && v !== ''))
}

function apply() {
  const payload = currentPayload()
  emit('update:modelValue', payload)
  emit('apply', payload)
}

function clear() {
  for (const key of Object.keys(local)) local[key] = ''
  emit('update:modelValue', {})
  emit('clear')
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-2">
      <div class="text-sm font-medium">
        {{ title || t('datatable.filters') }}
      </div>
    </div>

    <div class="grid grid-cols-1 gap-3">
      <div
        v-for="f in normalizedFields"
        :key="f.key"
        class="grid grid-cols-12 items-center gap-4"
      >
        <Label class="col-span-4 text-xs text-muted-foreground">
          {{ f.label }}
        </Label>

        <div class="col-span-8">
          <template v-if="f.type === 'daterange'">
            <div class="grid grid-cols-2 gap-2">
              <Input v-model="local[`${f.key}_from`]" type="date" />
              <Input v-model="local[`${f.key}_to`]" type="date" />
            </div>
          </template>

          <template v-else-if="f.type === 'numberrange'">
            <div class="grid grid-cols-2 gap-2">
              <Input v-model="local[`${f.key}_min`]" type="number" :placeholder="t('general.min')" />
              <Input v-model="local[`${f.key}_max`]" type="number" :placeholder="t('general.max')" />
            </div>
          </template>

          <template v-else-if="f.type === 'select' && f.options?.length">
            <NextSelect
              :modelValue="local[f.key]"
              @update:modelValue="(v) => (local[f.key] = v)"
              :options="f.options"
              :labelKey="f.labelKey"
              :valueKey="f.valueKey"
              :reduce="f.reduce"
              :clearable="f.clearable"
              :placeholder="f.placeholder || t('general.select')"
              :showArrow="true"
              :hasAddButton="false"
              class="w-full"
            />
          </template>

          <template v-else>
            <Input
              v-model="local[f.key]"
              :type="f.type === 'date' ? 'date' : f.type === 'number' ? 'number' : 'text'"
              :placeholder="f.placeholder"
              class="h-9"
            />
          </template>
        </div>
      </div>
    </div>

    <div class="flex justify-end gap-2 pt-1">
      <Button variant="outline" type="button" @click="clear">
        {{ t('general.clear') }}
      </Button>
      <Button class="bg-primary text-white" type="button" @click="apply">
        {{ t('general.search') }}
      </Button>
    </div>
  </div>
</template>

