<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from "@/Components/next/NextInput.vue";
import NextSelect from "@/Components/next/NextSelect.vue";
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    errors: Object,
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);

const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (val) => {
    localDialogOpen.value = val;
});

watch(() => localDialogOpen.value, (val) => {
    emit('update:isDialogOpen', val);
});

const isEditing = computed(() => !!props.editingItem?.id);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    branch_id: null,
    company_id: null,
    roles: [],
});

watch(() => props.editingItem, (item) => {
    if (item) {
        form.name = item.name || '';
        form.email = item.email || '';
        form.password = '';
        form.password_confirmation = '';
        form.branch_id = item.branch_id || null;
        form.company_id = item.company_id || null;
        form.roles = item.roles?.map(r => r.id) || [];
    } else {
        form.reset();
    }
}, { immediate: true });

const closeModal = () => {
    localDialogOpen.value = false;
};

const handleSubmit = async () => {
    if (isEditing.value) {
        form.patch(route('users.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved');
                form.reset();
                closeModal();
            },
        });
    } else {
        form.post('/users', {
            onSuccess: () => {
                emit('saved');
                form.reset();
                closeModal();
            },
        });
    }
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit') + ' User' : t('general.create') + ' User'"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        width="w-[800px] max-w-[800px]"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :cancel-text="t('general.close')"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid col-span-2 gap-4 py-4">
                <div class="grid items-center grid-cols-2 gap-4">
                    <NextInput 
                        :label="t('general.name')" 
                        :placeholder="t('general.enter', { text: t('general.name') })" 
                        v-model="form.name" 
                        :error="form.errors?.name"
                    />
                    <NextInput 
                        label="Email" 
                        type="email"
                        placeholder="Enter Email" 
                        v-model="form.email" 
                        :error="form.errors?.email"
                    />
                    <NextInput 
                        label="Password" 
                        type="password"
                        :placeholder="isEditing ? 'Leave blank to keep current' : 'Enter Password'" 
                        v-model="form.password" 
                        :error="form.errors?.password"
                    />
                    <NextInput 
                        label="Confirm Password" 
                        type="password"
                        placeholder="Confirm Password" 
                        v-model="form.password_confirmation" 
                        :error="form.errors?.password_confirmation"
                    />
                    <NextSelect
                        v-model="form.company_id"
                        :options="[]"
                        label-key="name"
                        value-key="id"
                        id="company"
                        :floating-text="'Company'"
                        :error="form.errors?.company_id"
                        :searchable="true"
                        resource-type="companies"
                        :search-fields="['name']"
                    />
                    <NextSelect
                        v-model="form.branch_id"
                        :options="[]"
                        label-key="name"
                        value-key="id"
                        id="branch"
                        :floating-text="'Branch'"
                        :error="form.errors?.branch_id"
                        :searchable="true"
                        resource-type="branches"
                        :search-fields="['name']"
                    />
                    <NextSelect
                        v-model="form.roles"
                        :options="[]"
                        label-key="name"
                        value-key="id"
                        id="roles"
                        :floating-text="'Roles'"
                        :error="form.errors?.roles"
                        :searchable="true"
                        :multiple="true"
                        resource-type="roles"
                        :search-fields="['name']"
                    />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>

