<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from "@/Components/next/NextInput.vue";
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner'
const { t } = useI18n()

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    errors: Object,
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);

const isEditing = computed(() => !!props.editingItem?.id);
const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (val) => localDialogOpen.value = val);
watch(() => localDialogOpen.value, (val) => emit('update:isDialogOpen', val));

const form = useForm({
    name: '',
    code: '',
});

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            name: item.name ?? '',
            code: item.code ?? '',
        });
    } else {
        form.reset();
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

const handleSubmit = async () => {
    const isEdit = isEditing.value;
    const action = isEdit
        ? () => form.patch(route('sizes.update', props.editingItem.id), submitOptions)
        : () => form.post('/sizes', submitOptions);

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
                        { name: t('admin.size.size') }
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
        :title="isEditing ? t('general.edit', { name: t('admin.size.size') }) : t('general.create', { name: t('admin.size.size') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :onConfirm="handleSubmit"
        :onCancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" class="space-y-4">
            <NextInput
                v-model="form.name"
                :label="t('general.name')"
                :error="form.errors.name"
                required
            />
            <NextInput
                v-model="form.code"
                :label="t('admin.currency.code')"
                :error="form.errors.code"
                required
            />
        </form>
    </ModalDialog>
</template>
