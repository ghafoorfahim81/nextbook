<script setup>
    import AppLayout from '@/Layouts/Layout.vue';
    import DataTable from '@/Components/DataTable.vue';
    import { h, ref } from 'vue';
    import { useDeleteResource } from '@/composables/useDeleteResource';
    import { useI18n } from 'vue-i18n';

    const props = defineProps({
        customers: Object   ,
    });
    const isDialogOpen = ref(false)
    const editingStore = ref(null)
    const { t } = useI18n()

    const columns = ref([
        { key: 'name', label: t('general.name') },
        { key: 'code', label: t('admin.currency.code') },
        { key: 'contact_person', label: t('ledger.contact_person') },
        { key: 'phone_no', label: t('general.phone') },
        { key: 'email', label: t('general.email') },
        { key: 'actions', label: t('general.actions') },

    ]);

    const editItem = (item) => {
     window.location.href = `/customers/${item.id}/edit`
    }
    const { deleteResource } = useDeleteResource()
    const deleteItem = (id) => {
        deleteResource('stores.destroy', id, {
            title: t('general.delete', { name: t('admin.store.store') }),
            description: t('general.delete_description', { name: t('admin.store.store') }),
            successMessage: t('general.delete_success', { name: t('admin.store.store') }),
        })

    };

    </script>

    <template>
        <AppLayout :title="t('admin.store.stores')">

            <DataTable
                :items="customers"
                :columns="columns"
                @delete="deleteItem"
                @edit="editItem"
                @show="showItem"
                @add="isDialogOpen = true"
                :title="t('ledger.customer.customers')"
                :url="`customers.index`"
                :hasShow="true"
                :showAddButton="true"
                :addTitle="t('ledger.customer.customer')"
                :addAction="'redirect'"
                :addRoute="'customers.create'"
            />
        </AppLayout>
    </template>
