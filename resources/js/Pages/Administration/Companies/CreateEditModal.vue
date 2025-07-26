<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from "@/Components/next/NextInput.vue";
import NextSelect from "@/Components/next/NextSelect.vue";

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    branches: {
        type: Array,
        required: true,
    },
    errors: Object,
});

const branches = computed(() => props.branches.data ?? props.branches);
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
    branch_id: '',
});

watch(() => props.editingItem, (item) => {
        console.log('itemssssssssssss', item);
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
            branch_id: item.branch_id ?? '',
        });
    } else {
        form.reset();
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

const handleSubmit = () => {
    if (isEditing.value) {
        form.patch(route('companies.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved');
                form.reset();
                closeModal();
            },
        });
    } else {

        form.post('/companies', {
            onSuccess: () => {
                emit('saved')
                form.reset();
                closeModal()
            },
        })
    }

};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? 'Edit Company' : 'Create Company'"
        :confirmText="isEditing ? 'Update' : 'Create'"
        :closeable="true"
        width="w-[900px] max-w-[900px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid col-span-2 gap-4 py-4">
                <div class="grid items-center grid-cols-3 gap-4">
                    <NextInput label="Name" v-model="form.name" :errors="errors?.name" type="text"/>
                    <NextInput label="Legal Name" v-model="form.legal_name" type="text"/>
                    <NextInput label="Registration Number" v-model="form.registration_number" type="text"/>
                    <NextInput label="Email" v-model="form.email" type="email"/>
                    <NextInput label="Phone" v-model="form.phone" type="text"/>
                    <NextInput label="Website" v-model="form.website" type="text"/>
                    <NextInput label="Industry" v-model="form.industry" type="text"/>
                    <NextInput label="Type" v-model="form.type" type="text"/>
                    <NextInput label="Address" v-model="form.address" type="text"/>
                    <NextInput label="City" v-model="form.city" type="text"/>
                    <NextInput label="Country" v-model="form.country" type="text"/>
                   <div>
                       <Label for="parent_id" class="text-nowrap">Branch</Label>
                       <v-select
                           :options="branches"
                           v-model="form.branch_id"
                           :reduce="branch => branch.id"
                           label="name"
                           class="col-span-3"
                       />
                   </div>
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
