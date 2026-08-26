<script setup>
import LandedCostForm from './Form.vue';
import { useI18n } from 'vue-i18n';

defineProps({
  allocationMethods: { type: Array, required: true },
  landedCost: { type: Object, required: true },
  purchases: { type: Array, required: true },
  landedCostCategories: { type: Array, default: () => [] },
  bankAccounts: { type: Array, default: () => [] },
  currencies: { type: Array, default: () => [] },
});

const { t } = useI18n();
</script>

<template>
  <LandedCostForm
    :allocation-methods="allocationMethods"
    :landed-cost="landedCost"
    :purchases="purchases"
    :landed-cost-categories="landedCostCategories"
    :bank-accounts="bankAccounts"
    :currencies="currencies"
    :page-title="t('general.edit', { name: t('landed_cost.title') })"
    submit-route-name="landed-costs.update"
    submit-method="put"
  />
</template>
