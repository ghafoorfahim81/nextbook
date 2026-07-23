<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';

const props = defineProps({ isDialogOpen: Boolean, editingItem: Object });
const emit = defineEmits(['update:isDialogOpen', 'saved']);
const open = ref(props.isDialogOpen);
const isEditing = computed(() => Boolean(props.editingItem?.id));
const form = useForm({ name: '', days: 0, type: '' });

watch(() => props.isDialogOpen, (value) => { open.value = value; });
watch(() => props.editingItem, (item) => {
    form.reset();
    if (item) form.defaults(item).reset();
}, { immediate: true });
watch(open, (value) => emit('update:isDialogOpen', value));

const submit = () => {
    const options = { onSuccess: () => { emit('saved'); open.value = false; form.reset(); } };
    isEditing.value
        ? form.patch(route('payment-terms.update', props.editingItem.id), options)
        : form.post(route('payment-terms.store'), options);
};
</script>

<template>
    <ModalDialog :open="open" :title="isEditing ? 'Edit Payment Term' : 'New Payment Term'" :confirm-text="isEditing ? 'Update' : 'Create'" :submitting="form.processing" @update:open="open = $event" @confirm="submit">
        <form class="grid gap-4 py-4" @submit.prevent="submit">
            <NextInput is-required label="Name" v-model="form.name" :error="form.errors.name" />
            <NextInput is-required type="number" min="0" label="Days" v-model="form.days" :error="form.errors.days" />
            <NextInput is-required label="Type" v-model="form.type" :error="form.errors.type" />
        </form>
    </ModalDialog>
</template>
