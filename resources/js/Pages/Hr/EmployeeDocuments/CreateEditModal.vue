<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
import AttachmentUploader from '@/Components/AttachmentUploader.vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    filterOptions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);

const isEditing = computed(() => !!props.editingItem?.id);
const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (val) => localDialogOpen.value = val);
watch(() => localDialogOpen.value, (val) => emit('update:isDialogOpen', val));

const blank = () => ({
    employee_id: null, selected_employee: null,
    document_type: 'tazkira', selected_document_type: null,
    document_number: '',
    issued_by: '',
    issue_date: '', expiry_date: '',
    is_verified: false,
    reminder_days_before: 30,
    remark: '',
    documents: [],
});

const form = useForm(blank());

const opt = (key) => props.filterOptions?.[key] || [];

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            employee_id: item.employee_id,
            selected_employee: item.employee_id ? { id: item.employee_id, name: item.employee_name } : null,
            document_type: item.document_type ?? 'tazkira',
            selected_document_type: opt('documentTypes').find((x) => x.id === item.document_type) ?? null,
            document_number: item.document_number ?? '',
            issued_by: item.issued_by ?? '',
            issue_date: item.issue_date ?? '',
            expiry_date: item.expiry_date ?? '',
            is_verified: !!item.is_verified,
            reminder_days_before: item.reminder_days_before ?? 30,
            remark: item.remark ?? '',
        });
    } else {
        form.defaults(blank());
        form.reset();
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

const handleSubmit = async () => {
    const isEdit = isEditing.value;
    const hasFiles = (form.documents || []).length > 0;

    form.transform((data) => ({
        ...Object.fromEntries(Object.entries(data).filter(([key]) => !key.startsWith('selected_'))),
        // Inertia cannot PATCH multipart, so an update carrying a file posts
        // with a method override instead.
        ...(isEdit && hasFiles ? { _method: 'patch' } : {}),
    }));

    const submitOptions = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(t('general.success'), {
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.document') }),
                class: 'bg-green-600',
            });
        },
    };

    if (!isEdit) {
        await form.post(route('employee-documents.store'), submitOptions);
    } else if (hasFiles) {
        await form.post(route('employee-documents.update', props.editingItem.id), submitOptions);
    } else {
        await form.patch(route('employee-documents.update', props.editingItem.id), submitOptions);
    }
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.document') }) : t('general.create', { name: t('hr.document') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        :closeable="true"
        width="w-[95vw] max-w-[95vw] sm:w-[820px] sm:max-w-[820px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid grid-cols-1 gap-4 py-4 md:grid-cols-3">
                <NextSelect
                    :options="[]" v-model="form.selected_employee"
                    @update:modelValue="(v) => { form.employee_id = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :searchable="true" resource-type="employees" :search-fields="['full_name', 'code']"
                    :floating-text="t('hr.employee')" :error="form.errors?.employee_id"
                    append-in-dialog
                />
                <NextSelect
                    :options="opt('documentTypes')" v-model="form.selected_document_type"
                    @update:modelValue="(v) => { form.document_type = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.document_type')" :error="form.errors?.document_type"
                    append-in-dialog
                />
                <NextInput :label="t('hr.document_number')" v-model="form.document_number" :error="form.errors?.document_number" />

                <NextInput :label="t('hr.issued_by')" v-model="form.issued_by" :error="form.errors?.issued_by" />
                <NextDate v-model="form.issue_date" :label="t('hr.issue_date')" :error="form.errors?.issue_date" show-label />
                <NextDate v-model="form.expiry_date" :label="t('hr.expiry_date')" :error="form.errors?.expiry_date" show-label />

                <NextInput :label="t('hr.reminder_days_before')" type="number" v-model="form.reminder_days_before" :error="form.errors?.reminder_days_before" />
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.is_verified" class="h-4 w-4 rounded border-border text-primary" />
                    {{ t('hr.is_verified') }}
                </label>

                <div class="md:col-span-3">
                    <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
                </div>
                <div class="md:col-span-3">
                    <AttachmentUploader
                        v-model="form.documents"
                        :label="t('general.attachments')"
                        :error="form.errors?.['documents.0']"
                    />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
