<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/Layout.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextInput from '@/Components/next/NextInput.vue'
import { Button } from '@/Components/ui/button'
import { useToast } from '@/Components/ui/toast/use-toast'
import { useI18n } from 'vue-i18n';
import { useSidebar } from '@/Components/ui/sidebar/utils';
import { h, ref, watch, onMounted, onUnmounted, computed } from 'vue';

const { t } = useI18n()
const { toast } = useToast()

let sidebar = null
try {
    sidebar = useSidebar()
} catch (e) {
    sidebar = null
}
const prevSidebarOpen = ref(true)
onMounted(() => {
    if (sidebar) {
        prevSidebarOpen.value = sidebar.open.value
        sidebar.setOpen(false)
    }

})
onUnmounted(() => {
    if (sidebar) {
        sidebar.setOpen(prevSidebarOpen.value)
    }
})

const props = defineProps({
    items: { type: Array, required: true },
    unitMeasures: { type: Array, required: true }, // Array of {id, name}
    stores: { type: Array, required: true },       // Array of {id, name}
})

// Initialize form with _key for v-for
const form = useForm({
    items: props.items.map(item => ({
        _key: item.id,
        item_id: item.id,
        name: item.name,
        batch: item.batch,
        store_id: null,
        unit_measure_id: item.unit_measure_id,
        quantity: item.quantity ?? 0,
        expire_date: item.expire_date ?? '',
        cost: item.cost ?? 0,
    }))
})

const removeRow = (idx) => {
    if (form.items.length <= 1) return
    form.items.splice(idx, 1)
}

// Helper for field errors
const fieldError = (idx, field) => form.errors?.[`items.${idx}.${field}`]

// Normalize before submission
const normalize = () => {
    form.items = form.items.map(r => ({
        ...r,
        name: r.name || '',
        batch: r.batch || '',
        quantity: r.quantity === '' ? null : Number(r.quantity),
        cost: r.cost === '' ? null : Number(r.cost),
        unit_measure_id: r.unit_measure_id ?? null,
        store_id: r.store_id ?? null,
        expire_date: r.expire_date || null,
    }))

    // Remove empty rows
    form.items = form.items.filter(r => Object.values({ ...r, _key: undefined }).some(v => v !== '' && v !== null))
}

// Handle form submit
const handleSubmit = () => {
    normalize()
    form.post(route('fast-opening.store'), {
        onSuccess: () => {
            toast({
                title: t('general.success'),
                variant: 'success',
                description: t('general.create_success', { name: t('item.opening') }),
            });
        }
    })
}
</script>

<template>
    <AppLayout :title="t('item.fast_opening')" :sidebar-collapsed="true">
        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="rounded-2xl border bg-card text-card-foreground shadow-sm p-1">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-semibold">{{ t('item.fast_opening') }}</h2>
                    <p class="text-sm text-muted-foreground">{{ t('item.remove_unwanted_items') }}</p>
                </div>

                <div class="rounded-xl border bg-card shadow-sm overflow-x-auto p-3 mt-1 mb-1">
                    <table class="w-full table-fixed min-w-[1000px] entry-table border-separate border-spacing-y-2">
                        <thead class="sticky top-0 bg-muted/40">
                        <tr class="rounded-xltext-muted-foreground font-semibold text-sm text-violet-500">
                            <th class="px-1 py-1 w-5 min-w-5">#</th>
                            <th class="px-1 py-1 w-40">{{ t('item.item') }}</th>
                            <th class="px-1 py-1 w-36">{{ t('item.batch') }}</th>
                            <th class="px-1 py-1 w-28">{{ t('item.quantity') }}</th>
                            <th class="px-1 py-1 w-44">{{ t('item.expire_date') }}</th>
                            <th class="px-1 py-1 w-40">{{ t('item.cost') }}</th>
                            <th class="px-1 py-1 w-44">{{ t('admin.store.store') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.actions') }}</th>
                        </tr>
                        </thead>

                        <tbody>
                        <tr v-for="(item, index) in form.items" :key="item._key" class="border-t">
                            <td class="px-1 py-2">{{ index + 1 }}</td>
                            <!-- Item Name (readonly) -->
                            <td class="px-1 py-2 align-top">
                                <NextInput v-model="item.name" :disabled="true" />
                                <NextInput type="hidden" v-model="item.item_id" />
                            </td>

                            <!-- Batch -->
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    v-model="item.batch"
                                    type="text"
                                    :error="fieldError(index, 'batch')"
                                    @keyup.enter.prevent="addRowAfter(index)"
                                />
                            </td>

                            <!-- Quantity (editable) -->
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    v-model="item.quantity"
                                    type="number"
                                    inputmode="decimal"
                                    :error="fieldError(index, 'quantity')"
                                />
                            </td>
                            <!-- Expiration Date -->
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    type="date"
                                    v-model="item.expire_date"
                                    :error="fieldError(index, 'expire_date')"
                                />
                            </td>

                            <!-- Purchase Rate -->
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    type="number"
                                    v-model="item.cost"
                                    inputmode="decimal"
                                    :error="fieldError(index, 'cost')"
                                />
                            </td>

                            <!-- Store Selection -->
                            <td class="px-1 py-2 align-top">
                                <NextSelect
                                    v-model="item.store_id"
                                    :options="props.stores.data"
                                    label-key="name"
                                    value-key="id"
                                    :show-arrow="false"
                                    :error="fieldError(index, 'store_id')"
                                />
                            </td>

                            <!-- Actions -->
                            <td class="px-1 py-2 align-top">
                                <Button type="button" variant="secondary" class="text-fuchsia-500" size="sm" @click="removeRow(index)" :disabled="form.items.length === 1">−</Button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t flex items-center justify-between gap-4">
                    <div class="text-sm text-muted-foreground">
                        {{ t('general.rows') }}: <span class="font-medium text-foreground">{{ form.items.length }}</span>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ t('general.save') }}
                        </Button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
