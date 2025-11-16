<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from "@/Components/next/NextInput.vue";
import NextSelect from "@/Components/next/NextSelect.vue";
import { useI18n } from 'vue-i18n';

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    currencies: {
        type: [Array, Object],
        required: true,
    },
    errors: Object,
});

const { t } = useI18n();
const currencyOptions = computed(() => (props.currencies?.data ?? props.currencies)?.map(c => ({ id: c.id, name: c.name })) ?? []);
const emit = defineEmits(['update:isDialogOpen', 'saved']);

const isEditing = computed(() => !!props.editingItem?.id);
const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (val) => localDialogOpen.value = val);
watch(() => localDialogOpen.value, (val) => emit('update:isDialogOpen', val));

const form = useForm({
    name: '',
    father_name: '',
    nic: '',
    email: '',
    address: '',
    phone_number: '',
    ownership_percentage: 100,
    is_active: true,
    // create only
    amount: null,
    currency_id: '',
    rate: 1,
});

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            name: item.name ?? '',
            father_name: item.father_name ?? '',
            nic: item.nic ?? '',
            email: item.email ?? '',
            address: item.address ?? '',
            phone_number: item.phone_number ?? '',
            ownership_percentage: item.ownership_percentage ?? 100,
            is_active: item.is_active ?? true,
        });
    } else {
        form.reset();
        form.ownership_percentage = 100;
        form.is_active = true;
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

const handleSubmit = () => {
    if (isEditing.value) {
        form.patch(route('owners.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved');
                form.reset();
                closeModal();
            },
        });
    } else {
        form.post('/owners', {
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
        :title="isEditing ? t('general.edit', { name: 'Owner' }) : t('general.create', { name: 'Owner' })"
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
                    <NextInput :label="t('admin.shared.father_name')" v-model="form.father_name" :error="errors?.father_name" />
                    <NextInput label="NIC" v-model="form.nic" :error="errors?.nic" />
                    <NextInput :label="t('admin.shared.email')" v-model="form.email" type="email" :error="errors?.email"/>
                    <NextInput :label="t('admin.shared.phone')" v-model="form.phone_number" type="text" :error="errors?.phone_number"/>
                    <NextInput :label="t('general.address')" v-model="form.address" type="text" :error="errors?.address"/>
                    <NextInput :label="t('admin.shared.percentage')" v-model="form.ownership_percentage" type="number" :error="errors?.ownership_percentage"/>
                    <div class="col-span-1 flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">{{ t('general.active') }}</label>
                        <input type="checkbox" v-model="form.is_active" />
                    </div>
                </div>
                <div v-if="!isEditing" class="grid items-center grid-cols-3 gap-4">
                    <NextInput label="Amount" v-model="form.amount" type="number" :error="errors?.amount"/>
                    <NextSelect :label="t('admin.currency.currency')" v-model="form.currency_id" :items="currencyOptions" :error="errors?.currency_id" />
                    <NextInput label="Rate" v-model="form.rate" type="number" :error="errors?.rate"/>
                </div>
            </div>
        </form>
    </ModalDialog>
</template>


