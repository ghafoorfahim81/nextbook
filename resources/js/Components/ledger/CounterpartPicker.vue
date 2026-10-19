<script setup>
/**
 * Pairing this party's account with their other one.
 *
 * Someone who both buys from us and sells to us has two accounts, because a
 * ledger's control account follows its type. This is the only field that says
 * the two are the same person — it drives the combined balance on their page
 * and fills the other side of a set-off.
 *
 * The picker always offers the OPPOSITE role: from a customer form it lists
 * suppliers, and the other way round. Pairing two accounts of the same role
 * would be meaningless, and the server refuses it anyway.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import NextSelect from '@/Components/next/NextSelect.vue'
import { useLazyProps } from '@/composables/useLazyProps'

const props = defineProps({
  modelValue: { type: String, default: null },
  /** This ledger's own role — the picker offers the other one. */
  role: { type: String, required: true },
  /** The already-paired account, so an edit form renders filled in. */
  preselected: { type: Object, default: null },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const page = usePage()

useLazyProps(page.props, ['ledgers'])

const ledgers = computed(() => page.props.ledgers?.data || [])
const wantedRole = computed(() => (props.role === 'customer' ? 'supplier' : 'customer'))
const selected = ref(null)

// An edit form knows the pairing before the ledger list has loaded, so seed
// the box from the record rather than waiting for a match to arrive.
onMounted(() => {
  if (props.preselected?.id) {
    selected.value = { ...props.preselected, name: props.preselected.name }
  }
})

watch(() => props.modelValue, (value) => {
  if (!value) {
    selected.value = null

    return
  }

  if (selected.value?.id === value) return

  const match = ledgers.value.find((ledger) => ledger.id === value)
  if (match) selected.value = match
})

function choose(option) {
  selected.value = option || null
  emit('update:modelValue', option?.id ?? null)
}
</script>

<template>
  <div>
    <NextSelect
      :options="ledgers"
      :model-value="selected"
      @update:modelValue="choose"
      label-key="name"
      value-key="id"
      :reduce="ledger => ledger"
      :floating-text="wantedRole === 'supplier'
        ? t('contra.supplier_account')
        : t('contra.customer_account')"
      :searchable="true"
      :clearable="true"
      resource-type="ledgers"
      :search-options="{ types: [wantedRole] }"
      :search-fields="['name', 'email', 'phone_no']"
      :error="error"
    />
    <p class="mt-1 text-xs text-muted-foreground">{{ t('contra.pairing_hint') }}</p>
  </div>
</template>
