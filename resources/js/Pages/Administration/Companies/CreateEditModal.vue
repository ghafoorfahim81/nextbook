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
        :title="isEditing ? t('general.edit', { name: t('admin.company.company') }) : t('general.create', { name: t('admin.company.company') })"  
        :cancel-text="t('general.close')"
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
                    <NextInput :label="t('general.name')" v-model="form.name" :error="errors?.name" />
                    <NextInput :label="t('admin.company.legal_name')" v-model="form.legal_name" type="text" :error="errors?.legal_name"/>
                    <NextInput :label="t('admin.company.registration_number')" v-model="form.registration_number" type="text" :error="errors?.registration_number"/>
                    <NextInput :label="t('admin.shared.email')" v-model="form.email" type="email" :error="errors?.email"/>
                    <NextInput :label="t('admin.shared.phone')" v-model="form.phone" type="text" :error="errors?.phone"/>
                    <NextInput :label="t('admin.company.website')" v-model="form.website" type="text"/>
                    <NextInput :label="t('admin.company.industry')" v-model="form.industry" type="text"/>
                    <NextInput :label="t('admin.company.type')" v-model="form.type" type="text"/>
                    <NextInput :label="t('admin.company.address')" v-model="form.address" type="text"/>
                    <NextInput :label="t('admin.company.city')" v-model="form.city" type="text"/>
                    <NextInput :label="t('admin.shared.country')" v-model="form.country" type="text"/>

                </div>
            </div>
        </form>
    </ModalDialog>
</template>
