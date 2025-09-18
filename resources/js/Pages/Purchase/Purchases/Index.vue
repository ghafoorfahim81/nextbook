<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    purchases: Object,
})

const columns = ref([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'supplier', label: t('ledger.supplier.supplier') },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'type', label: t('general.type'), sortable: true },
    { key: 'status', label: t('general.status') },
])
</script>

<template>
    <AppLayout :title="t('purchase.purchase')">
        <DataTable :items="purchases" :columns="columns"
         :title="t('purchase.purchase')"
         :url="`purchases.index`"
         :showAddButton="true"
         :addTitle="t('purchase.purchase')"
         :addAction="'redirect'"
         :addRoute="'purchases.create'"
         />

    </AppLayout>
</template>
