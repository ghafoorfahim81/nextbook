<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);

const isEditing = computed(() => !!props.editingItem?.id);
const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (v) => localDialogOpen.value = v);
watch(() => localDialogOpen.value, (v) => emit('update:isDialogOpen', v));

// ISO-8601 order but rotated so the week reads Saturday-first, which is how
// the Afghan working week is actually laid out. Friday stays visible so the
// rest day is an explicit choice rather than an omission.
const WEEKDAYS = [6, 7, 1, 2, 3, 4, 5];

const blank = () => ({
    name: '',
    code: '',
    start_time: '08:00',
    end_time: '16:00',
    crosses_midnight: false,
    break_minutes: 60,
    grace_in_minutes: 15,
    grace_out_minutes: 0,
    full_day_hours: 8,
    half_day_hours: 4,
    working_days: [6, 7, 1, 2, 3, 4],
    is_default: false,
    is_active: true,
    remark: '',
});

const form = useForm(blank());

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            name: item.name ?? '',
            code: item.code ?? '',
            start_time: item.start_time ?? '08:00',
            end_time: item.end_time ?? '16:00',
            crosses_midnight: !!item.crosses_midnight,
            break_minutes: item.break_minutes ?? 0,
            grace_in_minutes: item.grace_in_minutes ?? 0,
            grace_out_minutes: item.grace_out_minutes ?? 0,
            full_day_hours: item.full_day_hours ?? 8,
            half_day_hours: item.half_day_hours ?? null,
            working_days: [...(item.working_days ?? [])],
            is_default: !!item.is_default,
            is_active: !!item.is_active,
            remark: item.remark ?? '',
        });
    } else {
        form.defaults(blank());
        form.reset();
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

const toggleDay = (day) => {
    const i = form.working_days.indexOf(day);
    if (i === -1) form.working_days.push(day);
    else form.working_days.splice(i, 1);
};

const handleSubmit = async () => {
    const isEdit = isEditing.value;

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(t('general.success'), {
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.shift') }),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) await form.patch(route('shifts.update', props.editingItem.id), options);
    else await form.post(route('shifts.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.shift') }) : t('general.create', { name: t('hr.shift') })"
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
                <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" autofocus />
                <NextInput :label="t('general.code')" v-model="form.code" :error="form.errors?.code" />
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.is_default" class="h-4 w-4 rounded border-border text-primary" />
                    {{ t('hr.is_default') }}
                </label>

                <NextInput :label="t('hr.start_time')" type="time" v-model="form.start_time" :error="form.errors?.start_time" />
                <NextInput :label="t('hr.end_time')" type="time" v-model="form.end_time" :error="form.errors?.end_time" />
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.crosses_midnight" class="h-4 w-4 rounded border-border text-primary" />
                    {{ t('hr.crosses_midnight') }}
                </label>

                <NextInput :label="t('hr.break_minutes')" type="number" v-model="form.break_minutes" :error="form.errors?.break_minutes" />
                <NextInput :label="t('hr.grace_in_minutes')" type="number" v-model="form.grace_in_minutes" :error="form.errors?.grace_in_minutes" />
                <NextInput :label="t('hr.grace_out_minutes')" type="number" v-model="form.grace_out_minutes" :error="form.errors?.grace_out_minutes" />

                <NextInput :label="t('hr.full_day_hours')" type="number" step="any" v-model="form.full_day_hours" :error="form.errors?.full_day_hours" />
                <NextInput :label="t('hr.half_day_hours')" type="number" step="any" v-model="form.half_day_hours" :error="form.errors?.half_day_hours" />

                <div class="md:col-span-3">
                    <p class="mb-2 text-sm font-medium text-foreground">{{ t('hr.working_days') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="day in WEEKDAYS"
                            :key="day"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                            :class="form.working_days.includes(day)
                                ? 'border-violet-500 bg-violet-500 text-white'
                                : 'border-border text-muted-foreground hover:border-violet-500'"
                            @click="toggleDay(day)"
                        >
                            {{ t(`hr.weekday.${day}`) }}
                        </button>
                    </div>
                    <p v-if="form.errors?.working_days" class="mt-1 text-xs text-red-500">
                        {{ form.errors.working_days }}
                    </p>
                </div>

                <div class="md:col-span-3">
                    <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
