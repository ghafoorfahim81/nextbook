<template>
    <div class="print-page">
      <InvoiceLayout :invoice="props.invoice.data" :company="props.company" />
    </div>
  </template>

  <script setup>
  import { onMounted } from 'vue'
  import '../../../../css/print/invoice.css'
  import InvoiceLayout from './InvoiceLayout.vue'
  const props = defineProps({ invoice: Object, company: Object })
  
  console.log(props.invoice.data)
  onMounted(() => {
    window.print()
  })
  </script>
