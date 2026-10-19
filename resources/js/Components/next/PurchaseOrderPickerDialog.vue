<script setup>
import { useI18n } from 'vue-i18n'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'

const { t } = useI18n()

const props = defineProps({
    open: Boolean,
    purchaseOrders: { type: Array, default: () => [] },
})

const emit = defineEmits(['select', 'update:open'])

const useOrder = (purchaseOrder) => {
    emit('select', purchaseOrder.id)
}

const skip = () => {
    emit('update:open', false)
}
</script>

<template>
    <Dialog :open="open" @update:open="value => emit('update:open', value)">
        <DialogContent class="max-w-2xl rounded-xl">
            <DialogHeader>
                <DialogTitle>{{ t('purchase_order.eligible_purchase_orders_title') }}</DialogTitle>
                <DialogDescription>{{ t('purchase_order.eligible_purchase_orders_description') }}</DialogDescription>
            </DialogHeader>

            <div class="max-h-96 overflow-y-auto">
                <table v-if="purchaseOrders.length" class="w-full text-sm">
                    <thead class="border-b border-border bg-muted/40">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.number') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.date') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('purchase_order.delivery_date') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('purchase_order.item_count') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.amount') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="purchaseOrder in purchaseOrders" :key="purchaseOrder.id" class="hover:bg-muted/40 transition-colors">
                            <td class="px-3 py-2 font-medium">#{{ purchaseOrder.number }}</td>
                            <td class="px-3 py-2">{{ purchaseOrder.date }}</td>
                            <td class="px-3 py-2">{{ purchaseOrder.delivery_date || '-' }}</td>
                            <td class="px-3 py-2 text-right">{{ purchaseOrder.item_count }}</td>
                            <td class="px-3 py-2 text-right">{{ Number(purchaseOrder.amount || 0).toFixed(2) }}</td>
                            <td class="px-3 py-2 text-right">
                                <Button size="sm" @click="useOrder(purchaseOrder)">{{ t('purchase_order.use_this_order') }}</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="py-8 text-center text-sm text-muted-foreground">
                    {{ t('purchase_order.no_eligible_purchase_orders') }}
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="skip">{{ t('purchase_order.skip') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
