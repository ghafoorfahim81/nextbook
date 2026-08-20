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

const methods = computed(() => props.filterOptions?.accrualMethods || []);
const genders = computed(() => props.filterOptions?.genders || []);

const blank = () => ({
    name: '', code: '', colour: '#22c55e',
    is_paid: true,
    accrual_method: 'annual_grant', selected_method: null,
    days_per_year: 20,
    accrual_rate_per_month: null,
    max_carry_forward_days: null,
    carry_forward_expiry_months: null,
    max_consecutive_days: null,
    min_notice_days: null,
    min_service_months: null,
    applicable_gender: null, selected_gender: null,
    requires_attachment: false,
    requires_approval: true,
    deduct_from_salary: false,
    is_encashable: false,
    pro_rata_on_join: true,
    excludes_holidays: true,
    excludes_weekends: true,
    is_active: true,
    remark: '',
});

const form = useForm(blank());

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            ...item,
            selected_method: methods.value.find((x) => x.id === item.accrual_method) ?? null,
            selected_gender: genders.value.find((x) => x.id === item.applicable_gender) ?? null,
        });
    } else {
        form.defaults(blank());
        form.reset();
        form.selected_method = methods.value.find((x) => x.id === 'annual_grant') ?? null;
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

// Only an automatic accrual needs a yearly figure, and only a monthly accrual
// needs a rate — showing the irrelevant field invites nonsense configuration.
const needsYearlyDays = computed(() => ['annual_grant', 'monthly_accrual'].includes(form.accrual_method));
const needsMonthlyRate = computed(() => form.accrual_method === 'monthly_accrual');

const handleSubmit = async () => {
    const isEdit = isEditing.value;

    form.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([k]) => !k.startsWith('selected_') && k !== 'id' && !k.endsWith('_label'))
    ));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(t('general.success'), {
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.leave_type') }),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) await form.patch(route('leave-types.update', props.editingItem.id), options);
    else await form.post(route('leave-types.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.leave_type') }) : t('general.create', { name: t('hr.leave_type') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        width="w-[95vw] max-w-[95vw] sm:w-[900px] sm:max-w-[900px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid grid-cols-1 gap-4 py-4 md:grid-cols-3">
                <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" autofocus />
                <NextInput :label="t('general.code')" v-model="form.code" :error="form.errors?.code" />
                <div class="flex items-end gap-2">
                    <input type="color" v-model="form.colour" class="h-9 w-12 cursor-pointer rounded border border-border" />
                    <span class="pb-2 text-sm text-muted-foreground">{{ t('hr.colour') }}</span>
                </div>

                <NextSelect
                    :options="methods" v-model="form.selected_method"
                    @update:modelValue="(v) => { form.accrual_method = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.accrual_method')" :error="form.errors?.accrual_method"
                    append-in-dialog
                />
                <NextInput
                    v-if="needsYearlyDays"
                    :label="t('hr.days_per_year')" type="number" step="any"
                    v-model="form.days_per_year" :error="form.errors?.days_per_year"
                />
                <NextInput
                    v-if="needsMonthlyRate"
                    :label="t('hr.accrual_rate_per_month')" type="number" step="any"
                    v-model="form.accrual_rate_per_month" :error="form.errors?.accrual_rate_per_month"
                />

                <NextInput :label="t('hr.max_carry_forward_days')" type="number" step="any" v-model="form.max_carry_forward_days" :error="form.errors?.max_carry_forward_days" />
                <NextInput :label="t('hr.carry_forward_expiry_months')" type="number" v-model="form.carry_forward_expiry_months" :error="form.errors?.carry_forward_expiry_months" />
                <NextInput :label="t('hr.max_consecutive_days')" type="number" v-model="form.max_consecutive_days" :error="form.errors?.max_consecutive_days" />

                <NextInput :label="t('hr.min_notice_days')" type="number" v-model="form.min_notice_days" :error="form.errors?.min_notice_days" />
                <NextInput :label="t('hr.min_service_months')" type="number" v-model="form.min_service_months" :error="form.errors?.min_service_months" />
                <NextSelect
                    :options="genders" v-model="form.selected_gender"
                    @update:modelValue="(v) => { form.applicable_gender = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.applicable_gender')" :error="form.errors?.applicable_gender"
                    append-in-dialog
                />

                <div class="md:col-span-3 grid grid-cols-2 gap-3 rounded-lg border border-border p-3 md:grid-cols-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_paid" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.is_paid') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.requires_approval" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.requires_approval') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.requires_attachment" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.requires_attachment') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.deduct_from_salary" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.deduct_from_salary') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_encashable" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.is_encashable') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.pro_rata_on_join" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.pro_rata_on_join') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.excludes_holidays" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.excludes_holidays') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.excludes_weekends" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.excludes_weekends') }}
                    </label>
                </div>

                <div class="md:col-span-3">
                    <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
