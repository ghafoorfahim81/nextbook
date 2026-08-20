<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
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

const leaveTypes = computed(() => props.filterOptions?.leaveTypes || []);

const blank = () => ({
    employee_id: null, selected_employee: null,
    leave_type_id: null, selected_leave_type: null,
    period_start: '', period_end: '',
    entitled_days: 20,
    carried_forward_days: 0,
    adjustment_days: 0,
    encashed_days: 0,
    expired_days: 0,
    source: 'manual',
    remark: '',
});

const form = useForm(blank());

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            employee_id: item.employee_id,
            selected_employee: item.employee_id ? { id: item.employee_id, name: item.employee_name } : null,
            leave_type_id: item.leave_type_id,
            selected_leave_type: leaveTypes.value.find((x) => x.id === item.leave_type_id) ?? null,
            period_start: item.period_start ?? '',
            period_end: item.period_end ?? '',
            entitled_days: item.entitled_days ?? 0,
            carried_forward_days: item.carried_forward_days ?? 0,
            adjustment_days: item.adjustment_days ?? 0,
            encashed_days: item.encashed_days ?? 0,
            expired_days: item.expired_days ?? 0,
            source: item.source ?? 'manual',
            remark: item.remark ?? '',
        });
    } else {
        form.defaults(blank());
        form.reset();
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
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.leave_allocation') }),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) await form.patch(route('leave-allocations.update', props.editingItem.id), options);
    else await form.post(route('leave-allocations.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.leave_allocation') }) : t('general.create', { name: t('hr.leave_allocation') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        width="w-[95vw] max-w-[95vw] sm:w-[820px] sm:max-w-[820px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid grid-cols-1 gap-4 py-4 md:grid-cols-3">
                <NextSelect
                    :options="[]" v-model="form.selected_employee"
                    @update:modelValue="(v) => { form.employee_id = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :searchable="true" resource-type="employees" :search-fields="['full_name', 'code']"
                    :floating-text="t('hr.employee')" :error="form.errors?.employee_id"
                    append-in-dialog
                />
                <NextSelect
                    :options="leaveTypes" v-model="form.selected_leave_type"
                    @update:modelValue="(v) => { form.leave_type_id = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.leave_type')" :error="form.errors?.leave_type_id"
                    append-in-dialog
                />
                <NextInput
                    :label="t('hr.entitled_days')" type="number" step="any"
                    v-model="form.entitled_days" :error="form.errors?.entitled_days"
                />

                <NextDate v-model="form.period_start" :label="t('hr.period_start')" show-label :error="form.errors?.period_start" />
                <NextDate v-model="form.period_end" :label="t('hr.period_end')" show-label :error="form.errors?.period_end" />
                <NextInput
                    :label="t('hr.carried_forward_days')" type="number" step="any"
                    v-model="form.carried_forward_days" :error="form.errors?.carried_forward_days"
                />

                <!-- Negative is allowed: an adjustment is how a correction is
                     recorded, and corrections go both ways. -->
                <NextInput
                    :label="t('hr.adjustment_days')" type="number" step="any"
                    v-model="form.adjustment_days" :error="form.errors?.adjustment_days"
                />
                <NextInput
                    :label="t('hr.encashed_days')" type="number" step="any"
                    v-model="form.encashed_days" :error="form.errors?.encashed_days"
                />
                <NextInput
                    :label="t('hr.expired_days')" type="number" step="any"
                    v-model="form.expired_days" :error="form.errors?.expired_days"
                />

                <div class="md:col-span-3">
                    <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
