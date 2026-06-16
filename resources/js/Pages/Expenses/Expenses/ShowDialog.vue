<script setup>
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Separator } from '@/Components/ui/separator';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue';

const { t } = useI18n();

const props = defineProps({
    open: Boolean,
    expense: Object,
});

const emit = defineEmits(['update:open']);
const postDialogOpen = ref(false);
const reverseDialogOpen = ref(false);

const total = computed(() => {
    if (!props.expense?.details) return 0;
    return props.expense.details.reduce((sum, d) => sum + Number(d.amount || 0), 0);
});

const baseTotal = computed(() => {
    return total.value * (props.expense?.rate || 1);
});

const statusClass = computed(() => {
    switch (props.expense?.status) {
        case 'draft':     return 'bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700'
        case 'posted':    return 'bg-green-100 text-green-800 border border-green-300 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700'
        case 'reversed':  return 'bg-red-100 text-red-800 border border-red-300 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700'
        default:          return 'bg-muted text-muted-foreground border border-border'
    }
})

const statusLabel = computed(() => {
    switch (props.expense?.status) {
        case 'draft':    return t('general.status_draft')
        case 'posted':   return t('general.status_posted')
        case 'reversed': return t('general.status_reversed')
        default:         return props.expense?.status ?? ''
    }
})

const postExpense = () => {
    if (!props.expense?.id) return;
    router.post(route('expenses.post', props.expense.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            postDialogOpen.value = false;
            emit('update:open', false);
        },
    });
};

const reverseExpense = (reason) => {
    if (!props.expense?.id) return;
    router.post(route('expenses.reverse', props.expense.id), { reason }, {
        preserveScroll: true,
        onSuccess: () => {
            reverseDialogOpen.value = false;
            emit('update:open', false);
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <div class="flex items-center justify-between gap-3">
                    <DialogTitle class="text-xl font-semibold text-violet-600">
                        {{ t('expense.expense_details') }}
                    </DialogTitle>
                    <span v-if="expense" :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', statusClass]">
                        {{ statusLabel }}
                    </span>
                </div>
            </DialogHeader>

            <div v-if="expense" class="space-y-6">
                <div class="flex justify-end gap-2">
                    <button v-if="expense.status === 'draft'" class="rounded-md bg-green-600 px-3 py-1.5 text-sm text-white hover:bg-green-700" @click="postDialogOpen = true">
                        {{ t('general.post') }}
                    </button>
                    <button v-if="expense.status === 'posted'" class="rounded-md bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700" @click="reverseDialogOpen = true">
                        {{ t('general.reverse') }}
                    </button>
                </div>
                <TransactionActionDialog
                    v-model:open="postDialogOpen"
                    type="post"
                    :title="t('general.post') + ' ' + t('expense.expense')"
                    :description="t('general.post_document_desc')"
                    @confirm="postExpense"
                />
                <TransactionActionDialog
                    v-model:open="reverseDialogOpen"
                    type="reverse"
                    :title="t('general.reverse') + ' ' + t('expense.expense')"
                    :description="t('general.reverse_description')"
                    @confirm="reverseExpense"
                />
                <!-- General Info -->
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-muted-foreground">{{ t('general.date') }}:</span>
                        <span class="ml-2 font-medium">{{ expense.date }}</span>
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
                                    <th class="px-4 py-2 text-left rtl:text-right">#</th>
                                    <th class="px-4 py-2 text-left rtl:text-right">{{ t('expense.title') }}</th>
                                    <th class="px-4 py-2 text-right">{{ t('general.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(detail, index) in expense.details" :key="detail.id" class="border-t">
                                    <td class="px-4 py-2">{{ index + 1 }}</td>
                                    <td class="px-4 py-2">{{ detail.title }}</td>
                                    <td class="px-4 py-2 text-right">
                                        {{ expense.currency?.symbol }} {{ Number(detail.amount).toLocaleString() }}
                                    </td>
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
                    <h4 class="font-semibold mb-3 text-violet-600 dark:text-violet-300">{{ t('expense.accounting_entries') }}</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="p-3 rounded-lg
                            bg-green-100 border border-green-300
                            dark:bg-green-900/[.94] dark:border-green-700 text-green-900 dark:text-green-100
                            flex flex-col items-start min-h-[88px]">
                            <span class="inline-flex items-center rounded-full bg-green-600 px-2 py-0.5 text-xs font-medium text-white mb-2" style="direction: ltr;">DR</span>
                            <p class="font-medium break-words">{{ expense.expense_account?.name }}</p>
                            <p class="text-muted-foreground">
                                {{ expense.currency?.symbol }} {{ total.toLocaleString() }}
                            </p>
                        </div>
                        <div class="p-3 rounded-lg
                            bg-red-100 border border-red-300
                            dark:bg-red-900/[.93] dark:border-red-700 text-red-900 dark:text-red-100
                            flex flex-col items-start min-h-[88px]">
                            <span class="inline-flex items-center rounded-full bg-red-600 px-2 py-0.5 text-xs font-medium text-white mb-2" style="direction: ltr;">CR</span>
                            <p class="font-medium break-words">{{ expense.bank_account?.name }}</p>
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
