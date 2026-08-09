<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';

const props = defineProps({ isDialogOpen: Boolean, editingItem: Object });
const emit = defineEmits(['update:isDialogOpen', 'saved']);
const open = ref(props.isDialogOpen);
const isEditing = computed(() => Boolean(props.editingItem?.id));
const form = useForm({ name_en: '', name_fa: '', description: '' });

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
    <ModalDialog :open="open" :title="isEditing ? 'Edit Customer Group' : 'New Customer Group'" :confirm-text="isEditing ? 'Update' : 'Create'" :submitting="form.processing" @update:open="open = $event" @confirm="submit">
        <form class="grid gap-4 py-4" @submit.prevent="submit">
            <NextInput is-required label="Name (English)" v-model="form.name_en" :error="form.errors.name_en" />
            <NextInput is-required label="نام فارسی" v-model="form.name_fa" :error="form.errors.name_fa" />
            <NextTextarea label="Description" v-model="form.description" :error="form.errors.description" />
        </form>
    </ModalDialog>
</template>
