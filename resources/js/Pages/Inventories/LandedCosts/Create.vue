<script setup>
import LandedCostForm from './Form.vue';
import { useI18n } from 'vue-i18n';

defineProps({
  allocationMethods: { type: Array, required: true },
  purchases: { type: Array, required: true },
});

const { t } = useI18n();
</script>

<template>
  <LandedCostForm
    :allocation-methods="allocationMethods"
    :purchases="purchases"
    :page-title="t('landed_cost.create_title')"
    submit-route-name="landed-costs.store"
    submit-method="post"
  />
</template>
