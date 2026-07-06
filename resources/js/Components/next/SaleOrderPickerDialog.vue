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
    saleOrders: { type: Array, default: () => [] },
})

const emit = defineEmits(['select', 'update:open'])

const useOrder = (saleOrder) => {
    emit('select', saleOrder.id)
}

const skip = () => {
    emit('update:open', false)
}
</script>

<template>
    <Dialog :open="open" @update:open="value => emit('update:open', value)">
        <DialogContent class="max-w-2xl rounded-xl">
            <DialogHeader>
                <DialogTitle>{{ t('sale_order.eligible_sale_orders_title') }}</DialogTitle>
                <DialogDescription>{{ t('sale_order.eligible_sale_orders_description') }}</DialogDescription>
            </DialogHeader>

            <div class="max-h-96 overflow-y-auto">
                <table v-if="saleOrders.length" class="w-full text-sm">
                    <thead class="border-b border-border bg-muted/40">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.number') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.date') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('sale_order.delivery_date') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('sale_order.item_count') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.amount') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="saleOrder in saleOrders" :key="saleOrder.id" class="hover:bg-muted/40 transition-colors">
                            <td class="px-3 py-2 font-medium">#{{ saleOrder.number }}</td>
                            <td class="px-3 py-2">{{ saleOrder.date }}</td>
                            <td class="px-3 py-2">{{ saleOrder.delivery_date || '-' }}</td>
                            <td class="px-3 py-2 text-right">{{ saleOrder.item_count }}</td>
                            <td class="px-3 py-2 text-right">{{ Number(saleOrder.amount || 0).toFixed(2) }}</td>
                            <td class="px-3 py-2 text-right">
                                <Button size="sm" @click="useOrder(saleOrder)">{{ t('sale_order.use_this_order') }}</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="py-8 text-center text-sm text-muted-foreground">
                    {{ t('sale_order.no_eligible_sale_orders') }}
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="skip">{{ t('sale_order.skip') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
