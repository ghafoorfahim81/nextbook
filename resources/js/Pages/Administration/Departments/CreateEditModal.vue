<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import ModalDialog from '@/Components/next/Dialog.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    departments: { type: [Array, Object], default: () => [] },
})

const emit = defineEmits(['update:isDialogOpen', 'saved'])
const { t } = useI18n()
const localDialogOpen = ref(props.isDialogOpen)
const isEditing = computed(() => !!props.editingItem?.id)
const departmentOptions = computed(() => {
    const departments = props.departments?.data ?? props.departments
    return (Array.isArray(departments) ? departments : [])
        .filter((department) => department.id !== props.editingItem?.id)
})

const form = useForm({
    name: '',
    code: '',
    parent_id: null,
    selected_parent: null,
    remark: '',
})

watch(() => props.isDialogOpen, (value) => {
    localDialogOpen.value = value
})

watch(() => localDialogOpen.value, (value) => emit('update:isDialogOpen', value))

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            name: item.name ?? '',
            code: item.code ?? '',
            parent_id: item.parent_id ?? null,
            selected_parent: departmentOptions.value.find((department) => department.id === item.parent_id) ?? null,
            remark: item.remark ?? '',
        })
    } else {
        form.reset()
        form.clearErrors()
    }
}, { immediate: true })

const closeModal = () => {
    localDialogOpen.value = false
    form.reset()
    form.clearErrors()
}

const handleSubmit = () => {
    const isEdit = isEditing.value
    const options = {
        onBefore: () => form.transform(({ selected_parent, ...data }) => data),
        onSuccess: () => {
            emit('saved')
            toast.success(t('general.success'), {
                description: t(isEdit ? 'general.update_success' : 'general.create_success', {
                    name: t('admin.department.department'),
                }),
                class: 'bg-green-600',
            })
            closeModal()
        },
    }

    if (isEdit) {
        form.patch(route('departments.update', props.editingItem.id), options)
    } else {
        form.post(route('departments.store'), options)
    }
}
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('admin.department.department') }) : t('general.create', { name: t('admin.department.department') })"
        :confirm-text="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        :closeable="true"
        :submitting="form.processing"
        @update:open="localDialogOpen = $event"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form id="modalForm" @submit.prevent="handleSubmit">
            <div class="grid grid-cols-1 gap-4 py-4 md:grid-cols-2">
                <NextInput
                    v-model="form.name"
                    :label="t('general.name')"
                    :placeholder="t('general.enter', { text: t('general.name') })"
                    :error="form.errors?.name"
                    autofocus
                />
                <NextInput
                    v-model="form.code"
                    :label="t('general.code')"
                    :placeholder="t('general.enter', { text: t('general.code') })"
                    :error="form.errors?.code"
                />
                <div class="md:col-span-2">
                    <NextSelect
                        v-model="form.selected_parent"
                        :options="departmentOptions"
                        label-key="name"
                        value-key="id"
                        :reduce="(department) => department"
                        :floating-text="t('admin.shared.parent')"
                        :error="form.errors?.parent_id"
                        append-in-dialog
                        @update:model-value="(value) => { form.parent_id = value?.id ?? null }"
                    />
                </div>
                <div class="md:col-span-2">
                    <NextTextarea
                        v-model="form.remark"
                        :label="t('admin.shared.remark')"
                        :error="form.errors?.remark"
                    />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
