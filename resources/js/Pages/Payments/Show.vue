<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import VoucherShow from '@/Components/accounting/VoucherShow.vue'

const { t } = useI18n()

const props = defineProps({
  payment: { type: Object, required: true },
  settlements: { type: Array, default: () => [] },
})

const number = computed(() => (props.payment?.data ?? props.payment ?? {}).number)
</script>

<template>
  <AppLayout :title="`${t('payment.payment')} #${number}`">
    <VoucherShow
      :voucher="payment"
      :settlements="settlements"
      kind="payment"
      index-route="payments.index"
      edit-route="payments.edit"
      print-route="payments.print"
      permission="payments.update"
    />
  </AppLayout>
</template>
