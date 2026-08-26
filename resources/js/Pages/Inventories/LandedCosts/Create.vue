<script setup>
import LandedCostForm from './Form.vue';
import { useI18n } from 'vue-i18n';

defineProps({
  allocationMethods: { type: Array, required: true },
  purchases: { type: Array, required: true },
  landedCostCategories: { type: Array, default: () => [] },
});

const { t } = useI18n();
</script>

<template>
  <LandedCostForm
    :allocation-methods="allocationMethods"
    :purchases="purchases"
    :landed-cost-categories="landedCostCategories"
    :page-title="t('general.create', { name: t('landed_cost.title') })"
    submit-route-name="landed-costs.store"
    submit-method="post"
  />
</template>
