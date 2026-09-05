<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { useSoundPreferences } from '@/composables/useSoundPreferences';
const props = defineProps({
    accounts: Object,
    user: Object,
    filters: Object,
    filterOptions: Object,
    balanceNatureFormat: String,
});

console.log('accounts', props.accounts)
const { t } = useI18n();
const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'english_name', label: t('general.name') },
    { key: 'local_name', label: t('account.local_name'), sortable: true },
    { key: 'balance', label: t('general.balance') },
    // { key: 'remark', label: t('general.remark') },
    { key: 'parent.name', label: t('account.parent') },
    { key: 'account_type.name', label: t('account.account_type') },
    {
        key: 'is_main',
        label: t('account.account_scope'),
        badge: (item) => item.is_main
            ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'
            : 'bg-muted text-muted-foreground',
        render: (item) => item.is_main ? t('account.main_account') : t('account.custom_account'),
    },
    { key: 'actions', label: t('general.actions') },
]));

const editItem = (item) => {
    router.visit(route('chart-of-accounts.edit', item.id));
};

const showItem = (id) => {
    router.visit(route('chart-of-accounts.show', id));
};
const filterFields = computed(() => ([
    { key: 'number', label: t('general.number'), type: 'text' },
    { key: 'name', label: t('general.name'), type: 'text' },
    { key: 'local_name', label: t('account.local_name'), type: 'text' },
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
const { play } = useSoundPreferences();
const deleteItem = (id) => {
    // Main accounts are protected server-side too, but warning before the confirm
    // dialog even opens is clearer than letting the user confirm and only then
    // learn (via a post-submit toast) that nothing happened.
    const item = props.accounts?.data?.find((account) => account.id === id);

    if (item?.is_main) {
        play('warning');
        toast.error(t('account.cannot_delete_main_account_title'), {
            description: t('account.cannot_delete_main_account_desc'),
            class: 'bg-pink-600 text-white',
            duration: 8000,
        });
        return;
    }

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
            exportRoute="chart-of-accounts.export"
            :showAddButton="true"
            :addTitle="t('account.account')"
            :addAction="'redirect'"
            :addRoute="'chart-of-accounts.create'"
        />
    </AppLayout>
</template>
