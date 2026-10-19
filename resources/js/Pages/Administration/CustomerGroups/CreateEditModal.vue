<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const props = defineProps({ isDialogOpen: Boolean, editingItem: Object });
const emit = defineEmits(['update:isDialogOpen', 'saved']);
const open = ref(props.isDialogOpen);
const isEditing = computed(() => Boolean(props.editingItem?.id));
const form = useForm({ name_en: '', local_name: '', description: '' });

watch(() => props.isDialogOpen, (value) => { open.value = value; });
watch(() => props.editingItem, (item) => {
    form.reset();
    if (item) form.defaults(item).reset();
}, { immediate: true });
watch(open, (value) => emit('update:isDialogOpen', value));

const submit = () => {
    const options = { onSuccess: () => { emit('saved'); open.value = false; form.reset(); } };
    isEditing.value
        ? form.patch(route('customer-groups.update', props.editingItem.id), options)
        : form.post(route('customer-groups.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="open"
        :title="isEditing ? t('general.edit', { name: t('admin.customer_group.customer_group') }) : t('general.create', { name: t('admin.customer_group.customer_group') })"
        :confirm-text="isEditing ? t('general.update') : t('general.create')"
        :submitting="form.processing"
        @update:open="open = $event"
        @confirm="submit"
    >
        <form class="grid gap-4 py-4" @submit.prevent="submit">
            <NextInput is-required :label="t('admin.customer_group.name_en')" v-model="form.name_en" :error="form.errors.name_en" />
            <NextInput is-required :label="t('admin.customer_group.local_name')" v-model="form.local_name" :error="form.errors.local_name" />
            <NextTextarea :label="t('general.description')" v-model="form.description" :error="form.errors.description" />
        </form>
    </ModalDialog>
</template>
