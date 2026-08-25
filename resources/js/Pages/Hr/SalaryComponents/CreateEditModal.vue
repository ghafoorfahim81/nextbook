<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
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
const isSystem = computed(() => !!props.editingItem?.is_system);
const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (v) => localDialogOpen.value = v);
watch(() => localDialogOpen.value, (v) => emit('update:isDialogOpen', v));

const componentTypes = computed(() => props.filterOptions?.componentTypes || []);
const calculationTypes = computed(() => props.filterOptions?.calculationTypes || []);

const blank = () => ({
    name: '',
    code: '',
    component_type: 'earning', selected_type: null,
    calculation_type: 'fixed', selected_calculation: null,
    amount: null,
    percentage: null,
    is_taxable: true,
    affects_gross: true,
    is_prorated: true,
    account_id: null,
    sequence: 10,
    is_active: true,
});

const form = useForm(blank());

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            ...item,
            selected_type: componentTypes.value.find((x) => x.id === item.component_type) ?? null,
            selected_calculation: calculationTypes.value.find((x) => x.id === item.calculation_type) ?? null,
        });
    } else {
        form.defaults(blank());
        form.reset();
        form.selected_type = componentTypes.value.find((x) => x.id === 'earning') ?? null;
        form.selected_calculation = calculationTypes.value.find((x) => x.id === 'fixed') ?? null;
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

// A component is either a fixed amount or a percentage. Showing both invites
// someone to fill in the one that will be ignored.
const isPercentage = computed(() => ['percent_of_basic', 'percent_of_gross'].includes(form.calculation_type));
const isDeduction = computed(() => form.component_type === 'deduction');

const handleSubmit = async () => {
    const isEdit = isEditing.value;

    form.transform((data) => {
        const payload = Object.fromEntries(
            Object.entries(data).filter(([k]) => !k.startsWith('selected_') && k !== 'id' && !k.endsWith('_label'))
        );

        // Clear whichever value the calculation type does not use, so a
        // switched-over component does not carry a stale figure.
        if (isPercentage.value) payload.amount = null;
        else payload.percentage = null;

        return payload;
    });

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(t('general.success'), {
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.salary_component') }),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) await form.put(route('salary-components.update', props.editingItem.id), options);
    else await form.post(route('salary-components.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.salary_component') }) : t('general.create', { name: t('hr.salary_component') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        width="w-[95vw] max-w-[95vw] sm:w-[760px] sm:max-w-[760px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <p v-if="isSystem" class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                {{ t('hr.system_component_notice') }}
            </p>

            <div class="grid grid-cols-1 gap-4 py-2 md:grid-cols-2">
                <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" autofocus />
                <NextInput
                    :label="t('general.code')"
                    v-model="form.code"
                    :error="form.errors?.code"
                    :disabled="isSystem"
                />

                <NextSelect
                    :options="componentTypes" v-model="form.selected_type"
                    @update:modelValue="(v) => { form.component_type = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.component_type')" :error="form.errors?.component_type"
                    :disabled="isSystem"
                    append-in-dialog
                />
                <NextSelect
                    :options="calculationTypes" v-model="form.selected_calculation"
                    @update:modelValue="(v) => { form.calculation_type = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.calculation_type')" :error="form.errors?.calculation_type"
                    append-in-dialog
                />

                <NextInput
                    v-if="!isPercentage"
                    :label="t('hr.amount')" type="number" step="any"
                    v-model="form.amount" :error="form.errors?.amount"
                />
                <NextInput
                    v-else
                    :label="t('hr.percentage')" type="number" step="any"
                    v-model="form.percentage" :error="form.errors?.percentage"
                />

                <NextInput :label="t('hr.sequence')" type="number" v-model="form.sequence" :error="form.errors?.sequence" />

                <div class="md:col-span-2 grid grid-cols-2 gap-3 rounded-lg border border-border p-3 md:grid-cols-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_taxable" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.is_taxable') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.affects_gross" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.affects_gross') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_prorated" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.is_prorated') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('general.active') }}
                    </label>
                </div>

                <p v-if="isDeduction" class="md:col-span-2 text-xs text-muted-foreground">
                    {{ t('hr.deduction_hint') }}
                </p>
            </div>
        </form>
    </ModalDialog>
</template>
