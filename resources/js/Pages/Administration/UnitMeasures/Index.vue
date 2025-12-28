<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/UnitMeasures/CreateEditModal.vue';
import { router } from '@inertiajs/vue3';
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


const columns = computed(() => ([
    {
        key: 'quantity.quantity',
        label: '',
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

</script>

<template>
    <AppLayout title="t('admin.unit_measure.unit_measure')">
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
        <DataTable
            can="unit_measures"
            :items="unitMeasures"
            :columns="columns"
            @delete="deleteItem"
            :title="`${t('admin.unit_measure.unit_measure')}`"
            :showAddButton="true"
            :addTitle="t('admin.unit_measure.unit_measure')"
            :addAction="'modal'"
            :hasEdit="false"
            @add="isDialogOpen = true"
            :addRoute="route('unit-measures.create')"
            :url="`unit-measures.index`"
        />
    </AppLayout>
</template>
