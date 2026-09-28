<script setup>
/**
 * Both sides of a party who trades in both directions.
 *
 * Their customer and supplier accounts are separate ledgers and always will
 * be — one balance cannot be both a receivable and a payable. What was missing
 * was anywhere to see the two together, so "how much does Ahmad actually owe
 * us" had to be worked out by hand from two statements.
 *
 * Renders nothing when this party has no paired account, which is the
 * ordinary case.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  position: { type: Object, default: null },
  counterpart: { type: Object, default: null },
  /** This ledger's own role, so the net can be described from its side. */
  role: { type: String, required: true },
  ledgerId: { type: String, default: '' },
})

const { t } = useI18n()

const money = (value) => Number(value || 0).toLocaleString(undefined, {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const hasOverlap = computed(() => Number(props.position?.offsettable || 0) > 0)

const netLabel = computed(() => (props.position?.net_side === 'receivable'
  ? t('contra.they_owe_you')
  : t('contra.you_owe_them')))

const counterpartRoute = computed(() => {
  if (!props.counterpart?.id) return null

  const type = props.counterpart.type?.value ?? props.counterpart.type

  return type === 'supplier'
    ? route('suppliers.show', props.counterpart.id)
    : route('customers.show', props.counterpart.id)
})

function openSetOff() {
  router.visit(route('contra-settlements.create'))
}
</script>

<template>
  <div v-if="position" class="rounded-xl border p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <div class="text-sm font-semibold text-violet-500">{{ t('contra.combined_position') }}</div>
        <a
          v-if="counterpartRoute"
          :href="counterpartRoute"
          class="text-xs text-muted-foreground underline"
        >
          {{ t('contra.paired_with', { name: counterpart?.name }) }}
        </a>
      </div>

      <Button v-if="hasOverlap" size="sm" variant="outline" @click="openSetOff">
        {{ t('contra.create_set_off') }}
      </Button>
    </div>

    <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
      <div>
        <div class="text-xs text-muted-foreground">{{ t('contra.they_owe_you') }}</div>
        <div class="text-lg font-bold">{{ money(position.receivable) }}</div>
      </div>
      <div>
        <div class="text-xs text-muted-foreground">{{ t('contra.you_owe_them') }}</div>
        <div class="text-lg font-bold">{{ money(position.payable) }}</div>
      </div>
      <div>
        <div class="text-xs text-muted-foreground">{{ t('contra.can_be_offset') }}</div>
        <div class="text-lg font-bold">{{ money(position.offsettable) }}</div>
      </div>
      <div>
        <div class="text-xs text-muted-foreground">{{ t('contra.net_after_offset') }}</div>
        <div class="text-lg font-bold">{{ money(position.net) }}</div>
        <div class="text-xs text-muted-foreground">{{ netLabel }}</div>
      </div>
    </div>

    <p v-if="!hasOverlap" class="mt-3 text-xs text-muted-foreground">
      {{ t('contra.nothing_overlaps') }}
    </p>
  </div>
</template>
