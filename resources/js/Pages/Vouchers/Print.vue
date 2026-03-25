<template>
  <div class="print-page">
    <VoucherLayout
      :voucher="props.voucher.data"
      :company="props.company"
      :voucher-type="props.voucherType"
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
})

onMounted(() => {
  window.print()
})
</script>
