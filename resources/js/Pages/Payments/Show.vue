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
    payment: { type: Object, required: true },
    settlements: { type: Array, default: () => [] },
})

const payment = computed(() => props.payment?.data ?? props.payment ?? {})
const number = computed(() => payment.value.number)

const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)

function postPayment() {
    router.post(route('payments.post', payment.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { postDialogOpen.value = false },
    })
}

function reversePayment(reason) {
    router.post(route('payments.reverse', payment.value.id), { reason }, {
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
    <AppLayout :title="`${t('payment.payment')} #${number}`">
        <!-- Draft/reversal actions live here rather than inside VoucherShow:
             that component is the shared read-only view of a posted voucher,
             and only this module knows a payment can still be a draft. -->
        <div v-if="payment.status" class="mb-3 flex flex-wrap items-center justify-end gap-2">
            <Badge :class="statusClass(payment.status)" variant="outline">
                {{ statusLabel(payment.status) }}
            </Badge>
            <Button
                v-if="payment.status === 'draft' && can('payments.update')"
                size="sm"
                @click="postDialogOpen = true"
            >
                {{ t('general.post') }}
            </Button>
            <Button
                v-if="payment.status === 'posted' && can('payments.update')"
                size="sm"
                variant="outline"
                @click="reverseDialogOpen = true"
            >
                {{ t('general.reverse') }}
            </Button>
        </div>

        <VoucherShow
            :voucher="payment"
            :settlements="settlements"
            kind="payment"
            index-route="payments.index"
            edit-route="payments.edit"
            print-route="payments.print"
            permission="payments.update"
        />

        <TransactionActionDialog
            v-model:open="postDialogOpen"
            type="post"
            :title="t('general.post') + ' ' + t('payment.payment')"
            :description="t('general.post_document_desc')"
            @confirm="postPayment"
        />
        <TransactionActionDialog
            v-model:open="reverseDialogOpen"
            type="reverse"
            :title="t('general.reverse') + ' ' + t('payment.payment')"
            :description="t('general.reverse_description')"
            @confirm="reversePayment"
        />

        <div class="mt-4">
            <AttachmentList :items="payment.attachments || []" :label="t('general.attachments')" />
        </div>
    </AppLayout>
</template>
