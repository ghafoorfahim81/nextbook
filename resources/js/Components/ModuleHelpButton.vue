<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Info } from 'lucide-vue-next'

import { cn } from '@/lib/utils'
import { Button } from '@/Components/ui/button'
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog'

const props = defineProps({
  module: { type: String, required: true },
  /**
   * Place this component inside a `relative` container.
   * Default matches the common "floating label" style used across forms.
   */
  positionClass: { type: String, required: false, default: 'absolute -top-3 ltr:right-3 rtl:left-3' },
  /**
   * Optional extra wrapper classes (e.g. z-index tweaks).
   */
  class: { type: String, required: false, default: '' },
  /**
   * Inline trigger aligned with small outline buttons (e.g. next to Back on form pages).
   */
  toolbar: { type: Boolean, required: false, default: false },
})

const { t, tm } = useI18n()

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
  const key = `help.modules.${props.module}.items`
  const v = tm(key)
  if (Array.isArray(v)) return v.filter(Boolean)
  // vue-i18n returns strings for missing keys; keep the UI robust.
  return []
})
</script>

<template>
  <div
    :class="cn(
      props.toolbar ? 'relative inline-flex items-center z-50000' : props.positionClass,
      !props.toolbar && 'z-50000',
      props.class,
    )"
  >
    <Dialog>
      <DialogTrigger as-child>
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
      <DialogContent class="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{{ title }}</DialogTitle>
          <DialogDescription v-if="description">{{ description }}</DialogDescription>
        </DialogHeader>

        <div v-if="items.length" class="space-y-3">
          <ul class="list-disc pl-5 rtl:pl-0 rtl:pr-5 space-y-2 text-sm leading-relaxed">
            <li v-for="(item, idx) in items" :key="idx">
              {{ item }}
            </li>
          </ul>
        </div>

        <div v-else class="text-sm text-muted-foreground">
          {{ t('help.no_tips_available') }}
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>

