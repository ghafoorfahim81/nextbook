<script setup>
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog'

const { t } = useI18n()

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: null },
  fields: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:open', 'update:modelValue', 'apply', 'clear'])

const local = reactive({})

const normalizedFields = computed(() => {
  return (props.fields || []).map((f) => ({
    key: f.key,
    label: f.label,
    type: f.type || 'text', // text | date | daterange | number | numberrange
    placeholder: f.placeholder || '',
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
  () => [props.open, props.modelValue, props.fields],
  () => {
    if (props.open) hydrateFromModelValue()
  },
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

  // strip null/empty
  return Object.fromEntries(Object.entries(payload).filter(([, v]) => v !== null && v !== ''))
}

function apply() {
  const payload = currentPayload()
  emit('update:modelValue', payload)
  emit('apply', payload)
  emit('update:open', false)
}

function clear() {
  for (const key of Object.keys(local)) local[key] = ''
  emit('update:modelValue', {})
  emit('clear')
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
    <DialogContent
      overlayClass="bg-transparent shadow-none z-[1990]"
      class="max-w-3xl p-0 overflow-hidden border border-border shadow-none z-[2000]"
    >
      <div class="px-6 pt-5 pb-4">
        <DialogHeader>
          <DialogTitle class="text-base font-medium">
            {{ title || t('datatable.filters') }}
          </DialogTitle>
        </DialogHeader>
      </div>

      <div class="px-6 pb-4">
        <div class="grid grid-cols-1 gap-3">
          <div
            v-for="f in normalizedFields"
            :key="f.key"
            class="grid grid-cols-12 items-center gap-4"
          >
            <div class="col-span-4 text-xs text-muted-foreground">
              {{ f.label }}
            </div>

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

              <template v-else>
                <Input
                  v-model="local[f.key]"
                  :type="f.type === 'date' ? 'date' : f.type === 'number' ? 'number' : 'text'"
                  :placeholder="f.placeholder"
                />
              </template>
            </div>
          </div>
        </div>
      </div>

      <div class="px-6 pb-5">
        <DialogFooter class="gap-2">
          <Button variant="outline" type="button" @click="clear">
            {{ t('general.clear') }}
          </Button>
          <Button class="bg-primary text-white" type="button" @click="apply">
            {{ t('general.search') }}
          </Button>
        </DialogFooter>
      </div>
    </DialogContent>
  </Dialog>
</template>

