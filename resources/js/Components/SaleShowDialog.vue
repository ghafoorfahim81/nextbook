<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    saleId: {
        type: String,
        default: null
    }
});

const emit = defineEmits(['update:modelValue']);

const sale = ref(null);
const loading = ref(false);

// Watch for sale ID changes to load sale data
watch(() => props.saleId, async (newId) => {
    if (newId && props.modelValue) {
        await loadSale(newId);
    }
});

// Watch for dialog open/close
watch(() => props.modelValue, (isOpen) => {
    if (!isOpen) {
        sale.value = null;
    }
});

const loadSale = async (id) => {
    loading.value = true;
    try {
        const response = await axios.get(`/sales/${id}`);
        sale.value = response.data.data;
    } catch (error) {
        console.error('Error loading sale:', error);
    } finally {
        loading.value = false;
    }
};

const closeDialog = () => {
    emit('update:modelValue', false);
};

const getStatusColor = (status) => {
    switch (status) {
        case 'approved':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'rejected':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Dialog :open="modelValue" @update:open="emit('update:modelValue', $event)">
        <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ t('sale.sale_details') }}</DialogTitle>
            </DialogHeader>

            <div v-if="loading" class="flex justify-center items-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
            </div>

            <div v-else-if="sale" class="space-y-6">
                <!-- Sale Header -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h3 class="font-semibold">{{ t('general.number') }}: {{ sale.number }}</h3>
                        <p class="text-sm text-gray-600">{{ t('general.date') }}: {{ sale.date }}</p>
                        <p class="text-sm text-gray-600">{{ t('ledger.customer.customer') }}: {{ sale.customer_name }}</p>
                    </div>
                    <div class="text-right">
                        <Badge :class="getStatusColor(sale.status)">
                            {{ sale.status }}
                        </Badge>
                        <p class="text-sm text-gray-600 mt-1">{{ t('general.type') }}: {{ sale.type }}</p>
                        <p class="text-sm text-gray-600">{{ t('general.amount') }}: {{ sale.amount }}</p>
                    </div>
                </div>

                <!-- Items Table -->
                <div>
                    <h4 class="font-semibold mb-3">{{ t('inventory.items') }}</h4>
                    <div class="border rounded-lg overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">{{ t('inventory.item.item') }}</th>
                                    <th class="px-4 py-2 text-left">{{ t('general.batch') }}</th>
                                    <th class="px-4 py-2 text-left">{{ t('general.quantity') }}</th>
                                    <th class="px-4 py-2 text-left">{{ t('general.unit_price') }}</th>
                                    <th class="px-4 py-2 text-left">{{ t('general.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in sale.items" :key="item.id" class="border-t">
                                    <td class="px-4 py-2">{{ item.item_name }}</td>
                                    <td class="px-4 py-2">{{ item.batch || '-' }}</td>
                                    <td class="px-4 py-2">{{ item.quantity }}</td>
                                    <td class="px-4 py-2">{{ item.unit_price }}</td>
                                    <td class="px-4 py-2">{{ (item.quantity * item.unit_price).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Additional Information -->
                <div v-if="sale.description" class="space-y-2">
                    <h4 class="font-semibold">{{ t('general.description') }}</h4>
                    <p class="text-sm text-gray-600">{{ sale.description }}</p>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <Button @click="closeDialog">
                    {{ t('general.close') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
