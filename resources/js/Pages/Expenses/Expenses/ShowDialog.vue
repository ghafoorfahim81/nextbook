<script setup>
import { computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    open: Boolean,
    expense: Object,
});

const emit = defineEmits(['update:open']);

const total = computed(() => {
    if (!props.expense?.details) return 0;
    return props.expense.details.reduce((sum, d) => sum + Number(d.amount || 0), 0);
});

const baseTotal = computed(() => {
    return total.value * (props.expense?.rate || 1);
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="text-xl font-semibold text-violet-600">
                    {{ t('expense.expense_details') }}
                </DialogTitle>
            </DialogHeader>

            <div v-if="expense" class="space-y-6">
                <!-- General Info -->
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-muted-foreground">{{ t('general.date') }}:</span>
                        <span class="ml-2 font-medium">{{ expense.formatted_date }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('expense.category') }}:</span>
                        <span class="ml-2 font-medium">{{ expense.category?.name }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('expense.expense_account') }}:</span>
                        <span class="ml-2 font-medium">{{ expense.expense_account?.name }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('expense.bank_account') }}:</span>
                        <span class="ml-2 font-medium">{{ expense.bank_account?.name }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('admin.currency.currency') }}:</span>
                        <span class="ml-2 font-medium">{{ expense.currency?.code }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('general.rate') }}:</span>
                        <span class="ml-2 font-medium">{{ expense.rate }}</span>
                    </div>
                </div>

                <div v-if="expense.remarks">
                    <span class="text-muted-foreground text-sm">{{ t('general.remarks') }}:</span>
                    <p class="mt-1">{{ expense.remarks }}</p>
                </div>

                <Separator />

                <!-- Details Table -->
                <div>
                    <h4 class="font-semibold mb-3 text-violet-600">{{ t('expense.detail_lines') }}</h4>
                    <div class="rounded-lg border overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-4 py-2 text-left">#</th>
                                    <th class="px-4 py-2 text-left">{{ t('expense.title') }}</th>
                                    <th class="px-4 py-2 text-right">{{ t('general.amount') }}</th>
                                    <th class="px-4 py-2 text-left">{{ t('general.note') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(detail, index) in expense.details" :key="detail.id" class="border-t">
                                    <td class="px-4 py-2">{{ index + 1 }}</td>
                                    <td class="px-4 py-2">{{ detail.title }}</td>
                                    <td class="px-4 py-2 text-right">
                                        {{ expense.currency?.symbol }} {{ Number(detail.amount).toLocaleString() }}
                                    </td>
                                    <td class="px-4 py-2 text-muted-foreground">{{ detail.note || '-' }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-violet-500/10 font-semibold">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 text-right">{{ t('general.total') }}:</td>
                                    <td class="px-4 py-2 text-right">
                                        {{ expense.currency?.symbol }} {{ total.toLocaleString() }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr v-if="expense.rate !== 1">
                                    <td colspan="2" class="px-4 py-2 text-right">{{ t('expense.base_currency_total') }}:</td>
                                    <td class="px-4 py-2 text-right">
                                        {{ baseTotal.toLocaleString() }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Attachment -->
                <div v-if="expense.attachment_url">
                    <span class="text-muted-foreground text-sm">{{ t('general.attachment') }}:</span>
                    <a 
                        :href="expense.attachment_url" 
                        target="_blank" 
                        class="ml-2 text-violet-600 hover:underline"
                    >
                        {{ t('general.view_attachment') }}
                    </a>
                </div>

                <!-- Transactions -->
                <Separator />
                <div>
                    <h4 class="font-semibold mb-3 text-violet-600">{{ t('expense.accounting_entries') }}</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="p-3 rounded-lg bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800">
                            <Badge variant="destructive" class="mb-2">DR</Badge>
                            <p class="font-medium">{{ expense.expense_account?.name }}</p>
                            <p class="text-muted-foreground">
                                {{ expense.currency?.symbol }} {{ total.toLocaleString() }}
                            </p>
                        </div>
                        <div class="p-3 rounded-lg bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800">
                            <Badge variant="success" class="bg-green-600 mb-2">CR</Badge>
                            <p class="font-medium">{{ expense.bank_account?.name }}</p>
                            <p class="text-muted-foreground">
                                {{ expense.currency?.symbol }} {{ total.toLocaleString() }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

