<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    departments: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);

const isEditing = computed(() => !!props.editingItem?.id);
const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (val) => localDialogOpen.value = val);
watch(() => localDialogOpen.value, (val) => emit('update:isDialogOpen', val));

const form = useForm({
    name: '',
    code: '',
    department_id: null,
    selected_department: null,
    grade_level: '',
    remark: '',
});

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            name: item.name ?? '',
            code: item.code ?? '',
            department_id: item.department_id ?? null,
            selected_department: props.departments.find((d) => d.id === item.department_id) ?? null,
            grade_level: item.grade_level ?? '',
            remark: item.remark ?? '',
        });
    } else {
        form.reset();
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

const handleSubmit = async () => {
    const isEdit = isEditing.value;

    const submitOptions = {
        // selected_department carries the whole object for the combobox; only
        // department_id belongs in the payload.
        onBefore: () => { form.transform((data) => {
            const { selected_department, ...rest } = data;
            return rest;
        }); },
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(t('general.success'), {
                description: t(
                    isEdit ? 'general.update_success' : 'general.create_success',
                    { name: t('admin.designation.designation') }
                ),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) {
        await form.patch(route('designations.update', props.editingItem.id), submitOptions);
    } else {
        await form.post(route('designations.store'), submitOptions);
    }
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing
            ? t('general.edit', { name: t('admin.designation.designation') })
            : t('general.create', { name: t('admin.designation.designation') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        :closeable="true"
        width="w-[95vw] max-w-[95vw] sm:w-[720px] sm:max-w-[720px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid grid-cols-1 gap-4 py-4 md:grid-cols-2">
                <NextInput
                    :label="t('general.name')"
                    :placeholder="t('general.enter', { text: t('general.name') })"
                    v-model="form.name"
                    :error="form.errors?.name"
                    autofocus
                />
                <NextInput
                    :label="t('general.code')"
                    :placeholder="t('general.enter', { text: t('general.code') })"
                    v-model="form.code"
                    :error="form.errors?.code"
                />
                <NextSelect
                    :options="departments"
                    v-model="form.selected_department"
                    @update:modelValue="(v) => { form.department_id = v?.id ?? null }"
                    label-key="name"
                    value-key="id"
                    :reduce="(d) => d"
                    :floating-text="t('hr.department')"
                    :error="form.errors?.department_id"
                    append-in-dialog
                />
                <NextInput
                    :label="t('admin.designation.grade_level')"
                    :placeholder="t('general.enter', { text: t('admin.designation.grade_level') })"
                    v-model="form.grade_level"
                    type="number"
                    :error="form.errors?.grade_level"
                />
                <div class="md:col-span-2">
                    <NextTextarea
                        :label="t('admin.shared.remark')"
                        v-model="form.remark"
                        :error="form.errors?.remark"
                    />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
