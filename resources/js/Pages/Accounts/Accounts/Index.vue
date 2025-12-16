<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import AccountShowDialog from '@/Components/AccountShowDialog.vue';
import { ref } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    accounts: Object,
});
const { t } = useI18n();

const columns = ref([
    { key: 'name', label: t('general.name') },
    { key: 'number', label: t('general.number') },
    { key: 'account_type.name', label: t('account.account_type') },
    { key: 'balance', label: t('general.balance') },
    { key: 'actions', label: t('general.actions') },
]);

const editItem = (item) => {
    window.location.href = `/chart-of-accounts/${item.id}/edit`;
};

const showDialog = ref(false);
const selectedAccountId = ref(null);

const showItem = (id) => { 
    selectedAccountId.value = id;
    showDialog.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('chart-of-accounts.destroy', id, {
        title: t('general.delete', { name: t('account.account') }),
        description: t('general.delete_description', { name: t('account.account') }),
        successMessage: t('general.delete_success', { name: t('account.account') }),
    });
};
</script>

<template>
    <AppLayout :title="t('account.chart_of_accounts')">
        <DataTable
            :items="accounts"
            :columns="columns"
            @delete="deleteItem"
            @edit="editItem"
            @show="showItem"
            :title="t('account.chart_of_accounts')"
            :url="`chart-of-accounts.index`"
            :hasShow="true"
            :showAddButton="true"
            :addTitle="t('account.account')"
            :addAction="'redirect'"
            :addRoute="'chart-of-accounts.create'"
        />

        <AccountShowDialog
            v-model:open="showDialog"
            :account-id="selectedAccountId"
        />
    </AppLayout>
</template>
