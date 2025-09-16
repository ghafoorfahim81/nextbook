<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import {useDeleteResource} from "@/composables/useDeleteResource.js";
import { useI18n } from 'vue-i18n';
const { t } = useI18n()
const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    items: Object,
})

const columns = ref([
    { key: 'name', label: t('general.name') },
    { key: 'code', label: t('admin.currency.code') },
    { key: 'category', label: t('admin.category.category') },
    { key: 'measure', label: t('admin.unit_measure.unit_measure') },
    { key: 'brand_name', label: t('admin.brand.brand') },
    { key: 'cost', label: t('item.cost') },
    { key: 'quantity', label: t('general.quantity') },
    { key: 'mrp_rate', label: t('item.mrp_rate') },
    { key: 'actions', label: t('general.actions') },

])

const editItem = (item) => {
    window.location.href = `/items/${item.id}/edit`;
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('items.destroy', id, {
        title: t('general.delete', { name: t('item.item') }),
        description: t('general.delete_description', { name: t('item.item') }),
        successMessage: t('general.delete_success', { name: t('item.item') }),
    })

};

</script>

<template>
    <AppLayout :title="t('item.items')">

        <DataTable :items="items" :columns="columns"
                   @delete="deleteItem"
                   @edit="editItem"
                   :title="t('item.items')"
                   :url="`items.index`"
                   :showAddButton="true"
                   :addTitle="t('item.item')"
                   :addAction="'redirect'"
                   :addRoute="'items.create'"
                   />

    </AppLayout>
</template>
