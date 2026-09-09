<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    fiscalYears: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

// One year expanded at a time. Twelve months per year turns into a wall of
// rows otherwise, and the year row is what most visits are actually about.
const expanded = ref(props.fiscalYears.find((year) => year.status === 'open')?.id ?? null);

const toggle = (id) => {
    expanded.value = expanded.value === id ? null : id;
};

const generateForm = useForm({ date: '' });

const generateNext = () => {
    // The day after the latest year ends is, by construction, the first day of
    // the next one — so the user never has to work out the boundary date of a
    // calendar they did not choose.
    const latest = props.fiscalYears[0];

    if (latest?.end_date) {
        const next = new Date(latest.end_date);
        next.setDate(next.getDate() + 1);
        generateForm.date = next.toISOString().slice(0, 10);
    } else {
        generateForm.date = new Date().toISOString().slice(0, 10);
    }

    generateForm.post(route('fiscal-years.store'), { preserveScroll: true });
};

const act = (routeName, id) => {
    router.patch(route(routeName, id), {}, { preserveScroll: true });
};

const statusClass = (status) =>
    status === 'closed'
        ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300'
        : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300';

const openYears = computed(() => props.fiscalYears.filter((y) => y.status === 'open').length);
</script>

<template>
    <AppLayout :title="t('general.fiscal_years')">
        <div class="p-4 space-y-4">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-lg font-semibold">{{ t('general.fiscal_years') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ openYears }} {{ t('general.fiscal_years') }} — {{ t('general.active') }}
                    </p>
                </div>

                <button
                    v-if="can.create"
                    type="button"
                    class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                    :disabled="generateForm.processing"
                    @click="generateNext"
                >
                    {{ t('general.create') }}
                </button>
            </div>

            <p v-if="generateForm.errors.date" class="text-sm text-red-600">
                {{ generateForm.errors.date }}
            </p>

            <div v-if="!fiscalYears.length" class="rounded-md border p-8 text-center text-sm text-muted-foreground">
                {{ t('general.no_records_found') }}
            </div>

            <div v-for="year in fiscalYears" :key="year.id" class="rounded-md border overflow-hidden">
                <div class="flex items-center justify-between gap-3 p-3 bg-muted/40 flex-wrap">
                    <button type="button" class="flex items-center gap-3 text-start" @click="toggle(year.id)">
                        <span class="font-medium">{{ year.name }}</span>
                        <span class="text-sm text-muted-foreground">
                            {{ year.start_date_display }} — {{ year.end_date_display }}
                        </span>
                        <span class="rounded px-2 py-0.5 text-xs" :class="statusClass(year.status)">
                            {{ year.status }}
                        </span>
                    </button>

                    <div class="flex items-center gap-2">
                        <span v-if="year.closed_by" class="text-xs text-muted-foreground">
                            {{ year.closed_by }} · {{ year.closed_at }}
                        </span>
                        <button
                            v-if="year.status === 'open' && can.close"
                            type="button"
                            class="rounded border px-2 py-1 text-xs"
                            @click="act('fiscal-years.close', year.id)"
                        >
                            {{ t('general.close') }}
                        </button>
                        <button
                            v-if="year.status === 'closed' && can.reopen"
                            type="button"
                            class="rounded border px-2 py-1 text-xs"
                            @click="act('fiscal-years.reopen', year.id)"
                        >
                            {{ t('general.reopen') }}
                        </button>
                    </div>
                </div>

                <table v-if="expanded === year.id" class="w-full text-sm">
                    <thead class="text-muted-foreground">
                        <tr class="border-t">
                            <th class="p-2 text-start font-medium">{{ t('general.financial_period') }}</th>
                            <th class="p-2 text-start font-medium">{{ t('general.date') }}</th>
                            <th class="p-2 text-start font-medium">{{ t('general.status') }}</th>
                            <th class="p-2 text-end font-medium">{{ t('general.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="period in year.periods" :key="period.id" class="border-t">
                            <td class="p-2">{{ period.name }}</td>
                            <td class="p-2 text-muted-foreground">
                                {{ period.start_date_display }} — {{ period.end_date_display }}
                            </td>
                            <td class="p-2">
                                <span class="rounded px-2 py-0.5 text-xs" :class="statusClass(period.status)">
                                    {{ period.status }}
                                </span>
                            </td>
                            <td class="p-2 text-end">
                                <button
                                    v-if="period.status === 'open' && can.close"
                                    type="button"
                                    class="rounded border px-2 py-1 text-xs"
                                    @click="act('financial-periods.close', period.id)"
                                >
                                    {{ t('general.close') }}
                                </button>
                                <!-- A month inside a closed year cannot be reopened on its
                                     own; the year is the backstop, so the server refuses
                                     and the button would only produce an error. -->
                                <button
                                    v-else-if="period.status === 'closed' && can.reopen && year.status === 'open'"
                                    type="button"
                                    class="rounded border px-2 py-1 text-xs"
                                    @click="act('financial-periods.reopen', period.id)"
                                >
                                    {{ t('general.reopen') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
