<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
import { Button } from '@/Components/ui/button';
import ImportDialog from './ImportDialog.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Lock, AlertTriangle, Upload } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    roster: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const showImport = ref(false);

// Filter state is separate from the grid form: changing a filter re-fetches
// the roster, it does not submit anything.
const filterState = ref({
    date: props.filters?.date ?? '',
    department_id: props.filters?.department_id ?? null,
    shift_id: props.filters?.shift_id ?? null,
    selected_department: null,
    selected_shift: null,
});

const statuses = computed(() => props.options?.statuses || []);
const departments = computed(() => props.options?.departments || []);
const shifts = computed(() => props.options?.shifts || []);

const form = useForm({
    date: props.filters?.date ?? '',
    shift_id: props.filters?.shift_id ?? null,
    rows: [],
});

// Rebuild the editable rows whenever the server sends a new roster, so a
// filter change never leaves stale edits attached to the wrong employees.
const hydrate = () => {
    form.date = props.roster?.date ?? props.filters?.date ?? '';
    form.rows = (props.roster?.rows || []).map((row) => ({ ...row }));
};

hydrate();
watch(() => props.roster, hydrate, { deep: false });

const reload = () => {
    router.get(route('attendances.roster'), {
        date: filterState.value.date || undefined,
        department_id: filterState.value.department_id || undefined,
        shift_id: filterState.value.shift_id || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const editableRows = computed(() => form.rows.filter((r) => !r.is_locked));
const lockedCount = computed(() => form.rows.filter((r) => r.is_locked).length);
const reviewCount = computed(() => form.rows.filter((r) => r.needs_review).length);

const markAllPresent = () => {
    editableRows.value.forEach((row) => {
        row.status = 'present';
        if (!row.check_in) row.check_in = '08:00';
        if (!row.check_out) row.check_out = '16:00';
    });
};

const clearTimes = (row) => {
    // An absent day carrying times is contradictory, and the server rejects it
    // — so clearing here saves the user a round trip.
    if (['absent', 'on_leave', 'holiday', 'weekend'].includes(row.status)) {
        row.check_in = null;
        row.check_out = null;
    }
};

const submit = () => {
    if (!editableRows.value.length) {
        toast.error(t('general.error'), { description: t('hr.no_employees'), class: 'bg-red-600' });
        return;
    }

    // Locked rows are dropped rather than sent: the server would refuse the
    // whole grid, and the user cannot change them anyway.
    form.transform((data) => ({
        ...data,
        rows: data.rows
            .filter((r) => !r.is_locked)
            .map((r) => ({
                employee_id: r.employee_id,
                status: r.status,
                shift_id: r.shift_id,
                check_in: r.check_in || null,
                check_out: r.check_out || null,
                overtime_hours: r.overtime_hours || 0,
                remark: r.remark || null,
            })),
    })).post(route('attendances.roster.store'), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('general.success'), {
            description: t('hr.roster_saved', { count: editableRows.value.length }),
            class: 'bg-green-600',
        }),
    });
};

const rowError = (index, field) => form.errors?.[`rows.${index}.${field}`];
</script>

<template>
    <AppLayout :title="t('hr.roster')" :sidebar-collapsed="true">
        <FormPageToolbar back-route="attendances.index" module="attendances" />

        <ImportDialog
            :open="showImport"
            :devices="options.devices || []"
            @update:open="showImport = $event"
        />

        <!-- Filters -->
        <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
            <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
                {{ t('hr.roster') }}
            </div>
            <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-4">
                <NextDate
                    v-model="filterState.date"
                    :label="t('hr.date')"
                    show-label
                    @update:modelValue="reload"
                />
                <NextSelect
                    :options="departments"
                    v-model="filterState.selected_department"
                    @update:modelValue="(v) => { filterState.department_id = v?.id ?? null; reload(); }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.department')"
                />
                <NextSelect
                    :options="shifts"
                    v-model="filterState.selected_shift"
                    @update:modelValue="(v) => { filterState.shift_id = v?.id ?? null; form.shift_id = v?.id ?? null; reload(); }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.shift')"
                />
                <div class="flex items-end gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-9" @click="markAllPresent">
                        {{ t('hr.mark_all_present') }}
                    </Button>
                    <Button type="button" variant="outline" size="sm" class="h-9 gap-1" @click="showImport = true">
                        <Upload class="h-4 w-4" />
                        {{ t('hr.import_punches') }}
                    </Button>
                </div>
            </div>

            <div v-if="roster.holiday" class="mt-3 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-300">
                {{ t('hr.holiday') }}: {{ roster.holiday }}
            </div>
        </div>

        <!-- Grid -->
        <form @submit.prevent="submit">
            <div class="rounded-xl border border-violet-500 bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="px-3 py-2 text-start">#</th>
                                <th class="px-3 py-2 text-start">{{ t('general.code') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('general.name') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.department') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('general.status') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.check_in') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.check_out') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.overtime_hours') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('admin.shared.remark') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in form.rows"
                                :key="row.employee_id"
                                class="border-t border-border hover:bg-muted/30"
                                :class="{ 'opacity-60': row.is_locked }"
                            >
                                <td class="px-3 py-2 text-muted-foreground">{{ index + 1 }}</td>
                                <td class="px-3 py-2">{{ row.code }}</td>
                                <td class="px-3 py-2 font-medium">
                                    <div class="flex items-center gap-1.5">
                                        {{ row.full_name }}
                                        <Lock v-if="row.is_locked" class="h-3.5 w-3.5 text-muted-foreground" :title="t('hr.locked')" />
                                        <AlertTriangle v-if="row.needs_review" class="h-3.5 w-3.5 text-amber-500" :title="t('hr.needs_review')" />
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">{{ row.department_name ?? '—' }}</td>

                                <td class="px-3 py-2">
                                    <select
                                        v-model="row.status"
                                        :disabled="row.is_locked"
                                        class="w-32 rounded-md border border-border bg-background px-2 py-1 text-sm disabled:opacity-50"
                                        @change="clearTimes(row)"
                                    >
                                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                    <p v-if="rowError(index, 'status')" class="mt-1 text-xs text-red-500">
                                        {{ rowError(index, 'status') }}
                                    </p>
                                </td>

                                <td class="px-3 py-2">
                                    <input
                                        type="time" v-model="row.check_in" :disabled="row.is_locked"
                                        class="w-28 rounded-md border border-border bg-background px-2 py-1 text-sm disabled:opacity-50"
                                    />
                                    <p v-if="rowError(index, 'check_in')" class="mt-1 text-xs text-red-500">
                                        {{ rowError(index, 'check_in') }}
                                    </p>
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="time" v-model="row.check_out" :disabled="row.is_locked"
                                        class="w-28 rounded-md border border-border bg-background px-2 py-1 text-sm disabled:opacity-50"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="number" step="any" min="0" v-model="row.overtime_hours" :disabled="row.is_locked"
                                        class="w-20 rounded-md border border-border bg-background px-2 py-1 text-sm disabled:opacity-50"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="text" v-model="row.remark" :disabled="row.is_locked"
                                        class="w-48 rounded-md border border-border bg-background px-2 py-1 text-sm disabled:opacity-50"
                                    />
                                </td>
                            </tr>

                            <tr v-if="!form.rows.length">
                                <td colspan="9" class="px-3 py-10 text-center text-muted-foreground">
                                    {{ t('hr.no_employees') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-4">
                <button
                    type="submit"
                    class="rounded-sm bg-primary px-4 py-2 text-white disabled:bg-gray-300"
                    :disabled="form.processing || !editableRows.length"
                >
                    {{ t('hr.save_roster') }}
                </button>

                <p v-if="lockedCount" class="text-sm text-muted-foreground">
                    {{ lockedCount }} × {{ t('hr.locked') }}
                </p>
                <p v-if="reviewCount" class="text-sm text-amber-600 dark:text-amber-400">
                    {{ reviewCount }} × {{ t('hr.needs_review') }}
                </p>
            </div>
        </form>
    </AppLayout>
</template>
