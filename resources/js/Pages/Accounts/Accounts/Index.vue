<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import AccountShowDialog from '@/Components/AccountShowDialog.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
const props = defineProps({
    accounts: Object,
    user: Object,
    filters: Object,
    filterOptions: Object,
    balanceNatureFormat: String,
});
const { t } = useI18n();
const columns = computed(() => ([
    { key: 'name', label: t('general.name') },
    { key: 'number', label: t('general.number') },
    { key: 'remark', label: t('general.remark') },
    { key: 'account_type.name', label: t('account.account_type') },
    { key: 'balance', label: t('general.balance') },
    { key: 'actions', label: t('general.actions') },
]));

const editItem = (item) => {
    router.visit(route('chart-of-accounts.edit', item.id));
};

const showDialog = ref(false);
const selectedAccountId = ref(null);

const showItem = (id) => {
    selectedAccountId.value = id;
    showDialog.value = true;
};
const filterFields = computed(() => ([
    { key: 'number', label: t('general.number'), type: 'text' },
    { key: 'name', label: t('general.name'), type: 'text' },
    {
        key: 'account_type_id',
        label: t('account.account_type'),
        type: 'select',
        options: (props.filterOptions?.accountTypes || []).map((a) => ({ id: a.id, name: a.name })),
    },
    {
        key: 'created_by',
        label: t('general.created_by'),
        type: 'select',
        options: (props.filterOptions?.users || []).map((u) => ({ id: u.id, name: u.name })),
    },
]));
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
            can="accounts"
            :items="accounts"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @delete="deleteItem"
            @edit="editItem"
            @show="showItem"
            :title="t('account.chart_of_accounts')"
            :url="`chart-of-accounts.index`"
            :hasShow="true"
            :addTitle="t('account.account')"
            :addAction="'redirect'"
            :addRoute="'chart-of-accounts.create'"
        />

        <AccountShowDialog
            v-model:open="showDialog"
            :account-id="selectedAccountId"
            :balance-nature-format="balanceNatureFormat"
        />
    </AppLayout>
</template>
