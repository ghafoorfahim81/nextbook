<template>
  <div class="print-page" :data-invoice-theme="props.invoiceTheme">
    <InvoiceLayout :invoice="props.invoice.data" :company="props.company" :invoice-theme="props.invoiceTheme" />
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import '../../../../css/print/invoice.css'
import InvoiceLayout from './InvoiceLayout.vue'

const props = defineProps({ invoice: Object, company: Object, invoiceTheme: String })

let hasPrinted = false
let printTimer = null

const clearPrintTimer = () => {
  if (printTimer !== null) {
    window.clearTimeout(printTimer)
    printTimer = null
  }
}

const queuePrint = () => {
  if (hasPrinted || document.visibilityState !== 'visible') {
    return
  }

  clearPrintTimer()

  Promise.resolve(document.fonts?.ready).finally(() => {
    if (hasPrinted || document.visibilityState !== 'visible') {
      return
    }

    printTimer = window.setTimeout(() => {
      if (hasPrinted || document.visibilityState !== 'visible') {
        return
      }

      hasPrinted = true
      window.print()
    }, 180)
  })
}

const handleVisibilityChange = () => {
  if (document.visibilityState === 'visible') {
    queuePrint()
    return
  }

  clearPrintTimer()
}

onMounted(() => {
  window.addEventListener('focus', queuePrint)
  document.addEventListener('visibilitychange', handleVisibilityChange)
  queuePrint()
})

onBeforeUnmount(() => {
  clearPrintTimer()
  window.removeEventListener('focus', queuePrint)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
})
</script>
