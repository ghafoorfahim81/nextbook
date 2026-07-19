<template>
  <div class="print-page">
    <VoucherLayout
      :voucher="props.voucher.data"
      :company="props.company"
      :voucher-type="props.voucherType"
      :ledger-balance="props.ledgerBalance"
    />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import '../../../css/print/invoice.css'
import VoucherLayout from './VoucherLayout.vue'

const props = defineProps({
  voucher: Object,
  company: Object,
  voucherType: String,
  ledgerBalance: Object,
})

onMounted(() => {
  window.print()
})
</script>
