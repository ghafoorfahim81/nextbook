<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/UnitMeasures/CreateEditModal.vue';
import ShowDialog from '@/Pages/Administration/UnitMeasures/ShowDialog.vue';
import { useI18n } from 'vue-i18n';
const { t } = useI18n()
const props = defineProps({
    unitMeasures: Object,
    quantities: {
        type: [Array, Object],
        default: () => [],
    },
});
const isDialogOpen = ref(false)
const editingMeasure = ref(null)
const showDialogOpen = ref(false)
const viewingMeasureId = ref(null)


const columns = computed(() => ([
    {
        key: 'quantity.quantity',
        label: t('admin.unit_measure.quantity'),
        sortable: true,
        render: (row) => row.quantity?.quantity ?? '-',
    },
    {
        key: 'quantity.unit',
        label: t('admin.unit_measure.base_unit'),
        sortable: true,
        render: (row) => row.quantity?.unit ?? '-',
    },
    { key: 'name', label: t('general.name'),sortable: true },
    { key: 'unit', label: t('admin.unit_measure.unit') },
    { key: 'symbol', label: t('admin.shared.symbol') },
    {
        key: 'is_active',
        label: t('general.status'),
        sortable: true,
        render: (row) => row.is_active ? t('general.active') : t('general.inactive'),
    },
    {
        key: 'created_by.name',
        label: t('general.created_by'),
        render: (row) => row.created_by?.name ?? '-',
    },
    {
        key: 'updated_by.name',
        label: t('general.updated_by'),
        render: (row) => row.updated_by?.name ?? '-',
    },
    { key: 'actions', label: t('general.action') },
]));


const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('unit-measures.destroy', id, {
        title: t('general.delete', { name: t('admin.unit_measure.unit_measure') }),
        description: t('general.delete_description', { name: t('admin.unit_measure.unit_measure') }),
        successMessage: t('general.delete_success', { name: t('admin.unit_measure.unit_measure') }),
    })

};

const editItem = (item) => {
    editingMeasure.value = item
    isDialogOpen.value = true
}

const showItem = (id) => {
    viewingMeasureId.value = id
    showDialogOpen.value = true
}

</script>

<template>
    <AppLayout :title="t('admin.unit_measure.unit_measure')">
        <div class="flex gap-2 items-center mb-4">
            <div class="ml-auto gap-3">
                <CreateEditModal
                    :isDialogOpen="isDialogOpen"
                    :editingItem="editingMeasure"
                    :quantities="props.quantities"
                    @update:isDialogOpen="(value) => {
                        isDialogOpen = value;
                        if (!value) editingMeasure = null;
                    }"
                    @saved="() => { editingMeasure = null }"
                />
            </div>
        </div>
        <ShowDialog
            v-model:open="showDialogOpen"
            :unit-measure-id="viewingMeasureId"
            @edit="(item) => {
                showDialogOpen = false
                viewingMeasureId = null
                editingMeasure = item
                isDialogOpen = true
            }"
        />
        <DataTable
            can="unit_measures"
            :items="unitMeasures"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :title="`${t('admin.unit_measure.unit_measure')}`"
            :showAddButton="true"
            :addTitle="t('admin.unit_measure.unit_measure')"
            :addAction="'modal'"
            :hasEdit="true"
            :hasShow="true"
            @add="isDialogOpen = true"
            :addRoute="route('unit-measures.create')"
            :url="`unit-measures.index`"
        />
    </AppLayout>
</template>
