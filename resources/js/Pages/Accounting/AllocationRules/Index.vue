<script setup>
/** Allocation rules (phase 7): how shared costs split across branches. */
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import { useDeleteResource } from '@/composables/useDeleteResource'

defineProps({ rules: Object })

const { t } = useI18n()

const columns = computed(() => [
    { key: 'name', label: t('dimension.name'), sortable: true },
    { key: 'current', label: t('dimension.current_split'), render: (row) => row.current || t('dimension.no_version') },
    { key: 'is_active', label: t('general.status'), render: (row) => (row.is_active ? t('general.active') : t('general.inactive')) },
    { key: 'actions', label: t('general.action') },
])

const { deleteResource } = useDeleteResource()
const remove = (id) => deleteResource('allocation-rules.destroy', id, {
    title: t('general.delete', { name: t('dimension.allocation_rule') }),
    name: t('dimension.allocation_rule'),
})
</script>

<template>
    <AppLayout :title="t('dimension.allocation_rules')">
        <p class="text-sm text-muted-foreground">{{ t('dimension.allocation_rules_hint') }}</p>
        <DataTable
            can="allocation_rules"
            :items="rules"
            :columns="columns"
            :title="t('dimension.allocation_rules')"
            :url="`allocation-rules.index`"
            :showAddButton="true"
            :addTitle="t('dimension.allocation_rule')"
            :addAction="'redirect'"
            :addRoute="'allocation-rules.create'"
            @edit="(item) => router.visit(route('allocation-rules.edit', item.id))"
            @delete="remove"
        />
    </AppLayout>
</template>
