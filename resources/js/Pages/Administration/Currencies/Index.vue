<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/Currencies/CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    currencies: Object,
    branches: {
        type: Array,
        required: true,
    },
});
const isDialogOpen = ref(false)
const editingCurrency = ref(null)
const { t } = useI18n()

const columns = computed(() => ([ 
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'code', label: t('admin.currency.code'), sortable: true },
    { key: 'exchange_rate', label: t('admin.currency.exchange_rate'), sortable: true },
    { key: 'symbol', label: t('admin.shared.symbol') },
    { key: 'format', label: t('admin.currency.format') },
    { key: 'actions', label: t('general.action') },
]));

const editItem = (item) => {
    editingCurrency.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('currencies.destroy', id, {
        title: t('general.delete', { name: t('admin.currency.currency') }),
        description: t('general.delete_description', { name: t('admin.currency.currency') }),
        successMessage: t('general.delete_success', { name: t('admin.currency.currency') }),
    })

};

</script>

<template>
    <AppLayout :title="t('admin.currency.currencies')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingCurrency"
            :currencies="currencies"
            :branches="branches"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingCurrency = null;
            }"
            @saved="() => { editingCurrency = null }"
        />
        <DataTable
            can="currencies"
            :items="currencies"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('admin.currency.currencies')"
            :url="`currencies.index`"
            :showAddButton="true"
            :addTitle="t('admin.currency.currency')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
