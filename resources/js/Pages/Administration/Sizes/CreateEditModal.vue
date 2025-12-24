<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from "@/Components/next/NextInput.vue";
import { useI18n } from 'vue-i18n';

const { t } = useI18n()

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    branches: {
        type: Array,
        required: true,
    },
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

const handleSubmit = () => {
    if (isEditing.value) {
        form.patch(route('sizes.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved');
                form.reset();
                closeModal();
            },
        });
    } else {
        form.post('/sizes', {
            onSuccess: () => {
                emit('saved')
                form.reset();
                closeModal()
            },
            onError: () => {
                console.log('error', form.errors);
            },
        })
    }
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('admin.size.size') }) : t('general.create', { name: t('admin.size.size') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :onConfirm="handleSubmit"
        :onCancel="closeModal"
        :loading="form.processing"
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
