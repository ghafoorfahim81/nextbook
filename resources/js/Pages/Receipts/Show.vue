<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import VoucherShow from '@/Components/accounting/VoucherShow.vue'

const { t } = useI18n()

const props = defineProps({
  receipt: { type: Object, required: true },
  settlements: { type: Array, default: () => [] },
})

const number = computed(() => (props.receipt?.data ?? props.receipt ?? {}).number)
</script>

<template>
  <AppLayout :title="`${t('receipt.receipt')} #${number}`">
    <VoucherShow
      :voucher="receipt"
      :settlements="settlements"
      kind="receipt"
      index-route="receipts.index"
      edit-route="receipts.edit"
      print-route="receipts.print"
      permission="receipts.update"
    />
  </AppLayout>
</template>
