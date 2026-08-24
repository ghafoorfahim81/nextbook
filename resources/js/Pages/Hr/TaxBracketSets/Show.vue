<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    taxBracketSet: { type: Object, required: true },
});

const set = computed(() => props.taxBracketSet?.data ?? props.taxBracketSet);
const brackets = computed(() => set.value.brackets?.data ?? set.value.brackets ?? []);

// The top band has no ceiling — null means "and everything above", so it reads
// as an open range rather than a suspiciously round number.
const ceiling = (bracket) => (bracket.to_amount === null || bracket.to_amount === undefined ? '∞' : bracket.to_amount);
</script>

<template>
    <AppLayout :title="set.name">
        <FormPageToolbar back-route="tax-bracket-sets.index" module="tax_bracket_sets" />

        <div class="mb-5 rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">{{ set.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ set.jurisdiction || '—' }} · {{ set.period_label }}
                        <span v-if="set.effective_from"> · {{ set.effective_from }} → {{ set.effective_to || '—' }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full border px-3 py-1 text-xs"
                        :class="set.is_active
                            ? 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300'
                            : 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300'"
                    >
                        {{ set.is_active ? t('general.active') : t('general.inactive') }}
                    </span>
                    <span
                        v-if="set.is_system"
                        class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs text-primary"
                    >
                        {{ t('hr.is_system') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-card shadow-sm lg:col-span-2">
                <h2 class="border-b border-border px-4 py-3 text-sm font-semibold text-foreground">
                    {{ t('hr.tax_brackets') }}
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="bg-muted/50 text-xs uppercase">
                            <tr class="font-semibold text-violet-500">
                                <th class="px-4 py-2 text-start">#</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.from_amount') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.to_amount') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.fixed_amount') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.rate_percent') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!brackets.length">
                                <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">
                                    {{ t('general.no_data_found') }}
                                </td>
                            </tr>
                            <tr v-for="bracket in brackets" :key="bracket.id" class="border-t border-border">
                                <td class="px-4 py-2 text-muted-foreground">{{ bracket.sequence }}</td>
                                <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ bracket.from_amount }}</td>
                                <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ ceiling(bracket) }}</td>
                                <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ bracket.fixed_amount }}</td>
                                <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ bracket.rate }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('general.info') }}</h2>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('hr.tax_period') }}</dt>
                        <dd>{{ set.period_label || '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('general.currency') }}</dt>
                        <dd>{{ set.currency_code || '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('hr.effective_from') }}</dt>
                        <dd>{{ set.effective_from || '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('hr.effective_to') }}</dt>
                        <dd>{{ set.effective_to || '—' }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-border pt-2">
                        <dt class="text-muted-foreground">{{ t('general.created_by') }}</dt>
                        <dd>{{ set.created_by || '—' }}</dd>
                    </div>
                </dl>

                <div v-if="set.remark" class="mt-4 border-t border-border pt-3">
                    <dt class="text-xs text-muted-foreground">{{ t('admin.shared.remark') }}</dt>
                    <dd class="text-sm">{{ set.remark }}</dd>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
