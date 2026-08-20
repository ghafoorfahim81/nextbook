<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
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

const openings = computed(() => props.filterOptions?.jobOpenings || []);
const sources = computed(() => props.filterOptions?.sources || []);
const provinces = computed(() => props.filterOptions?.provinces || []);

const blank = () => ({
    job_opening_id: null, selected_opening: null,
    application_number: '',
    full_name: '',
    father_name: '',
    gender: null,
    date_of_birth: null,
    national_id: '',
    phone_number: '',
    email: '',
    address: '',
    province_id: null, selected_province: null,
    current_employer: '',
    current_position: '',
    years_of_experience: null,
    highest_education: '',
    expected_salary: null,
    notice_period_days: null,
    source: 'website', selected_source: null,
    referred_by: '',
    applied_date: '',
    remark: '',
});

const form = useForm(blank());

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            ...item,
            selected_opening: openings.value.find((x) => x.id === item.job_opening_id) ?? null,
            selected_source: sources.value.find((x) => x.id === item.source) ?? null,
            selected_province: provinces.value.find((x) => x.id === item.province_id) ?? null,
        });
    } else {
        form.defaults(blank());
        form.reset();
        form.selected_source = sources.value.find((x) => x.id === 'website') ?? null;
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

// Only asked for when the source is a referral — an always-visible field
// invites someone to fill it in for a walk-in.
const isReferral = computed(() => form.source === 'referral');

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
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.job_application') }),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) await form.put(route('job-applications.update', props.editingItem.id), options);
    else await form.post(route('job-applications.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.job_application') }) : t('general.create', { name: t('hr.job_application') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        width="w-[95vw] max-w-[95vw] sm:w-[900px] sm:max-w-[900px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid grid-cols-1 gap-4 py-2 md:grid-cols-3">
                <div class="md:col-span-2">
                    <NextSelect
                        :options="openings" v-model="form.selected_opening"
                        @update:modelValue="(v) => { form.job_opening_id = v?.id ?? null }"
                        label-key="name" value-key="id" :reduce="(x) => x"
                        :floating-text="t('hr.job_opening')" :error="form.errors?.job_opening_id"
                        append-in-dialog
                    />
                </div>
                <NextInput :label="t('general.number')" v-model="form.application_number" :error="form.errors?.application_number" />

                <NextInput :label="t('hr.full_name')" v-model="form.full_name" :error="form.errors?.full_name" autofocus />
                <NextInput :label="t('hr.father_name')" v-model="form.father_name" :error="form.errors?.father_name" />
                <NextInput :label="t('hr.national_id')" v-model="form.national_id" :error="form.errors?.national_id" />

                <NextInput :label="t('general.phone')" v-model="form.phone_number" :error="form.errors?.phone_number" />
                <NextInput :label="t('general.email')" v-model="form.email" :error="form.errors?.email" />
                <NextDatePicker :label="t('hr.date_of_birth')" v-model="form.date_of_birth" :error="form.errors?.date_of_birth" />

                <NextSelect
                    :options="provinces" v-model="form.selected_province"
                    @update:modelValue="(v) => { form.province_id = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.province')" :error="form.errors?.province_id"
                    append-in-dialog
                />
                <div class="md:col-span-2">
                    <NextInput :label="t('hr.address')" v-model="form.address" :error="form.errors?.address" />
                </div>

                <NextInput :label="t('hr.current_employer')" v-model="form.current_employer" :error="form.errors?.current_employer" />
                <NextInput :label="t('hr.current_position')" v-model="form.current_position" :error="form.errors?.current_position" />
                <NextInput :label="t('hr.experience')" type="number" step="any" v-model="form.years_of_experience" :error="form.errors?.years_of_experience" />

                <NextInput :label="t('hr.highest_education')" v-model="form.highest_education" :error="form.errors?.highest_education" />
                <NextInput :label="t('hr.expected_salary')" type="number" step="any" v-model="form.expected_salary" :error="form.errors?.expected_salary" />
                <NextInput :label="t('hr.notice_period_days')" type="number" v-model="form.notice_period_days" :error="form.errors?.notice_period_days" />

                <NextSelect
                    :options="sources" v-model="form.selected_source"
                    @update:modelValue="(v) => { form.source = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.source')" :error="form.errors?.source"
                    append-in-dialog
                />
                <NextInput v-if="isReferral" :label="t('hr.referred_by')" v-model="form.referred_by" :error="form.errors?.referred_by" />
                <NextDatePicker :label="t('hr.applied_date')" v-model="form.applied_date" :error="form.errors?.applied_date" />

                <div class="md:col-span-3">
                    <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
