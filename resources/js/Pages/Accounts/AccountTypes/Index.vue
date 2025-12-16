<script setup>
    import AppLayout from '@/Layouts/Layout.vue';
    import DataTable from '@/Components/DataTable.vue';
    import { h, ref } from 'vue';
    import { useDeleteResource } from '@/composables/useDeleteResource';
    import CreateEditModal from '@/Pages/Accounts/AccountTypes/CreateEditModal.vue';
    import { useI18n } from 'vue-i18n';
    
    const props = defineProps({
            accountTypes: Object,
    });
    const isDialogOpen = ref(false)
    const editingAccountType = ref(null)
    const { t } = useI18n()
    
    const columns = ref([ 
        { key: 'name', label: t('general.name'),sortable: true },
        { key: 'remark', label: t('general.remark') },
        { key: 'actions', label: t('general.action') },
    ]);
    
    const editItem = (item) => {
        editingAccountType.value = item
        isDialogOpen.value = true
    }
    const { deleteResource } = useDeleteResource()
    const deleteItem = (id) => {
        deleteResource('account-types.destroy', id, {
            title: t('general.delete', { name: t('account.account_type') }),
            description: t('general.delete_description', { name: t('account.account_type') }),
            successMessage: t('general.delete_success', { name: t('account.account_type') }),
        })
    
    };
    
    </script>
    
    <template>
        <AppLayout :title="t('account.account_types')">
            <CreateEditModal
                :isDialogOpen="isDialogOpen"
                :editingItem="editingAccountType"
                :accountTypes="accountTypes"
                @update:isDialogOpen="(value) => {
                    isDialogOpen = value;
                    if (!value) editingAccountType = null;
                }"
                @saved="() => { editingAccountType = null }"
            />
            <DataTable
                :items="accountTypes"
                :columns="columns"
                @edit="editItem"
                @delete="deleteItem"
                @add="isDialogOpen = true"
                :title="t('account.account_types')"
                :url="`account-types.index`"
                :showAddButton="true"
                :addTitle="t('account.account_type')"
                :addAction="'modal'"
            />
        </AppLayout>
    </template>
    