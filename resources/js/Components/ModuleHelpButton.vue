<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Info, BookOpen } from 'lucide-vue-next'

import { cn } from '@/lib/utils'
import { Button } from '@/Components/ui/button'
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog'

const props = defineProps({
  module: { type: String, required: true },
  positionClass: { type: String, required: false, default: 'absolute -top-3 ltr:right-3 rtl:left-3' },
  class: { type: String, required: false, default: '' },
  toolbar: { type: Boolean, required: false, default: false },
  /** Render no trigger — the dialog is opened via `v-model:open` (first-time onboarding). */
  triggerless: { type: Boolean, required: false, default: false },
  /** Optional external open state; omit for the self-contained trigger button. */
  open: { type: Boolean, required: false, default: undefined },
})

const emit = defineEmits(['update:open'])

const { t, tm } = useI18n()

const internalOpen = ref(false)
const dialogOpen = computed({
  get: () => (props.open !== undefined ? props.open : internalOpen.value),
  set: (v) => {
    internalOpen.value = v
    emit('update:open', v)
  },
})

const title = computed(() => {
  const key = `help.modules.${props.module}.title`
  const value = t(key)
  return value === key ? t('general.info') : value
})

const description = computed(() => {
  const key = `help.modules.${props.module}.description`
  const value = t(key)
  return value === key ? '' : value
})

const items = computed(() => {
  const v = tm(`help.modules.${props.module}.items`)
  return Array.isArray(v) ? v.filter(Boolean) : []
})

/** New sectioned shape: [{ heading, items: [] }]. Falls back to the flat list. */
const sections = computed(() => {
  const v = tm(`help.modules.${props.module}.sections`)
  if (!Array.isArray(v)) return []
  return v
    .map((s) => ({
      heading: s?.heading ?? '',
      items: Array.isArray(s?.items) ? s.items.filter(Boolean) : [],
    }))
    .filter((s) => s.items.length)
})

const hasContent = computed(() => sections.value.length || items.value.length)
</script>

<template>
  <div
    :class="cn(
      props.toolbar ? 'relative inline-flex items-center z-50000' : props.positionClass,
      !props.toolbar && 'z-50000',
      props.class,
    )"
  >
    <Dialog v-model:open="dialogOpen">
      <DialogTrigger v-if="!props.triggerless" as-child>
        <Button
          v-if="props.toolbar"
          type="button"
          variant="outline"
          class="h-8 gap-1.5 bg-background border-primary/60 hover:bg-primary/40"
          size="sm"
        >
          <Info class="size-4 shrink-0 text-primary" aria-hidden="true" />
          <span>{{ t('general.info') }}</span>
        </Button>
        <div
          v-else
          class="bg-card px-2 py-1 rounded-md shadow-sm border mt-3 text-primary"
        >
          <Info class="w-4 h-4 text-primary hover:cursor-pointer" />
        </div>
      </DialogTrigger>

      <DialogContent class="max-w-2xl max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ title }}</DialogTitle>
          <DialogDescription v-if="description">{{ description }}</DialogDescription>
        </DialogHeader>

        <div v-if="sections.length" class="space-y-4">
          <section v-for="(section, sIdx) in sections" :key="sIdx">
            <h4 class="mb-1.5 text-sm font-semibold text-foreground">{{ section.heading }}</h4>
            <ul class="list-disc pl-5 rtl:pl-0 rtl:pr-5 space-y-1.5 text-sm leading-relaxed text-muted-foreground">
              <li v-for="(item, idx) in section.items" :key="idx">{{ item }}</li>
            </ul>
          </section>
        </div>
        <div v-else-if="items.length" class="space-y-3">
          <ul class="list-disc pl-5 rtl:pl-0 rtl:pr-5 space-y-2 text-sm leading-relaxed">
            <li v-for="(item, idx) in items" :key="idx">{{ item }}</li>
          </ul>
        </div>
        <div v-else class="text-sm text-muted-foreground">{{ t('help.no_tips_available') }}</div>

        <DialogFooter v-if="hasContent">
          <Button as-child variant="outline" class="gap-2">
            <Link :href="route('user-manual')">
              <BookOpen class="size-4" />
              {{ t('help.open_full_guide') }}
            </Link>
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
