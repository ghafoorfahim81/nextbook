<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from "@/Components/next/NextInput.vue";
import NextSelect from "@/Components/next/NextSelect.vue";
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
        form.patch(route('brands.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved');
                form.reset();
                closeModal();
            },
        });
    } else {

        form.post('/brands', {
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
        :title="isEditing ? t('general.edit', { name: t('admin.brand.brand') }) : t('general.create', { name: t('admin.brand.brand') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        :closeable="true"
        width="w-[900px] max-w-[900px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid col-span-2 gap-4 py-4">
                <div class="grid items-center grid-cols-3 gap-4">
                    <NextInput :label="t('general.name')" :placeholder="t('general.enter', { text: t('general.name') })" v-model="form.name" :error="form.errors?.name"  />
                    <NextInput :label="t('admin.brand.legal_name')" :placeholder="t('general.enter', { text: t('admin.brand.legal_name') })" v-model="form.legal_name" :error="form.errors?.legal_name"/>
                    <NextInput :label="t('admin.brand.registration_number')" :placeholder="t('general.enter', { text: t('admin.brand.registration_number') })" v-model="form.registration_number" :error="form.errors?.registration_number"/>
                    <NextInput :label="t('admin.shared.email')" :placeholder="t('general.enter', { text: t('admin.shared.email') })" v-model="form.email" type="email" :error="form.errors?.email"/>
                    <NextInput :label="t('admin.shared.phone')" :placeholder="t('general.enter', { text: t('admin.shared.phone') })" v-model="form.phone" :error="form.errors?.phone"/>
                    <NextInput :label="t('admin.brand.website')" :placeholder="t('general.enter', { text: t('admin.brand.website') })" v-model="form.website" :error="form.errors?.website"/>
                    <NextInput :label="t('admin.brand.industry')" :placeholder="t('general.enter', { text: t('admin.brand.industry') })" v-model="form.industry" :error="form.errors?.industry"/>
                    <NextInput :label="t('admin.brand.type')" :placeholder="t('general.enter', { text: t('admin.brand.type') })" v-model="form.type" :error="form.errors?.type"/>
                    <NextInput :label="t('admin.brand.address')" :placeholder="t('general.enter', { text: t('admin.brand.address') })" v-model="form.address" :error="form.errors?.address"/>
                    <NextInput :label="t('admin.brand.city')" :placeholder="t('general.enter', { text: t('admin.brand.city') })" v-model="form.city" :error="form.errors?.city"/>
                    <NextInput :label="t('admin.shared.country')" :placeholder="t('general.enter', { text: t('admin.shared.country') })" v-model="form.country" :error="form.errors?.country"/>

                </div>
            </div>
        </form>
    </ModalDialog>
</template>
