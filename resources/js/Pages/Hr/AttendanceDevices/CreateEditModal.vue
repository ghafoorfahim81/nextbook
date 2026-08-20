<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    filterOptions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);

const isEditing = computed(() => !!props.editingItem?.id);
const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (v) => localDialogOpen.value = v);
watch(() => localDialogOpen.value, (v) => emit('update:isDialogOpen', v));

const deviceTypes = computed(() => props.filterOptions?.deviceTypes || []);

const blank = () => ({
    name: '', code: '',
    device_type: 'zkteco', selected_type: null,
    serial_number: '', location: '', ip_address: '',
    is_active: true, remark: '',
});

const form = useForm(blank());

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            name: item.name ?? '',
            code: item.code ?? '',
            device_type: item.device_type ?? 'zkteco',
            selected_type: deviceTypes.value.find((x) => x.id === item.device_type) ?? null,
            serial_number: item.serial_number ?? '',
            location: item.location ?? '',
            ip_address: item.ip_address ?? '',
            is_active: !!item.is_active,
            remark: item.remark ?? '',
        });
    } else {
        form.defaults(blank());
        form.reset();
        form.selected_type = deviceTypes.value.find((x) => x.id === 'zkteco') ?? null;
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

const handleSubmit = async () => {
    const isEdit = isEditing.value;

    form.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([k]) => !k.startsWith('selected_'))
    ));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(t('general.success'), {
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.device') }),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) await form.patch(route('attendance-devices.update', props.editingItem.id), options);
    else await form.post(route('attendance-devices.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.device') }) : t('general.create', { name: t('hr.device') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        width="w-[95vw] max-w-[95vw] sm:w-[720px] sm:max-w-[720px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid grid-cols-1 gap-4 py-4 md:grid-cols-2">
                <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" autofocus />
                <NextInput :label="t('general.code')" v-model="form.code" :error="form.errors?.code" />

                <NextSelect
                    :options="deviceTypes" v-model="form.selected_type"
                    @update:modelValue="(v) => { form.device_type = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.device')" :error="form.errors?.device_type"
                    append-in-dialog
                />
                <NextInput :label="t('hr.device_user_id')" v-model="form.serial_number" :error="form.errors?.serial_number" />

                <NextInput :label="t('admin.brand.address')" v-model="form.location" :error="form.errors?.location" />
                <NextInput label="IP" v-model="form.ip_address" :error="form.errors?.ip_address" />

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-border text-primary" />
                    {{ t('general.active') }}
                </label>

                <div class="md:col-span-2">
                    <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
