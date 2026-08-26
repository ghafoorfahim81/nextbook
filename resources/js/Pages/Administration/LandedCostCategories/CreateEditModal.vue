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
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);

const isEditing = computed(() => !!props.editingItem?.id);
const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (val) => localDialogOpen.value = val);
watch(() => localDialogOpen.value, (val) => emit('update:isDialogOpen', val));

const form = useForm({
    name: '',
    remark: '',
});

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            name: item.name ?? '',
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
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(t('general.success'), {
                description: t(
                    isEdit ? 'general.update_success' : 'general.create_success',
                    { name: t('admin.landed_cost_category.landed_cost_category') }
                ),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) {
        await form.patch(route('landed-cost-categories.update', props.editingItem.id), submitOptions);
    } else {
        await form.post(route('landed-cost-categories.store'), submitOptions);
    }
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing
            ? t('general.edit', { name: t('admin.landed_cost_category.landed_cost_category') })
            : t('general.create', { name: t('admin.landed_cost_category.landed_cost_category') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        :closeable="true"
        width="w-[95vw] max-w-[95vw] sm:w-[520px] sm:max-w-[520px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid grid-cols-1 gap-4 py-4">
                <NextInput
                    :label="t('general.name')"
                    :placeholder="t('general.enter', { text: t('general.name') })"
                    v-model="form.name"
                    :error="form.errors?.name"
                    autofocus
                />
                <NextTextarea
                    :label="t('admin.shared.remark')"
                    v-model="form.remark"
                    :error="form.errors?.remark"
                />
            </div>
        </form>
    </ModalDialog>
</template>
