<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import AttachmentList from '@/Components/AttachmentList.vue'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { Separator } from '@/Components/ui/separator'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue'
import { Badge } from '@/Components/ui/badge'

const { t } = useI18n()

const props = defineProps({
    expense: { type: Object, required: true },
})

const expense = computed(() => props.expense?.data ?? props.expense ?? {})

const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)

const total = computed(() => {
    if (!expense.value?.details) return 0
    return expense.value.details.reduce((sum, d) => sum + Number(d.amount || 0), 0)
})

const baseTotal = computed(() => total.value * (expense.value?.rate || 1))

function postExpense() {
    router.post(route('expenses.post', expense.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { postDialogOpen.value = false },
    })
}

function reverseExpense(reason) {
    router.post(route('expenses.reverse', expense.value.id), { reason }, {
        preserveScroll: true,
        onSuccess: () => { reverseDialogOpen.value = false },
    })
}

const statusClass = (status) => {
    switch (status) {
        case 'draft':    return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
        case 'posted':   return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300'
        case 'reversed': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300'
        default:         return 'border-border bg-muted text-foreground'
    }
}
const statusLabel = (status) => {
    switch (status) {
        case 'draft':    return t('general.status_draft')
        case 'posted':   return t('general.status_posted')
        case 'reversed': return t('general.status_reversed')
        default:         return status ?? ''
    }
}
</script>

<template>
    <AppLayout :title="`${t('expense.expense')} #${expense.number}`">
        <div class="space-y-6">
            <ShowPageToolbar
                back-route="expenses.index"
                :status="expense.status"
                :edit-route="expense.status === 'draft' ? route('expenses.edit', expense.id) : null"
                edit-permission="expenses.update"
                @post="postDialogOpen = true"
                @reverse="reverseDialogOpen = true"
            />

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

            <div class="rounded-xl border border-border bg-card p-5 shadow-sm space-y-4">
                <div class="flex items-center gap-3">
                    <span class="text-lg font-semibold text-violet-600">{{ t('expense.expense_details') }} #{{ expense.number }}</span>
                    <Badge v-if="expense.status" :class="statusClass(expense.status)" variant="outline">{{ statusLabel(expense.status) }}</Badge>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                    <div v-if="expense.number">
                        <span class="text-muted-foreground">{{ t('general.number') }}:</span>
                        <span class="ml-2 font-medium">{{ expense.number }}</span>
                    </div>
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
                    <p class="mt-1 text-sm">{{ expense.remarks }}</p>
                </div>
            </div>

            <Separator />

            <div class="rounded-xl border border-border overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b bg-card">
                    <h3 class="font-semibold text-violet-600">{{ t('expense.detail_lines') }}</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-2 ltr:text-left rtl:text-right">#</th>
                            <th class="px-4 py-2 ltr:text-left rtl:text-right">{{ t('expense.title') }}</th>
                            <th class="px-4 py-2 text-right">{{ t('general.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(detail, index) in expense.details" :key="detail.id" class="border-t">
                            <td class="px-4 py-2">{{ index + 1 }}</td>
                            <td class="px-4 py-2">{{ detail.title }}</td>
                            <td class="px-4 py-2 text-right">{{ expense.currency?.symbol }} {{ Number(detail.amount).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-violet-500/10 font-semibold">
                        <tr>
                            <td colspan="2" class="px-4 py-2 text-right">{{ t('general.total') }}:</td>
                            <td class="px-4 py-2 text-right">{{ expense.currency?.symbol }} {{ total.toLocaleString() }}</td>
                        </tr>
                        <tr v-if="expense.rate !== 1">
                            <td colspan="2" class="px-4 py-2 text-right">{{ t('expense.base_currency_total') }}:</td>
                            <td class="px-4 py-2 text-right">{{ baseTotal.toLocaleString() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-if="expense.attachment_url" class="text-sm">
                <span class="text-muted-foreground">{{ t('general.attachment') }}:</span>
                <a :href="expense.attachment_url" target="_blank" class="ml-2 text-violet-600 hover:underline">
                    {{ t('general.view_attachment') }}
                </a>
            </div>

            <Separator />

            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h4 class="font-semibold mb-3 text-violet-600 dark:text-violet-300">{{ t('expense.accounting_entries') }}</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="p-3 rounded-lg bg-green-100 border border-green-300 dark:bg-green-900/[.94] dark:border-green-700 text-green-900 dark:text-green-100 flex flex-col items-start min-h-[88px]">
                        <span class="inline-flex items-center rounded-full bg-green-600 px-2 py-0.5 text-xs font-medium text-white mb-2" style="direction: ltr;">DR</span>
                        <p class="font-medium break-words">{{ expense.expense_account?.name }}</p>
                        <p class="text-muted-foreground">{{ expense.currency?.symbol }} {{ total.toLocaleString() }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-red-100 border border-red-300 dark:bg-red-900/[.93] dark:border-red-700 text-red-900 dark:text-red-100 flex flex-col items-start min-h-[88px]">
                        <span class="inline-flex items-center rounded-full bg-red-600 px-2 py-0.5 text-xs font-medium text-white mb-2" style="direction: ltr;">CR</span>
                        <p class="font-medium break-words">{{ expense.bank_account?.name }}</p>
                        <p class="text-muted-foreground">{{ expense.currency?.symbol }} {{ total.toLocaleString() }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4">
            <AttachmentList :items="expense.attachments || []" :label="t('general.attachment')" />
        </div>
    </AppLayout>
</template>
