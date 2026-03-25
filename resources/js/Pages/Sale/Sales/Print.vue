<template>
    <div class="print-page" :data-invoice-theme="props.invoiceTheme">
      <InvoiceLayout :invoice="props.invoice.data" :company="props.company" :invoice-theme="props.invoiceTheme" />
    </div>
  </template>

  <script setup>
  import { onMounted } from 'vue'
  import '../../../../css/print/invoice.css'
  import InvoiceLayout from './InvoiceLayout.vue'
  const props = defineProps({ invoice: Object, company: Object, invoiceTheme: String })

  onMounted(() => {
    window.print()
  })
  </script>
