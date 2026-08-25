<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    open: Boolean,
    devices: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:open']);

const localOpen = ref(props.open);
watch(() => props.open, (v) => localOpen.value = v);
watch(() => localOpen.value, (v) => emit('update:open', v));

const file = ref(null);
const headers = ref([]);
const reading = ref(false);

const form = useForm({
    file: null,
    attendance_device_id: null,
    selected_device: null,
    column_device_user_id: null,
    column_timestamp: null,
    column_direction: null,
    selected_user_col: null,
    selected_time_col: null,
    selected_direction_col: null,
});

const headerOptions = computed(() => headers.value.map((h) => ({ id: h, name: h })));

const onFileChange = (event) => {
    file.value = event.target.files?.[0] ?? null;
    form.file = file.value;
    headers.value = [];
    form.column_device_user_id = null;
    form.column_timestamp = null;
    form.column_direction = null;
};

/**
 * Read the header row before committing, so the user maps real column names
 * rather than guessing what the terminal called them.
 */
const readHeaders = async () => {
    if (!file.value) return;

    reading.value = true;
    try {
        const payload = new FormData();
        payload.append('file', file.value);

        const { data } = await axios.post(route('attendances.import-preview'), payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        headers.value = data.headers || [];

        if (!headers.value.length) {
            toast.error(t('general.error'), { description: t('hr.import_hint'), class: 'bg-red-600' });
            return;
        }

        // Best-effort guesses so the common export shapes need no clicking.
        const guess = (candidates) => headers.value.find((h) => candidates.some((c) => h.includes(c))) ?? null;

        const userCol = guess(['user', 'emp', 'pin', 'id']);
        const timeCol = guess(['time', 'date', 'stamp', 'punch']);
        const dirCol = guess(['status', 'direction', 'type', 'inout']);

        form.column_device_user_id = userCol;
        form.selected_user_col = userCol ? { id: userCol, name: userCol } : null;
        form.column_timestamp = timeCol;
        form.selected_time_col = timeCol ? { id: timeCol, name: timeCol } : null;
        form.column_direction = dirCol;
        form.selected_direction_col = dirCol ? { id: dirCol, name: dirCol } : null;
    } catch (e) {
        toast.error(t('general.error'), { description: e?.response?.data?.message ?? String(e), class: 'bg-red-600' });
    } finally {
        reading.value = false;
    }
};

const canSubmit = computed(() =>
    !!form.file && !!form.column_device_user_id && !!form.column_timestamp
);

const submit = () => {
    if (!canSubmit.value) return;

    form.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([k]) => !k.startsWith('selected_'))
    )).post(route('attendances.import'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            localOpen.value = false;
            form.reset();
            headers.value = [];
            file.value = null;
        },
    });
};
</script>

<template>
    <ModalDialog
        :open="localOpen"
        :title="t('hr.import_punches')"
        :confirm-text="t('hr.import_punches')"
        :cancel-text="t('general.close')"
        width="w-[95vw] max-w-[95vw] sm:w-[720px] sm:max-w-[720px]"
        @update:open="localOpen = $event; emit('update:open', $event)"
        @confirm="submit"
        @cancel="localOpen = false"
        :submitting="form.processing"
    >
        <div class="space-y-4 py-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-foreground">{{ t('hr.import_file') }}</label>
                <input
                    type="file"
                    accept=".csv,.txt,.dat,.tsv"
                    class="block w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                    @change="onFileChange"
                />
                <p class="mt-1 text-xs text-muted-foreground">{{ t('hr.import_hint') }}</p>
                <p v-if="form.errors?.file" class="mt-1 text-xs text-red-500">{{ form.errors.file }}</p>
            </div>

            <NextSelect
                :options="devices"
                v-model="form.selected_device"
                @update:modelValue="(v) => { form.attendance_device_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.device')"
                :error="form.errors?.attendance_device_id"
                append-in-dialog
            />

            <button
                type="button"
                class="rounded-md border border-primary px-3 py-1.5 text-sm text-primary hover:bg-primary hover:text-white disabled:opacity-50"
                :disabled="!file || reading"
                @click="readHeaders"
            >
                {{ t('hr.upload_and_read') }}
            </button>

            <div v-if="headers.length" class="space-y-4 rounded-lg border border-border p-3">
                <p class="text-sm font-medium text-foreground">{{ t('hr.column_mapping') }}</p>

                <NextSelect
                    :options="headerOptions" v-model="form.selected_user_col"
                    @update:modelValue="(v) => { form.column_device_user_id = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.column_device_user_id')"
                    :error="form.errors?.column_device_user_id"
                    append-in-dialog
                />
                <NextSelect
                    :options="headerOptions" v-model="form.selected_time_col"
                    @update:modelValue="(v) => { form.column_timestamp = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.column_timestamp')"
                    :error="form.errors?.column_timestamp"
                    append-in-dialog
                />
                <NextSelect
                    :options="headerOptions" v-model="form.selected_direction_col"
                    @update:modelValue="(v) => { form.column_direction = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.column_direction')"
                    :error="form.errors?.column_direction"
                    append-in-dialog
                />
            </div>
        </div>
    </ModalDialog>
</template>
