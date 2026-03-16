<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    errors: Object,
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
    address: '',
});

watch(() => props.editingItem, (item) => {
    if (item) {
        form.name = item.name || '';
        form.address = item.address || '';
    } else {
        form.reset();
    }
}, { immediate: true });

const closeModal = () => {
    localDialogOpen.value = false;
};

const submitOptions = (isEdit) => ({
    onSuccess: () => {
        emit('saved');
        form.reset();
        closeModal();
        toast.success(t('general.success'), {
            description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('admin.warehouse.warehouse') }),
            class: 'bg-green-600',
        });
    },
    onError: () => console.error('error', form.errors),
});

const handleSubmit = async () => {
    const isEdit = isEditing.value;
    if (isEdit) {
        await form.patch(route('warehouses.update', props.editingItem.id), submitOptions(true));
        return;
    }

    await form.post('/warehouses', submitOptions(false));
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('admin.warehouse.warehouse') }) : t('general.create', { name: t('admin.warehouse.warehouse') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        @confirm="handleSubmit"
        :submitting="form.processing"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit">
            <div class="grid gap-4 py-4">
                <NextInput
                    :label="t('general.name')"
                    v-model="form.name"
                    :error="form.errors?.name"
                    :placeholder="t('general.enter', { text: t('general.name') })"
                />
                <NextTextarea
                    :label="t('admin.shared.address')"
                    v-model="form.address"
                    :error="form.errors?.address"
                    :placeholder="t('general.enter', { text: t('admin.shared.address') })"
                />
            </div>
        </form>
    </ModalDialog>
</template>

