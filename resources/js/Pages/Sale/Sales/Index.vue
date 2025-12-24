<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import SaleShowDialog from '@/Components/SaleShowDialog.vue';
import { ref, computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    sales: Object,
})

const showDialog = ref(false);
const selectedSaleId = ref(null);

const editItem = (item) => {
    router.visit(route('sales.edit', item.id));
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('sales.destroy', id, {
        title: t('general.delete', { name: t('sale.sales') }),
    })
}

const showItem = (id) => {
    selectedSaleId.value = id;
    showDialog.value = true;
}

const printItem = (id) => {
    router.visit(route('sales.print', id));
}

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'customer_name', label: t('ledger.customer.customer') },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'type', label: t('general.type'), sortable: true },
    { key: 'status', label: t('general.status') },
    { key: 'actions', label: t('general.actions') },
]))
</script>

<template>
    <AppLayout :title="t('sale.sales')">
            <DataTable
            :items="sales"
            :columns="columns"
            :title="t('sale.sales')"
            :url="`sales.index`"
            :showAddButton="true"
            :showEditButton="true"
            :showDeleteButton="true"
            :hasShow="true"
            :hasPrint="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            @print="printItem"
            :addTitle="t('sale.sale')"
            :addAction="'redirect'"
            :addRoute="'sales.create'"
            />

        <SaleShowDialog
            v-model="showDialog"
            :sale-id="selectedSaleId"
        />
    </AppLayout>
</template>
