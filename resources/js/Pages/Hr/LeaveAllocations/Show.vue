<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import { Button } from '@/Components/ui/button';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { User } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    allocation: { type: Object, required: true },
    balance: { type: Object, default: null },
});

const a = computed(() => props.allocation?.data ?? props.allocation);
</script>

<template>
    <AppLayout :title="`${t('hr.leave_allocation')} — ${a.employee_name}`">
        <FormPageToolbar back-route="leave-allocations.index" module="leave_allocations" />

        <div class="mb-5 rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        {{ a.employee_name }}<span v-if="a.employee_code" class="text-muted-foreground"> ({{ a.employee_code }})</span>
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ a.leave_type_name }} · {{ a.period_start }} → {{ a.period_end }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs text-primary">
                        {{ a.source_label }}
                    </span>

                    <Button
                        v-if="a.employee_id"
                        size="sm" variant="outline"
                        class="h-9 gap-1"
                        @click="router.visit(route('employees.show', a.employee_id))"
                    >
                        <User class="h-4 w-4" />
                        {{ t('hr.employee') }}
                    </Button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <!-- What was granted -->
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm lg:col-span-2">
                <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('hr.leave_allocation') }}</h2>

                <dl class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.leave_type') }}</dt><dd>{{ a.leave_type_name || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.period_start') }}</dt><dd>{{ a.period_start || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.period_end') }}</dt><dd>{{ a.period_end || '—' }}</dd></div>

                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.entitled_days') }}</dt><dd>{{ a.entitled_days }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.carried_forward_days') }}</dt><dd>{{ a.carried_forward_days }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.adjustment_days') }}</dt><dd>{{ a.adjustment_days }}</dd></div>

                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.encashed_days') }}</dt><dd>{{ a.encashed_days }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.expired_days') }}</dt><dd>{{ a.expired_days }}</dd></div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('hr.granted_days') }}</dt>
                        <dd class="font-semibold">{{ a.granted_days }}</dd>
                    </div>

                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">{{ t('admin.shared.remark') }}</dt>
                        <dd>{{ a.remark || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- What is left -->
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('hr.balance_after') }}</h2>

                <dl v-if="balance" class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted-foreground">{{ t('hr.entitled_days') }}</dt><dd>{{ balance.entitled }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted-foreground">{{ t('hr.carried_forward_days') }}</dt><dd>{{ balance.carried }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted-foreground">{{ t('hr.taken_days') }}</dt><dd>{{ balance.taken }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted-foreground">{{ t('hr.pending_days') }}</dt><dd>{{ balance.pending }}</dd></div>
                    <div class="flex justify-between border-t border-border pt-2 font-semibold">
                        <dt>{{ t('hr.available_days') }}</dt>
                        <dd :class="balance.available < 0 ? 'text-red-600 dark:text-red-400' : ''">{{ balance.available }}</dd>
                    </div>
                </dl>
                <p v-else class="text-sm text-muted-foreground">{{ t('general.no_data_found') }}</p>

                <dl class="mt-4 space-y-2 border-t border-border pt-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('hr.source') }}</dt>
                        <dd>{{ a.source_label || '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('general.created_by') }}</dt>
                        <dd>{{ a.created_by || '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>
