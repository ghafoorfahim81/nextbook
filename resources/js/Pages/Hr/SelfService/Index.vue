<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { Button } from '@/Components/ui/button';
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { LogIn, LogOut, AlertTriangle } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    employee: { type: Object, required: true },
    today: { type: Object, default: null },
    history: { type: Object, default: () => ({ data: [] }) },
    balances: { type: Array, default: () => [] },
    leaveRequests: { type: Object, default: () => ({ data: [] }) },
    canCheckIn: { type: Boolean, default: true },
    canCheckOut: { type: Boolean, default: false },
});

const busy = ref(false);
const locating = ref(false);

const todayRow = computed(() => props.today?.data ?? props.today);
const historyRows = computed(() => props.history?.data ?? props.history ?? []);
const leaveRows = computed(() => props.leaveRequests?.data ?? props.leaveRequests ?? []);

const statusClasses = (status) => {
    switch (status) {
        case 'present':
        case 'remote':
        case 'mission':
            return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'late':
        case 'half_day':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'absent':
            return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        case 'on_leave':
            return 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300';
        default:
            return 'border-border bg-muted text-foreground';
    }
};

/**
 * Location is requested but never required by the browser flow — the server
 * decides whether it is mandatory, based on whether the branch enforces a
 * geofence. Failing to get a fix must not block a punch at a site that does
 * not use one.
 */
const currentPosition = () => new Promise((resolve) => {
    if (!navigator.geolocation) {
        resolve({});
        return;
    }

    locating.value = true;

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            locating.value = false;
            resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude });
        },
        () => {
            locating.value = false;
            resolve({});
        },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 },
    );
});

const punch = async (action) => {
    busy.value = true;

    const coords = await currentPosition();

    router.post(route(`self-service.${action}`), coords, {
        preserveScroll: true,
        onError: (errors) => {
            const first = Object.values(errors)[0];
            if (first) toast.error(t('general.error'), { description: first, class: 'bg-red-600' });
        },
        onFinish: () => { busy.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="t('hr.my_attendance')">
        <!-- Today -->
        <div class="mb-5 rounded-xl border border-border bg-card p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <img
                        v-if="employee.photo_url"
                        :src="employee.photo_url" :alt="employee.full_name"
                        class="h-14 w-14 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-14 w-14 items-center justify-center rounded-full bg-violet-500 text-lg font-semibold text-white"
                    >
                        {{ (employee.full_name || '?').slice(0, 1) }}
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-foreground">{{ employee.full_name }}</h1>
                        <p class="text-sm text-muted-foreground">
                            {{ employee.code }}
                            <span v-if="employee.designation_name"> · {{ employee.designation_name }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div v-if="todayRow" class="text-end text-sm">
                        <p v-if="todayRow.check_in" class="text-foreground">
                            {{ t('hr.checked_in_at', { time: todayRow.check_in }) }}
                        </p>
                        <p v-if="todayRow.check_out" class="text-muted-foreground">
                            {{ t('hr.checked_out_at', { time: todayRow.check_out }) }}
                        </p>
                        <p v-if="!todayRow.check_in" class="text-muted-foreground">{{ t('hr.not_checked_in') }}</p>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">{{ t('hr.not_checked_in') }}</p>

                    <Button
                        v-if="canCheckIn"
                        class="h-10 gap-2 bg-green-600 text-white hover:bg-green-700"
                        :disabled="busy"
                        @click="punch('check-in')"
                    >
                        <LogIn class="h-4 w-4" />
                        {{ t('hr.check_in') }}
                    </Button>

                    <Button
                        v-if="canCheckOut"
                        variant="outline"
                        class="h-10 gap-2 border-primary text-primary hover:bg-primary hover:text-white"
                        :disabled="busy"
                        @click="punch('check-out')"
                    >
                        <LogOut class="h-4 w-4" />
                        {{ t('hr.check_out') }}
                    </Button>
                </div>
            </div>

            <p v-if="locating" class="mt-3 text-xs text-muted-foreground">{{ t('hr.locating') }}</p>

            <div
                v-if="todayRow?.needs_review"
                class="mt-3 flex items-center gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-300"
            >
                <AlertTriangle class="h-4 w-4" />
                {{ t('hr.needs_review') }}
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <!-- History -->
            <div class="rounded-xl border border-border bg-card shadow-sm lg:col-span-2">
                <div class="border-b border-border px-4 py-3">
                    <h2 class="text-sm font-semibold text-foreground">{{ t('hr.last_30_days') }}</h2>
                </div>
                <div class="max-h-[26rem] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-muted/50">
                            <tr>
                                <th class="px-4 py-2 text-start">{{ t('hr.date') }}</th>
                                <th class="px-4 py-2 text-start">{{ t('general.status') }}</th>
                                <th class="px-4 py-2 text-start">{{ t('hr.check_in') }}</th>
                                <th class="px-4 py-2 text-start">{{ t('hr.check_out') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.worked_hours') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in historyRows" :key="row.id" class="border-t border-border hover:bg-muted/30">
                                <td class="px-4 py-2">{{ row.date }}</td>
                                <td class="px-4 py-2">
                                    <span class="rounded-full border px-2 py-0.5 text-xs" :class="statusClasses(row.status)">
                                        {{ row.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">{{ row.check_in ?? '—' }}</td>
                                <td class="px-4 py-2">{{ row.check_out ?? '—' }}</td>
                                <td class="px-4 py-2 text-end">{{ row.worked_hours }}</td>
                            </tr>
                            <tr v-if="!historyRows.length">
                                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                    {{ t('general.no_record_available') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Balances + recent leave -->
            <div class="space-y-5">
                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('hr.my_balances') }}</h2>

                    <div v-if="balances.length" class="space-y-3">
                        <div v-for="b in balances" :key="b.allocation_id">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-1.5">
                                    <span
                                        v-if="b.leave_type_colour"
                                        class="inline-block h-2.5 w-2.5 rounded-full"
                                        :style="{ backgroundColor: b.leave_type_colour }"
                                    />
                                    {{ b.leave_type_name }}
                                </span>
                                <span class="font-semibold" :class="b.available < 0 ? 'text-red-600 dark:text-red-400' : ''">
                                    {{ b.available }} / {{ b.granted }}
                                </span>
                            </div>
                            <p v-if="b.pending > 0" class="text-xs text-amber-600 dark:text-amber-400">
                                {{ b.pending }} {{ t('hr.pending_days') }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">{{ t('general.no_record_available') }}</p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('hr.my_leave') }}</h2>

                    <ul v-if="leaveRows.length" class="space-y-2 text-sm">
                        <li v-for="lr in leaveRows" :key="lr.id" class="flex items-center justify-between">
                            <span class="text-muted-foreground">
                                {{ lr.leave_type_name }} · {{ lr.from_date }}
                            </span>
                            <span class="text-xs">{{ lr.status_label }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">{{ t('general.no_record_available') }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
