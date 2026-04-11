<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'

const { t } = useI18n()

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  unitMeasureId: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['update:open', 'edit'])

const unitMeasure = ref(null)
const loading = ref(false)

const quantityLabel = computed(() => unitMeasure.value?.quantity?.quantity || unitMeasure.value?.quantity?.name || '-')

const formatValue = (value) => {
  if (value === null || value === undefined || value === '') return '-'
  return Number(value).toLocaleString(undefined, {
    maximumFractionDigits: 2,
  })
}

const loadUnitMeasure = async (id) => {
  if (!id) return

  loading.value = true
  try {
    const { data } = await axios.get(route('unit-measures.show', id))
    unitMeasure.value = data?.data ?? data ?? null
  } finally {
    loading.value = false
  }
}

const handleOpenChange = (nextOpen) => {
  emit('update:open', nextOpen)

  if (!nextOpen) {
    unitMeasure.value = null
    loading.value = false
  }
}

watch(
  () => [props.open, props.unitMeasureId],
  async ([isOpen, id]) => {
    if (isOpen && id) {
      await loadUnitMeasure(id)
      return
    }

    if (!isOpen) {
      unitMeasure.value = null
      loading.value = false
    }
  }
)
</script>

<template>
  <Dialog :open="open" @update:open="handleOpenChange">
    <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle class="text-lg font-semibold text-foreground">
          {{ t('admin.unit_measure.unit_measure') }}
          <span v-if="unitMeasure"> - {{ unitMeasure.name }}</span>
        </DialogTitle>
        <DialogDescription>
          {{ t('general.details') }}
        </DialogDescription>
      </DialogHeader>

      <div v-if="loading" class="py-10 text-center text-sm text-muted-foreground">
        {{ t('general.loading') }}...
      </div>

      <div v-else-if="unitMeasure" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-xs text-muted-foreground">{{ t('general.name') }}</div>
            <div class="mt-1 text-sm font-medium text-foreground">{{ unitMeasure.name || '-' }}</div>
          </div>

          <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-xs text-muted-foreground">{{ t('admin.unit_measure.quantity') }}</div>
            <div class="mt-1 text-sm font-medium text-foreground">{{ quantityLabel }}</div>
          </div>

          <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-xs text-muted-foreground">{{ t('admin.unit_measure.base_unit') }}</div>
            <div class="mt-1 text-sm font-medium text-foreground">
              {{ unitMeasure.quantity?.unit || '-' }}
            </div>
          </div>

          <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-xs text-muted-foreground">{{ t('admin.unit_measure.unit') }}</div>
            <div class="mt-1 text-sm font-medium text-foreground">{{ unitMeasure.unit || '-' }}</div>
          </div>

          <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-xs text-muted-foreground">{{ t('admin.shared.symbol') }}</div>
            <div class="mt-1 text-sm font-medium text-foreground">{{ unitMeasure.symbol || '-' }}</div>
          </div>

          <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-xs text-muted-foreground">{{ t('general.status') }}</div>
            <div class="mt-1 text-sm font-medium text-foreground">
              {{ unitMeasure.is_active ? t('general.active') : t('general.inactive') }}
            </div>
          </div>

          <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-xs text-muted-foreground">{{ t('admin.unit_measure.value') }}</div>
            <div class="mt-1 text-sm font-medium text-foreground">{{ formatValue(unitMeasure.value) }}</div>
          </div>

          <div class="rounded-lg border border-border bg-card p-4 md:col-span-2 xl:col-span-2">
            <div class="text-xs text-muted-foreground">{{ t('general.remark') }}</div>
            <div class="mt-1 text-sm font-medium text-foreground">
              {{ unitMeasure.description || '-' }}
            </div>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-xs text-muted-foreground">{{ t('general.created_by') }}</div>
            <div class="mt-1 text-sm font-medium text-foreground">{{ unitMeasure.created_by?.name || '-' }}</div>
          </div>

          <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-xs text-muted-foreground">{{ t('general.updated_by') }}</div>
            <div class="mt-1 text-sm font-medium text-foreground">{{ unitMeasure.updated_by?.name || '-' }}</div>
          </div>
        </div>
      </div>

      <div v-else class="py-10 text-center text-sm text-muted-foreground">
        {{ t('general.no_data_found') }}
      </div>

      <DialogFooter>
        <Button variant="outline" @click="handleOpenChange(false)">
          {{ t('general.close') }}
        </Button>
        <Button
          v-if="unitMeasure"
          class="bg-primary text-white"
          @click="emit('edit', unitMeasure)"
        >
          {{ t('general.edit', { name: t('admin.unit_measure.unit_measure') }) }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
