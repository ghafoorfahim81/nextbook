<script setup>
import { computed, ref, watch } from 'vue'
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
import NextInput from '@/Components/next/NextInput.vue'

const { t } = useI18n()

const props = defineProps({
  amount: {
    type: [Number, String],
    default: 0,
  },
  billLabel: {
    type: String,
    default: 'Bill',
  },
  bills: {
    type: Array,
    default: () => [],
  },
  loading: Boolean,
  open: Boolean,
  title: {
    type: String,
    default: '',
  },
  allocations: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:open', 'save', 'cancel', 'update:allocations'])

const localRows = ref([])

const availableAmount = computed(() => Number(props.amount || 0))
const allocatedTotal = computed(() => localRows.value.reduce((total, row) => total + (row.selected ? Number(row.amount || 0) : 0), 0))
const remainingToAllocate = computed(() => Math.max(availableAmount.value - allocatedTotal.value, 0))
const isValid = computed(() => {
  if (!localRows.value.some((row) => row.selected)) {
    return false
  }

  return allocatedTotal.value <= availableAmount.value + 0.00001
})

function cloneRows() {
  const existing = new Map((props.allocations || []).map((allocation) => [String(allocation.bill_id), Number(allocation.amount || 0)]))

  localRows.value = (props.bills || []).map((bill) => {
    const selectedAmount = existing.get(String(bill.id))
    const selected = typeof selectedAmount !== 'undefined'

    return {
      ...bill,
      selected,
      amount: selectedAmount ?? Math.min(Number(bill.remaining_amount || 0), availableAmount.value),
    }
  })
}

function close() {
  emit('update:open', false)
}

function toggleRow(row, checked) {
  row.selected = checked

  if (!checked) {
    row.amount = 0
    emit('update:allocations', serializeAllocations())
    return
  }

  if (!row.amount || Number(row.amount) <= 0) {
    row.amount = Math.min(Number(row.remaining_amount || 0), remainingToAllocate.value)
  }

  row.amount = Math.min(Number(row.amount || 0), Number(row.remaining_amount || 0), availableAmount.value)
  emit('update:allocations', serializeAllocations())
}

function autoDistribute() {
  let remaining = availableAmount.value

  localRows.value.forEach((row) => {
    if (!row.selected) {
      return
    }

    const value = Math.min(Number(row.remaining_amount || 0), remaining)
    row.amount = value
    remaining -= value
  })

  emit('update:allocations', serializeAllocations())
}

function serializeAllocations() {
  return localRows.value
    .filter((row) => row.selected && Number(row.amount || 0) > 0)
    .map((row) => ({
      bill_id: row.id,
      amount: Number(row.amount || 0),
    }))
}

function save() {
  if (!isValid.value) {
    return
  }

  emit('save', serializeAllocations())
  close()
}

watch(
  () => [props.open, props.bills],
  () => {
    if (!props.open) {
      return
    }

    cloneRows()
  },
  { immediate: true, deep: true },
)

watch(
  localRows,
  () => {
    emit('update:allocations', serializeAllocations())
  },
  { deep: true },
)
</script>

<template>
  <Dialog :open="open" @update:open="close">
    <DialogContent class="max-w-5xl">
      <DialogHeader>
        <DialogTitle>{{ title || t('general.allocate_bills') }}</DialogTitle>
        <DialogDescription>
          <!-- {{ t('general.enter') }} {{ billLabel.toLowerCase() }} {{ t('general.allocation') }} -->
        </DialogDescription>
      </DialogHeader>

      <div v-if="loading" class="py-10 text-center text-muted-foreground">
        {{ t('general.loading') }}...
      </div>

      <div v-else class="space-y-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <div class="rounded-lg border bg-muted/30 p-3">
            <div class="text-xs font-semibold text-violet-500">{{ t('general.amount') }}</div>
            <div class="mt-1 text-lg font-bold tabular-nums">{{ availableAmount.toLocaleString() }}</div>
          </div>
          <div class="rounded-lg border bg-muted/30 p-3">
            <div class="text-xs font-semibold text-violet-500">{{ t('general.allocated_amount') }}</div>
            <div class="mt-1 text-lg font-bold tabular-nums">{{ allocatedTotal.toLocaleString() }}</div>
          </div>
          <div class="rounded-lg border bg-muted/30 p-3">
            <div class="text-xs font-semibold text-violet-500">{{ t('general.remaining_balance') }}</div>
            <div class="mt-1 text-lg font-bold tabular-nums">{{ remainingToAllocate.toLocaleString() }}</div>
          </div>
        </div>

        <div class="flex items-center justify-end">
          <Button type="button" variant="outline" @click="autoDistribute">
            {{ t('general.auto_distribute') || 'Auto distribute' }}
          </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
          <table class="min-w-full text-sm">
            <thead class="bg-muted/40">
              <tr>
                <th class="px-3 py-2 text-left">{{ t('general.select') }}</th>
                <th class="px-3 py-2 text-left">{{ billLabel }}</th>
                <th class="px-3 py-2 text-left">{{ t('general.date') }}</th>
                <th class="px-3 py-2 text-right">{{ t('general.remaining_amount') }}</th>
                <th class="px-3 py-2 text-right">{{ t('general.allocate_amount') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in localRows" :key="row.id" class="border-t">
                <td class="px-3 py-2">
                  <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-border"
                    :checked="row.selected"
                    @change="toggleRow(row, $event.target.checked)"
                  >
                </td>
                <td class="px-3 py-2 font-medium">
                  #{{ row.number }}
                  <div class="text-xs text-muted-foreground">{{ row.payment_status }}</div>
                </td>
                <td class="px-3 py-2">{{ row.date || '-' }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ Number(row.remaining_amount || 0).toLocaleString() }}</td>
                <td class="px-3 py-2 text-right">
                  <NextInput
                    v-model="row.amount"
                    :disabled="!row.selected"
                    type="number"
                    step="0.01"
                    min="0"
                    :placeholder="t('general.enter', { text: t('general.amount') })"
                    @update:modelValue="() => emit('update:allocations', serializeAllocations())"
                  />
                </td>
              </tr>
              <tr v-if="!localRows.length">
                <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">
                  {{ t('general.no_record_available') || 'No record available' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <DialogFooter class="gap-2">
        <Button type="button" variant="outline" @click="close">
          {{ t('general.cancel') }}
        </Button>
        <Button type="button" :disabled="!isValid" @click="save">
          {{ t('general.save') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
