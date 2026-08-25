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

const departments = computed(() => props.filterOptions?.departments || []);
const designations = computed(() => props.filterOptions?.designations || []);
const currencies = computed(() => props.filterOptions?.currencies || []);
const employmentTypes = computed(() => props.filterOptions?.employmentTypes || []);

const blank = () => ({
    code: '',
    title: '',
    department_id: null, selected_department: null,
    designation_id: null, selected_designation: null,
    employment_type: 'permanent', selected_employment_type: null,
    vacancies: 1,
    description: '',
    requirements: '',
    responsibilities: '',
    min_salary: null,
    max_salary: null,
    currency_id: null, selected_currency: null,
    location: '',
    posted_date: '',
    closing_date: '',
    hiring_manager_id: null, selected_manager: null,
    remark: '',
});

const form = useForm(blank());

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            ...item,
            selected_department: departments.value.find((x) => x.id === item.department_id) ?? null,
            selected_designation: designations.value.find((x) => x.id === item.designation_id) ?? null,
            selected_employment_type: employmentTypes.value.find((x) => x.id === item.employment_type) ?? null,
            selected_currency: currencies.value.find((x) => x.id === item.currency_id) ?? null,
            selected_manager: item.hiring_manager_id
                ? { id: item.hiring_manager_id, name: item.hiring_manager_name }
                : null,
        });
    } else {
        form.defaults(blank());
        form.reset();
        form.selected_employment_type = employmentTypes.value.find((x) => x.id === 'permanent') ?? null;
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

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
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.job_opening') }),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) await form.put(route('job-openings.update', props.editingItem.id), options);
    else await form.post(route('job-openings.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.job_opening') }) : t('general.create', { name: t('hr.job_opening') })"
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
                <NextInput :label="t('general.code')" v-model="form.code" :error="form.errors?.code" autofocus />
                <div class="md:col-span-2">
                    <NextInput :label="t('hr.job_title')" v-model="form.title" :error="form.errors?.title" />
                </div>

                <NextSelect
                    :options="departments" v-model="form.selected_department"
                    @update:modelValue="(v) => { form.department_id = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.department')" :error="form.errors?.department_id"
                    append-in-dialog
                />
                <NextSelect
                    :options="designations" v-model="form.selected_designation"
                    @update:modelValue="(v) => { form.designation_id = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.designation')" :error="form.errors?.designation_id"
                    append-in-dialog
                />
                <NextSelect
                    :options="employmentTypes" v-model="form.selected_employment_type"
                    @update:modelValue="(v) => { form.employment_type = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.employment_type')" :error="form.errors?.employment_type"
                    append-in-dialog
                />

                <NextInput :label="t('hr.vacancies')" type="number" v-model="form.vacancies" :error="form.errors?.vacancies" />
                <NextInput :label="t('hr.location')" v-model="form.location" :error="form.errors?.location" />
                <NextSelect
                    v-model="form.selected_manager"
                    @update:modelValue="(v) => { form.hiring_manager_id = v?.id ?? null }"
                    resource-type="employees"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.hiring_manager')" :error="form.errors?.hiring_manager_id"
                    append-in-dialog
                />

                <NextInput :label="t('hr.min_salary')" type="number" step="any" v-model="form.min_salary" :error="form.errors?.min_salary" />
                <NextInput :label="t('hr.max_salary')" type="number" step="any" v-model="form.max_salary" :error="form.errors?.max_salary" />
                <NextSelect
                    :options="currencies" v-model="form.selected_currency"
                    @update:modelValue="(v) => { form.currency_id = v?.id ?? null }"
                    label-key="code" value-key="id" :reduce="(x) => x"
                    :floating-text="t('general.currency')" :error="form.errors?.currency_id"
                    append-in-dialog
                />

                <NextDatePicker :label="t('hr.posted_date')" v-model="form.posted_date" :error="form.errors?.posted_date" />
                <NextDatePicker :label="t('hr.closing_date')" v-model="form.closing_date" :error="form.errors?.closing_date" />
                <div></div>

                <div class="md:col-span-3">
                    <NextTextarea :label="t('hr.description')" v-model="form.description" :error="form.errors?.description" />
                </div>
                <div class="md:col-span-3">
                    <NextTextarea :label="t('hr.requirements')" v-model="form.requirements" :error="form.errors?.requirements" />
                </div>
                <div class="md:col-span-3">
                    <NextTextarea :label="t('hr.responsibilities')" v-model="form.responsibilities" :error="form.errors?.responsibilities" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
