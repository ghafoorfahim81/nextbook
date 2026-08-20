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

const types = computed(() => props.filterOptions?.holidayTypes || []);

const blank = () => ({
    name: '',
    date: '',
    end_date: '',
    holiday_type: 'public',
    selected_type: null,
    is_recurring: false,
    is_paid: true,
    remark: '',
});

const form = useForm(blank());

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            name: item.name ?? '',
            date: item.date ?? '',
            end_date: item.end_date ?? '',
            holiday_type: item.holiday_type ?? 'public',
            selected_type: types.value.find((x) => x.id === item.holiday_type) ?? null,
            is_recurring: !!item.is_recurring,
            is_paid: !!item.is_paid,
            remark: item.remark ?? '',
        });
    } else {
        form.defaults(blank());
        form.reset();
        form.selected_type = types.value.find((x) => x.id === 'public') ?? null;
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
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.holiday') }),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) await form.patch(route('holidays.update', props.editingItem.id), options);
    else await form.post(route('holidays.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.holiday') }) : t('general.create', { name: t('hr.holiday') })"
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
                <div class="md:col-span-2">
                    <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" autofocus />
                </div>

                <NextDate v-model="form.date" :label="t('hr.date')" :error="form.errors?.date" show-label />
                <!-- Optional: a multi-day holiday like Eid is one record with a
                     range, not several rows. -->
                <NextDate v-model="form.end_date" :label="t('hr.end_date')" :error="form.errors?.end_date" show-label />

                <NextSelect
                    :options="types"
                    v-model="form.selected_type"
                    @update:modelValue="(v) => { form.holiday_type = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.holiday_type')"
                    :error="form.errors?.holiday_type"
                    append-in-dialog
                />

                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_paid" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.is_paid') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_recurring" class="h-4 w-4 rounded border-border text-primary" />
                        {{ t('hr.is_recurring') }}
                    </label>
                </div>

                <div class="md:col-span-2">
                    <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
