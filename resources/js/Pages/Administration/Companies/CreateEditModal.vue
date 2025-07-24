<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import ModalDialog from '@/Components/next/Dialog.vue';
import Textarea from '@/Components/ui/textarea/textarea.vue';

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
    legal_name: '',
    registration_number: '',
    logo: '',
    email: '',
    phone: '',
    website: '',
    industry: '',
    type: '',
    address: '',
    city: '',
    country: '',
});

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            name: item.name ?? '',
            legal_name: item.legal_name ?? '',
            registration_number: item.registration_number ?? '',
            logo: item.logo ?? '',
            email: item.email ?? '',
            phone: item.phone ?? '',
            website: item.website ?? '',
            industry: item.industry ?? '',
            type: item.type ?? '',
            address: item.address ?? '',
            city: item.city ?? '',
            country: item.country ?? '',
        });
    } else {
        form.reset();
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

const handleSubmit = () => {
    const url = isEditing.value ? route('companies.update', props.editingItem.id) : route('companies.store');
    const method = isEditing.value ? form.patch : form.post;

    method(url, {
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
        },
    });
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? 'Edit Company' : 'Create Company'"
        :confirmText="isEditing ? 'Update' : 'Create'"
        :closeable="true"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid gap-4 py-4">
                <div v-for="field in Object.keys(form)" :key="field" class="grid items-center grid-cols-4 gap-4">
                    <Label :for="field" class="text-nowrap">{{ field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) }}</Label>
                    <Input v-if="field !== 'address'" :id="field" v-model="form[field]" class="col-span-3" :placeholder="`Enter ${field}`" />
                    <Textarea v-else :id="field" v-model="form[field]" rows="2" class="col-span-3" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
