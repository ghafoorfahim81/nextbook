<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/UnitMeasures/CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    unitMeasures: Object,
});
const isDialogOpen = ref(false)
const editingUnitMeasure = ref(null)
const { t } = useI18n()

const columns = ref([
    {
        key: 'quantity.quantity',
        label: t('admin.unit_measure.metric'),
        sortable: true,
        render: (row) => row.quantity?.quantity ?? '-',
    },
    {
        key: 'quantity.unit',
        label: t('admin.unit_measure.base_unit'),
        sortable: true,
        render: (row) => row.quantity?.unit ?? '-',
    },
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'unit', label: t('admin.unit_measure.unit'), sortable: true },
    { key: 'symbol', label: t('admin.shared.symbol') },
    { key: 'actions', label: t('general.action') },
]);

const editItem = (item) => {
    editingUnitMeasure.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('unit-measures.destroy', id, {
        title: t('general.delete', { name: t('admin.unit_measure.unit_measure') }),
        description: t('general.delete_description', { name: t('admin.unit_measure.unit_measure') }),
        successMessage: t('general.delete_success', { name: t('admin.unit_measure.unit_measure') }),
    })

};

</script>

<template>
    <AppLayout :title="t('admin.unit_measure.unit_measures')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingUnitMeasure"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingUnitMeasure = null;
            }"
            @saved="() => { editingUnitMeasure = null }"
        />
        <DataTable
            :items="unitMeasures"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('admin.unit_measure.unit_measures')"
            :url="`unit-measures.index`"
            :showAddButton="true"
            :addTitle="t('admin.unit_measure.unit_measure')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
