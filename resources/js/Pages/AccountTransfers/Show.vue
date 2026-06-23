<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { Calendar, DollarSign, FileText, ArrowLeftRight, Banknote, User } from 'lucide-vue-next'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue'
import { Badge } from '@/Components/ui/badge'

const { t } = useI18n()

const props = defineProps({
    transfer: { type: Object, required: true },
})

const transferData = computed(() => props.transfer?.data ?? props.transfer ?? {})

const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)

function postTransfer() {
    router.post(route('account-transfers.post', transferData.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { postDialogOpen.value = false },
    })
}

function reverseTransfer(reason) {
    router.post(route('account-transfers.reverse', transferData.value.id), { reason }, {
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
    <AppLayout :title="`${t('general.account_transfer')} #${transferData.number}`">
        <div class="space-y-6">
            <ShowPageToolbar
                back-route="account-transfers.index"
                :status="transferData.status"
                :edit-route="transferData.status === 'draft' ? route('account-transfers.edit', transferData.id) : null"
                edit-permission="account-transfers.update"
                @post="postDialogOpen = true"
                @reverse="reverseDialogOpen = true"
            />

            <TransactionActionDialog
                v-model:open="postDialogOpen"
                type="post"
                :title="t('general.post') + ' ' + t('general.account_transfer')"
                :description="t('general.post_document_desc')"
                @confirm="postTransfer"
            />
            <TransactionActionDialog
                v-model:open="reverseDialogOpen"
                type="reverse"
                :title="t('general.reverse') + ' ' + t('general.account_transfer')"
                :description="t('general.reverse_description')"
                @confirm="reverseTransfer"
            />

            <!-- Info card -->
            <fieldset class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 shadow-sm">
                <legend class="px-2 flex items-center gap-2">
                    <ArrowLeftRight class="w-4 h-4 text-violet-500" />
                    <span class="text-sm font-semibold text-violet-500">{{ t('general.account_transfer') }} #{{ transferData.number }}</span>
                    <Badge v-if="transferData.status" :class="statusClass(transferData.status)" variant="outline">{{ statusLabel(transferData.status) }}</Badge>
                </legend>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Calendar class="w-3 h-3" /> {{ t('general.date') }}
                        </div>
                        <div class="text-sm font-medium">{{ transferData.date }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Banknote class="w-3 h-3" /> {{ t('general.amount') }}
                        </div>
                        <div class="text-sm font-medium">{{ transferData.amount }} {{ transferData.currency_code }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="w-3 h-3" /> {{ t('general.rate') }}
                        </div>
                        <div class="text-sm font-medium">{{ transferData.rate }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <FileText class="w-3 h-3" /> {{ t('general.remark') }}
                        </div>
                        <div class="text-sm font-medium">{{ transferData.remark || '-' }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="w-3 h-3" /> {{ t('general.created_by') }}
                        </div>
                        <div class="text-sm font-medium">{{ transferData.created_by?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="w-3 h-3" /> {{ t('general.updated_by') }}
                        </div>
                        <div class="text-sm font-medium">{{ transferData.updated_by?.name || '-' }}</div>
                    </div>
                </div>
            </fieldset>

            <!-- Transfer accounts -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-border rounded-xl p-4 bg-card">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.from_account') }}</div>
                    <div class="text-sm font-medium">{{ transferData.from_account?.name || '-' }}</div>
                    <div class="text-xs text-muted-foreground mt-1">{{ transferData.currency?.symbol || '' }} {{ transferData.amount }}</div>
                </div>
                <div class="border border-border rounded-xl p-4 bg-card">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.to_account') }}</div>
                    <div class="text-sm font-medium">{{ transferData.to_account?.name || '-' }}</div>
                    <div class="text-xs text-muted-foreground mt-1">{{ transferData.currency?.symbol || '' }} {{ transferData.amount }}</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
