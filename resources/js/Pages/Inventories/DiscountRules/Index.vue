<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import CreateEditModal from './CreateEditModal.vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';

defineProps({
    discountRules: Object,
    options: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);
const { deleteResource } = useDeleteResource();

const columns = computed(() => [
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'scope_label', label: t('discount_rule.applies_to') },
    { key: 'target_name', label: t('discount_rule.target') },
    { key: 'value_label', label: t('general.discount'), align: 'right' },
    { key: 'customer_group_name', label: t('discount_rule.customer_group') },
    { key: 'window_label', label: t('discount_rule.active_period') },
    { key: 'actions', label: t('general.actions') },
]);

// Labels (scope, value, date window) are assembled by DiscountRuleResource, so
// the table renders plain strings and the paginator reaches DataTable intact.
const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};
const deleteItem = (id) => deleteResource('discount-rules.destroy', id);
</script>

<template>
    <AppLayout :title="t('discount_rule.discount_rules')">
        <CreateEditModal
            :is-dialog-open="isDialogOpen"
            :editing-item="editingItem"
            :options="options"
            @update:is-dialog-open="isDialogOpen = $event"
            @saved="editingItem = null"
        />
        <DataTable
            can="discount_rules"
            :items="discountRules"
            :columns="columns"
            :title="t('discount_rule.discount_rules')"
            url="discount-rules.index"
            :show-add-button="true"
            :add-title="t('discount_rule.discount_rule')"
            add-action="modal"
            @add="editingItem = null; isDialogOpen = true"
            @edit="editItem"
            @delete="deleteItem"
        />
    </AppLayout>
</template>
