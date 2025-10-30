<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import SaleShowDialog from '@/Components/SaleShowDialog.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
const { t } = useI18n();

const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    sales: Object,
})

console.log('this is sales',props.sales);
const showDialog = ref(false);
const selectedSaleId = ref(null);

const editItem = (item) => {
    window.location.href = `/sales/${item.id}/edit`;
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('sales.destroy', id, {
        title: t('general.delete', { name: t('sale.sale') }),
    })
}

const showItem = (id) => {
    selectedSaleId.value = id;
    showDialog.value = true;
}

const columns = ref([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'customer_name', label: t('ledger.customer.customer') },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'type', label: t('general.type'), sortable: true },
    { key: 'status', label: t('general.status') },
    { key: 'actions', label: t('general.actions') },
])
</script>

<template>
    <AppLayout :title="t('sale.sale')">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">{{ t('sale.sales') }}</h1>
                <Button as-child>
                    <a href="/sales/create">{{ t('general.create') }}</a>
                </Button>
            </div>

            <DataTable
                :items="sales"
                :columns="columns"
                :searchable="true"
                :filterable="true"
                :sortable="true"
                @edit="editItem"
                @delete="deleteItem"
                @show="showItem"
                resource-name="sales"
            />
        </div>

        <SaleShowDialog
            v-model="showDialog"
            :sale-id="selectedSaleId"
        />
    </AppLayout>
</template>
