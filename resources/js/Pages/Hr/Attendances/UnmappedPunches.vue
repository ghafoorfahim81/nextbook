<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import { Button } from '@/Components/ui/button';
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Link2 } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    punches: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

const rows = computed(() => props.punches?.data ?? []);
const meta = computed(() => props.punches?.meta ?? {});

/**
 * One mapping covers every punch that shares a device ID, so the list is
 * grouped rather than mapped row by row — otherwise the same ID would be
 * mapped dozens of times.
 */
const grouped = computed(() => {
    const map = new Map();

    for (const punch of rows.value) {
        const key = `${punch.device_id ?? 'none'}::${punch.device_user_id}`;

        if (!map.has(key)) {
            map.set(key, {
                key,
                device_id: punch.device_id,
                device_name: punch.device_name,
                device_user_id: punch.device_user_id,
                count: 0,
                first_seen: punch.punched_at,
                last_seen: punch.punched_at,
            });
        }

        const entry = map.get(key);
        entry.count += 1;
        if (punch.punched_at < entry.first_seen) entry.first_seen = punch.punched_at;
        if (punch.punched_at > entry.last_seen) entry.last_seen = punch.punched_at;
    }

    return [...map.values()];
});

const selected = ref({});

const form = useForm({
    attendance_device_id: null,
    employee_id: null,
    device_user_id: null,
});

const saveMapping = (group) => {
    const employee = selected.value[group.key];

    if (!employee?.id) {
        toast.error(t('general.error'), { description: t('hr.map_to_employee'), class: 'bg-red-600' });
        return;
    }

    form.attendance_device_id = group.device_id;
    form.employee_id = employee.id;
    form.device_user_id = group.device_user_id;

    form.post(route('attendance-device-mappings.store'), {
        preserveScroll: true,
        onSuccess: () => {
            delete selected.value[group.key];
        },
    });
};
</script>

<template>
    <AppLayout :title="t('hr.unmapped_punches')">
        <div class="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
            {{ t('hr.unmapped_hint') }}
        </div>

        <div class="rounded-xl border border-border bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ t('hr.device') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('hr.device_user_id') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('general.total') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('hr.punched_at') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('hr.map_to_employee') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="group in grouped" :key="group.key" class="border-t border-border hover:bg-muted/30">
                            <td class="px-4 py-2">{{ group.device_name ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono">{{ group.device_user_id }}</td>
                            <td class="px-4 py-2">{{ group.count }}</td>
                            <td class="px-4 py-2 text-xs text-muted-foreground">
                                {{ group.first_seen }}
                                <span v-if="group.last_seen !== group.first_seen"> → {{ group.last_seen }}</span>
                            </td>
                            <td class="px-4 py-2 w-64">
                                <NextSelect
                                    :options="[]"
                                    v-model="selected[group.key]"
                                    label-key="name" value-key="id" :reduce="(x) => x"
                                    :searchable="true"
                                    resource-type="employees"
                                    :search-fields="['full_name', 'code']"
                                    :floating-text="t('hr.employee')"
                                />
                            </td>
                            <td class="px-4 py-2">
                                <Button
                                    size="sm" variant="outline"
                                    class="h-8 gap-1 border-primary text-primary hover:bg-primary hover:text-white"
                                    :disabled="form.processing"
                                    @click="saveMapping(group)"
                                >
                                    <Link2 class="h-3.5 w-3.5" />
                                    {{ t('hr.save_mapping') }}
                                </Button>
                            </td>
                        </tr>

                        <tr v-if="!grouped.length">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                {{ t('general.no_record_available') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta.total" class="border-t border-border px-4 py-2 text-xs text-muted-foreground">
                {{ t('datatable.showing', { from: meta.from, to: meta.to, total: meta.total }) }}
            </div>
        </div>
    </AppLayout>
</template>
