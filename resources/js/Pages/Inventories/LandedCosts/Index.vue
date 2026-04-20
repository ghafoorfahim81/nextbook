<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { router } from '@inertiajs/vue3';

const { t } = useI18n();

const props = defineProps({
  landedCosts: Object,
  filters: Object,
  filterOptions: Object,
});

const columns = computed(() => ([
  { key: 'purchase_number', label: t('landed_cost.purchase_order'), sortable: false },
  { key: 'date', label: t('general.date'), sortable: true },
  { key: 'total_cost', label: t('landed_cost.total_additional_cost'), sortable: true },
  { key: 'allocated_total', label: t('landed_cost.allocated_amount'), sortable: true },
  { key: 'allocation_method', label: t('landed_cost.allocation_method') },
  { key: 'status', label: t('general.status') },
  { key: 'actions', label: t('general.actions') },
]));

const pageTitle = computed(() => t('landed_cost.title'));

const filterFields = computed(() => ([
  {
    key: 'purchase_id',
    label: t('landed_cost.purchase_order'),
    type: 'select',
    options: (props.filterOptions?.purchases || []).map((purchase) => ({ id: purchase.id, name: purchase.name })),
  },
  {
    key: 'status',
    label: t('general.status'),
    type: 'select',
    options: (props.filterOptions?.statuses || []).map((status) => ({ id: status.id, name: status.name })),
  },
  {
    key: 'allocation_method',
    label: t('landed_cost.allocation_method'),
    type: 'select',
    options: (props.filterOptions?.allocationMethods || []).map((method) => ({ id: method.id, name: method.name })),
  },
  { key: 'date', label: t('general.date'), type: 'daterange' },
]));

const { deleteResource } = useDeleteResource();

const editItem = (item) => {
  router.visit(route('landed-costs.edit', item.id));
};

const showItem = (id) => {
  router.visit(route('landed-costs.show', id));
};

const deleteItem = (id) => {
  deleteResource('landed-costs.destroy', id, {
    title: t('general.delete', { name: t('landed_cost.title') }),
  });
};
</script>

<template>
  <AppLayout :title="pageTitle">
    <DataTable
      can="landed_costs.view_any"
      :items="landedCosts"
      :columns="columns"
      :filters="filters"
      :filterFields="filterFields"
      :title="t('landed_cost.title')"
      :url="`landed-costs.index`"
      :showAddButton="true"
      :hasShow="true"
      :showEditButton="true"
      :showDeleteButton="true"
      @show="showItem"
      @edit="editItem"
      @delete="deleteItem"
      :addTitle="t('landed_cost.title')"
      :addAction="'redirect'"
      :addRoute="'landed-costs.create'"
    />
  </AppLayout>
</template>
