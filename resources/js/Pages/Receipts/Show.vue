<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import AttachmentList from '@/Components/AttachmentList.vue'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'
import VoucherShow from '@/Components/accounting/VoucherShow.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { useAuth } from '@/composables/useAuth'

const { t } = useI18n()
const { can } = useAuth()

const props = defineProps({
    receipt: { type: Object, required: true },
    settlements: { type: Array, default: () => [] },
})

const receipt = computed(() => props.receipt?.data ?? props.receipt ?? {})
const number = computed(() => receipt.value.number)

const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)

function postReceipt() {
    router.post(route('receipts.post', receipt.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { postDialogOpen.value = false },
    })
}

function reverseReceipt(reason) {
    router.post(route('receipts.reverse', receipt.value.id), { reason }, {
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
        case 'draft':    return t('general.draft')
        case 'posted':   return t('general.posted')
        case 'reversed': return t('general.reversed')
        default:         return status || '-'
    }
}
</script>

<template>
    <AppLayout :title="`${t('receipt.receipt')} #${number}`">
        <!-- Draft/reversal actions live here rather than inside VoucherShow:
             that component is the shared read-only view of a posted voucher,
             and only this module knows a receipt can still be a draft. -->
        <div v-if="receipt.status" class="mb-3 flex flex-wrap items-center justify-end gap-2">
            <Badge :class="statusClass(receipt.status)" variant="outline">
                {{ statusLabel(receipt.status) }}
            </Badge>
            <Button
                v-if="receipt.status === 'draft' && can('receipts.update')"
                size="sm"
                @click="postDialogOpen = true"
            >
                {{ t('general.post') }}
            </Button>
            <Button
                v-if="receipt.status === 'posted' && can('receipts.update')"
                size="sm"
                variant="outline"
                @click="reverseDialogOpen = true"
            >
                {{ t('general.reverse') }}
            </Button>
        </div>

        <VoucherShow
            :voucher="receipt"
            :settlements="settlements"
            kind="receipt"
            index-route="receipts.index"
            edit-route="receipts.edit"
            print-route="receipts.print"
            permission="receipts.update"
        />

        <TransactionActionDialog
            v-model:open="postDialogOpen"
            type="post"
            :title="t('general.post') + ' ' + t('receipt.receipt')"
            :description="t('general.post_document_desc')"
            @confirm="postReceipt"
        />
        <TransactionActionDialog
            v-model:open="reverseDialogOpen"
            type="reverse"
            :title="t('general.reverse') + ' ' + t('receipt.receipt')"
            :description="t('general.reverse_description')"
            @confirm="reverseReceipt"
        />

        <div class="mt-4">
            <AttachmentList :items="receipt.attachments || []" :label="t('general.attachments')" />
        </div>
    </AppLayout>
</template>
