<script setup>
import LandedCostForm from './Form.vue';
import { useI18n } from 'vue-i18n';

defineProps({
  allocationMethods: { type: Array, required: true },
  landedCost: { type: Object, required: true },
  purchases: { type: Array, required: true },
});

const { t } = useI18n();
</script>

<template>
  <LandedCostForm
    :allocation-methods="allocationMethods"
    :landed-cost="landedCost"
    :purchases="purchases"
    :page-title="t('landed_cost.edit_title')"
    submit-route-name="landed-costs.update"
    submit-method="put"
  />
</template>
