<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
const { t } = useI18n();

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);

const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (val) => {
    localDialogOpen.value = val;
});

watch(() => localDialogOpen.value, (val) => {
    emit('update:isDialogOpen', val);
});

const isEditing = computed(() => !!props.editingItem?.id);

const form = useForm({
    name: '',
    remarks: '',
    is_active: true,
});

watch(() => props.editingItem, (item) => {
    if (item) {
        form.name = item.name || '';
        form.remarks = item.remarks || '';
        form.is_active = item.is_active ?? true;
    } else {
        form.reset();
    }
}, { immediate: true });

const closeModal = () => {
    localDialogOpen.value = false;
};

const handleSubmit = async () => {
    const isEdit = isEditing.value;
    const action = isEdit
        ? () => form.patch(route('journal-types.update', props.editingItem.id), submitOptions)
        : () => form.post('/journal-types', submitOptions);

    const submitOptions = {
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(
                t('general.success'),
                {
                    description: t(
                        isEdit ? 'general.update_success' : 'general.create_success',
                        { name: t('sidebar.journal_entry.journal_type') }
                    ),
                    class: 'bg-green-600',
                }
            );
        },
        onError: () => console.error('error', form.errors),
    };

    await action();
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('sidebar.journal_entry.journal_type') }) : t('general.create', { name: t('sidebar.journal_entry.journal_type') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        :submitting="form.processing"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid gap-4 py-4">
                <NextInput 
                    :label="t('general.name')" 
                    :placeholder="t('general.enter', { text: t('general.name') })" 
                    v-model="form.name" 
                    :error="form.errors.name" 
                />
                <NextTextarea
                    v-model="form.remarks"
                    :label="t('general.remarks')"
                    :placeholder="t('general.enter', { text: t('general.remarks') })"
                    :error="form.errors?.remarks"
                /> 
            </div>
        </form>
    </ModalDialog>
</template>

