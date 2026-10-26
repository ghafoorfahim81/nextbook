<script setup>
/**
 * Cost centers (phase 7): a list with a create/edit dialog. Where a cost
 * belongs, independent of the branch tree.
 */
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import ModalDialog from '@/Components/next/Dialog.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import { Switch } from '@/Components/ui/switch'
import { Label } from '@/Components/ui/label'
import { useDeleteResource } from '@/composables/useDeleteResource'

const props = defineProps({
    costCenters: Object,
    allCostCenters: { type: Array, default: () => [] },
})

const { t } = useI18n()

const columns = computed(() => [
    { key: 'code', label: t('dimension.code'), sortable: true },
    { key: 'name', label: t('dimension.name'), sortable: true },
    { key: 'parent', label: t('dimension.parent'), render: (row) => row.parent || '—' },
    { key: 'description', label: t('dimension.description') },
    { key: 'is_active', label: t('general.status'), render: (row) => (row.is_active ? t('general.active') : t('general.inactive')) },
    { key: 'actions', label: t('general.action') },
])

const dialogOpen = ref(false)
const editing = ref(null)

const form = useForm({ code: '', name: '', parent_id: null, description: '', is_active: true })

watch(editing, (item) => {
    form.clearErrors()
    form.code = item?.code ?? ''
    form.name = item?.name ?? ''
    form.parent_id = item?.parent_id ?? null
    form.description = item?.description ?? ''
    form.is_active = item?.is_active ?? true
})

// A cost center cannot sit under itself.
const parentOptions = computed(() => props.allCostCenters.filter((center) => center.id !== editing.value?.id))

const openCreate = () => {
    editing.value = null
    form.reset()
    dialogOpen.value = true
}

const openEdit = (item) => {
    editing.value = item
    dialogOpen.value = true
}

const save = () => {
    const isEdit = !!editing.value?.id
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false
            toast.success(t('general.success'), {
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('dimension.cost_center') }),
            })
        },
    }

    isEdit
        ? form.patch(route('cost-centers.update', editing.value.id), options)
        : form.post(route('cost-centers.store'), options)
}

const { deleteResource } = useDeleteResource()
const remove = (id) => deleteResource('cost-centers.destroy', id, {
    title: t('general.delete', { name: t('dimension.cost_center') }),
    name: t('dimension.cost_center'),
})
</script>

<template>
    <AppLayout :title="t('dimension.cost_centers')">
        <p class="text-sm text-muted-foreground">{{ t('dimension.cost_centers_hint') }}</p>

        <ModalDialog
            :open="dialogOpen"
            :title="editing ? t('general.edit', { name: t('dimension.cost_center') }) : t('general.create', { name: t('dimension.cost_center') })"
            :confirmText="editing ? t('general.update') : t('general.create')"
            :cancel-text="t('general.close')"
            :closeable="true"
            :submitting="form.processing"
            @update:open="dialogOpen = $event"
            @confirm="save"
            @cancel="dialogOpen = false"
        >
            <form id="modalForm" class="grid gap-4 py-4" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-3">
                    <NextInput v-model="form.code" :label="t('dimension.code')" :error="form.errors.code" />
                    <div class="sm:col-span-2">
                        <NextInput v-model="form.name" is-required :label="t('dimension.name')" :error="form.errors.name" />
                    </div>
                </div>
                <NextSelect
                    v-model="form.parent_id"
                    :options="parentOptions"
                    label-key="name"
                    value-key="id"
                    :reduce="(option) => option.id"
                    :floating-text="t('dimension.parent')"
                    :placeholder="t('dimension.no_parent')"
                    :clearable="true"
                    :error="form.errors.parent_id"
                />
                <NextTextarea v-model="form.description" :label="t('dimension.description')" :error="form.errors.description" />
                <div class="flex items-center gap-2">
                    <Switch id="cost_center_active" v-model:checked="form.is_active" />
                    <Label for="cost_center_active">{{ t('dimension.active') }}</Label>
                </div>
            </form>
        </ModalDialog>

        <DataTable
            can="cost_centers"
            :items="costCenters"
            :columns="columns"
            :title="t('dimension.cost_centers')"
            :url="`cost-centers.index`"
            :showAddButton="true"
            :addTitle="t('dimension.cost_center')"
            :addAction="'modal'"
            @add="openCreate"
            @edit="openEdit"
            @delete="remove"
        />
    </AppLayout>
</template>
