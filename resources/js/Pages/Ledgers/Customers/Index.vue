<script setup>
    import AppLayout from '@/Layouts/Layout.vue';
    import DataTable from '@/Components/DataTable.vue';
    import CustomerShowDialog from '@/Components/CustomerShowDialog.vue';
    import { ref, computed } from 'vue';
    import { useDeleteResource } from '@/composables/useDeleteResource';
    import { useI18n } from 'vue-i18n';

    const props = defineProps({
        customers: Object   ,
    });
    const { t } = useI18n()

    const columns = computed(() => ([
        { key: 'name', label: t('general.name') },
        { key: 'code', label: t('admin.currency.code') },
        { key: 'contact_person', label: t('ledger.contact_person') },
        { key: 'phone_no', label: t('general.phone') },
        { key: 'email', label: t('general.email') },
        { key: 'actions', label: t('general.actions') },

    ]));

    const editItem = (item) => {
        window.location.href = `/customers/${item.id}/edit`
    }

    const showDialog = ref(false)
    const selectedCustomerId = ref(null)

    const showItem = (item) => {
        selectedCustomerId.value = item 
        showDialog.value = true
    }
    const { deleteResource } = useDeleteResource()
    const deleteItem = (id) => {
        deleteResource('customers.destroy', id, {
            title: t('general.delete', { name: t('ledger.customer.customer') }),
            description: t('general.delete_description', { name: t('ledger.customer.customer') }),
            successMessage: t('general.delete_success', { name: t('ledger.customer.customer') }),
        })

    };

    </script>

    <template>
    <AppLayout :title="t('ledger.customer.customers')">

        <DataTable
            :items="customers"
            :columns="columns"
            @delete="deleteItem"
            @edit="editItem"
            @show="showItem"
            :title="t('ledger.customer.customers')"
            :url="`customers.index`"
            :hasShow="true"
            :showAddButton="true"
            :addTitle="t('ledger.customer.customer')"
            :addAction="'redirect'"
            :addRoute="'customers.create'"
        />

        <CustomerShowDialog
            v-model:open="showDialog"
            :customer-id="selectedCustomerId"
        />
    </AppLayout>
</template>
