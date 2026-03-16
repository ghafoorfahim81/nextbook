<script setup>
import { onMounted } from 'vue'

defineProps({
  sale: Object
})

onMounted(() => {
  window.print()
})
</script>

<template>
  <div class="print-page">
    <h1>Invoice #{{ sale.number }}</h1>

    <p>Customer: {{ sale.customer?.name }}</p>

    <table>
      <tr>
        <th>#</th>
        <th>Item</th>
        <th>Qty</th>
        <th>Price</th>
      </tr>
      <tr v-for="(item, i) in sale.items" :key="i">
        <td>{{ i + 1 }}</td>
        <td>{{ item.item?.name }}</td>
        <td>{{ item.qty }}</td>
        <td>{{ item.price }}</td>
      </tr>
    </table>
  </div>
</template>
