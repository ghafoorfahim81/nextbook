<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Calendar, DollarSign, FileText, ArrowLeftRight, Banknote, User } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'

const { t } = useI18n()

const props = defineProps({
    open: Boolean,
    transferId: String,
})
const emit = defineEmits(['update:open'])

const transfer = ref(null)
const loading = ref(false)
const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)

watch(() => props.open, async (isOpen) => {
    if (isOpen && props.transferId) {
        loading.value = true
        try {
            const { data } = await axios.get(`/account-transfers/${props.transferId}`)
            transfer.value = data?.data || null
        } finally {
            loading.value = false
        }
    }
})

function closeDialog() {
    emit('update:open', false)
    transfer.value = null
}

function postTransfer() {
    if (!props.transferId) return
    router.post(route('account-transfers.post', props.transferId), {}, {
        preserveScroll: true,
        onSuccess: () => {
            postDialogOpen.value = false
            closeDialog()
        },
    })
}

function reverseTransfer(reason) {
    if (!props.transferId) return
    router.post(route('account-transfers.reverse', props.transferId), { reason }, {
        preserveScroll: true,
        onSuccess: () => {
            reverseDialogOpen.value = false
            closeDialog()
        },
    })
}
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="max-w-3xl">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <ArrowLeftRight class="w-6 h-6 text-violet-500" />
                    <DialogTitle class="text-xl">
                        {{ t('general.account_transfer') }} <span v-if="transfer">#{{ transfer.number }}</span>
                    </DialogTitle>
                </div>
                <DialogDescription class="text-xs text-muted-foreground" v-if="transfer?.remark">
                    {{ transfer.remark }}
                </DialogDescription>
                <div v-if="transfer" class="flex items-center gap-2 pt-2">
                    <span class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize">{{ transfer.status }}</span>
                    <Button v-if="transfer.status === 'draft'" size="sm" class="bg-green-600 text-white hover:bg-green-700" @click="postDialogOpen = true">Post</Button>
                    <Button v-if="transfer.status === 'posted'" size="sm" variant="destructive" @click="reverseDialogOpen = true">Reverse</Button>
                </div>
            </DialogHeader>

            <TransactionActionDialog
                v-model:open="postDialogOpen"
                type="post"
                title="Post account transfer"
                description="This will write the accounting entries for this transfer."
                @confirm="postTransfer"
            />
            <TransactionActionDialog
                v-model:open="reverseDialogOpen"
                type="reverse"
                title="Reverse account transfer"
                description="Enter a reason to create the reversal transaction."
                @confirm="reverseTransfer"
            />

            <div v-if="loading" class="py-6 text-center text-muted-foreground">
                {{ t('general.loading') }}...
            </div>

            <div v-else-if="transfer" class="space-y-6">
                <div class="bg-card rounded-lg p-4 border border-border">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Calendar class="w-3 h-3" />
                                {{ t('general.date') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ transfer.date }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Banknote class="w-3 h-3" />
                                {{ t('general.amount') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ transfer.amount }} {{ transfer.currency_code }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Banknote class="w-3 h-3" />
                                {{ t('general.rate') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ transfer.rate }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <FileText class="w-3 h-3" />
                                {{ t('general.number') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ transfer.number }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <User class="w-3 h-3" />
                                {{ t('general.created_by') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ transfer.created_by?.name || '-' }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <User class="w-3 h-3" />
                                {{ t('general.updated_by') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ transfer.updated_by?.name || '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-border rounded-lg p-4 bg-card">
                        <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.from_account') }}</div>
                        <div v-if="transfer.from_account" class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-muted-foreground">{{ t('general.account') }}</div>
                            <div class="font-medium">
                                {{ transfer.from_account?.name || '-' }}
                            </div>
                            <div class="text-muted-foreground">{{ t('general.amount') }}</div>
                            <div class="font-medium">
                                {{ transfer.currency?.symbol || '' }} {{ transfer.amount }}
                            </div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
                    </div>
                    <div class="border border-border rounded-lg p-4 bg-card">
                        <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.to_account') }}  </div>
                        <div v-if="transfer.to_account" class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-muted-foreground">{{ t('general.account') }}</div>
                            <div class="font-medium">
                                {{ transfer.to_account?.name || '-' }}
                            </div>
                            <div class="text-muted-foreground">{{ t('general.amount') }}</div>
                            <div class="font-medium">
                                {{ transfer.currency?.symbol || '' }} {{ transfer.amount }}
                            </div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeDialog">
                    {{ t('general.close') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>



