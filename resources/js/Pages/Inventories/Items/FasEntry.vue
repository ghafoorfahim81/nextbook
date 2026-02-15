<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { useToast } from '@/Components/ui/toast/use-toast'
import { useI18n } from 'vue-i18n';
const { t } = useI18n()
import { useSidebar } from '@/Components/ui/sidebar/utils';
import { h, ref, watch, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
    stores: { type: [Array, Object], required: true },
    unitMeasures: { type: [Array, Object], required: true },
    brands: { type: [Array, Object], required: true },
    maxCode: { type: Number, required: true },
})

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



const { toast } = useToast()
const stores        = computed(() => props.stores?.data ?? props.stores ?? [])
const unitMeasures  = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])

// Track the latest max code on the client so we can keep incrementing
const currentMaxCode = ref(Number(props.maxCode))

// Format code with leading zeros based on the number
const formatCode = (number) => {
    const num = Number(number);
    if (num < 10) {
        return num.toString().padStart(3, '0'); // 001-009
    } else if (num < 100) {
        return num.toString().padStart(3, '0'); // 010-099
    } else {
        return num.toString(); // 100+
    }
}

const blankRow = (code = currentMaxCode.value) => ({
    name: '',
    code: formatCode(code),
    measure_id: null,
    purchase_price: '',
    sale_price: '',
    batch: '',
    expire_date: '',
    quantity: '',
    store_id: null,
})

const addRow = () => {
    const lastIndex = form.items.length - 1;
    addRowAfter(lastIndex)
}

const form = useForm({
    // Initialize 6 rows with auto-incrementing codes based on currentMaxCode
    items: Array.from({ length: 6 }, (_, idx) => blankRow(currentMaxCode.value + idx)),
})

const fieldError = (idx, field) => form.errors?.[`items.${idx}.${field}`]

const addRowAfter = (idx) => {
    // only add when user is on the last row (good UX), but feel free to remove this guard

    if (idx === form.items.length - 1) {
        // Get the code from the previous row and increment by 1
        const previousCode = form.items[idx].code || 0;
        const newCode = Number(previousCode) + 1;
        const formattedCode = formatCode(newCode);
        const newRow = { ...blankRow(), code: formattedCode };
        form.items.push(newRow);
    }
}

const removeRow = (idx) => {
    if (form.items.length <= 1) return
    form.items.splice(idx, 1)
    // Renumber codes for all rows from idx onwards
    for (let i = idx; i < form.items.length; i++) {
        const prevCode = i === 0 ? props.maxCode - 1 : Number(form.items[i - 1].code)
        form.items[i].code = formatCode(prevCode + 1)
    }
}

// Check if a row is effectively empty (ignoring auto code)
const isEmptyRow = (row) => {
    const keysToCheck = [
        'name',
        'measure_id',
        'quantity',
        'batch',
        'expire_date',
        'store_id',
        'purchase_price',
        'sale_price',
    ]

    return !keysToCheck.some((key) => {
        const value = row[key]
        return value !== '' && value !== null && value !== undefined
    })
}

// Return a normalized copy of items without mutating the UI rows
const normalize = () => {
    return form.items
        .map(r => ({
            ...r,
            quantity: r.quantity === '' ? null : Number(r.quantity),
            purchase_price: r.purchase_price === '' ? null : Number(r.purchase_price),
            sale_price: r.sale_price === '' ? null : Number(r.sale_price),
            category_id: r.category_id ?? null,
            measure_id: r.measure_id ?? null,
            company_id: r.company_id ?? null,
            store_id: r.store_id ?? null,
            expire_date: r.expire_date || null,
        }))
        // drop rows that are effectively empty (ignore auto code)
        .filter(r => !isEmptyRow(r))
}


const notifySound = (type) => {
    if(type === 'success') {
        const sound = new Audio('/notify_sounds/filling-your-inbox.mp3');
        sound.play().catch(error => console.error('Error playing sound:', error));
    }
    else {
        const sound = new Audio('/notify_sounds/glass-breaking.mp3');
        sound.play().catch(error => console.error('Error playing sound:', error));
    }
}

const handleSubmit = () => {
    const normalizedItems = normalize()

    // If everything is empty, there is nothing to submit
    if (!normalizedItems.length) {
        toast({
            title: t('general.error'),
            variant: 'error',
            description: t('general.no_data_to_save') ?? 'Nothing to save. Please fill at least one row.',
        })
        return
    }

    // ensure items is a clean array; drop helper keys and send only non-empty rows
    form.transform(() => ({
        items: normalizedItems.map(({ _key, ...r }) => r)
    }))

    const itemCount = normalizedItems.length;
    form.post(route('item.fast.store'), {
        preserveScroll: true,
        // preserveState: true, // optional
        onSuccess: () => {
            // Update currentMaxCode based on the highest code we just saved
            const highestCode = normalizedItems.reduce((max, item) => {
                const codeNum = Number(item.code) || 0
                return codeNum > max ? codeNum : max
            }, currentMaxCode.value)

            currentMaxCode.value = highestCode + 1

            notifySound('success');
            toast({
                title: t('general.success'),
                variant: 'success',
                description: itemCount + ' ' + t('item.items') + ' ' + t('general.created_successfully'),
            });

            // Rebuild the items array so new rows start after the latest saved code
            form.items = Array.from({ length: 6 }, (_, idx) => blankRow(currentMaxCode.value + idx))
            form.clearErrors()
        },
        onError: () => {
            notifySound('error');
            toast({
                title: t('general.error'),
                variant: 'error',
                description: t('general.create_error_message'),
            });
        }

    })
}

</script>

<template>
    <AppLayout :title="t('item.fast_entry')" :sidebar-collapsed="true">
        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="rounded-2xl border bg-card text-card-foreground shadow-sm p-1 border-primary">
                <div class="p-4 border-b flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ t('item.fast_entry') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ t('item.add_multiple_items_quickly') }}</p>
                    </div>
                    <ModuleHelpButton module="fast_entry" positionClass="" class="shrink-0" />
                </div>

                <div class="rounded-xl border border-violet-400 bg-card shadow-sm overflow-x-auto p-3 mt-1 mb-1">
                    <table class="w-full table-fixed min-w-[1000px]">
                        <thead class="sticky top-0 bg-muted/40">
                        <tr class="rounded-xltext-muted-foreground font-semibold text-sm text-white bg-primary">
                            <th class="px-1 py-1 w-5 min-w-5">#</th>
                            <th class="px-1 py-1 w-36">{{ t('general.name') }}</th>
                            <th class="px-1 py-1 w-20">{{ t('item.code') }}</th>
                            <th class="px-1 py-1 w-28">{{ t('admin.unit_measure.unit_measure') }}</th>
                            <th class="px-1 py-1 w-20">{{ t('item.quantity') }}</th>
                            <th class="px-1 py-1 w-20">{{ t('item.batch') }}</th>
                            <th class="px-1 py-1 w-36">{{ t('item.expire_date') }}</th>
                            <th class="px-1 py-1 w-32">{{ t('admin.store.store') }}</th>
                            <th class="px-1 py-1 w-28">{{ t('item.purchase_price') }}</th>
                            <th class="px-1 py-1 w-20">{{ t('item.sale_price') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.actions') }}</th>
                        </tr>
                        </thead>

                        <tbody>
                        <tr v-for="(item, index) in form.items" :key="item._key" class="border-t">
                            <td class="px-1 py-2 align-top w-5 tex-center">{{ index + 1 }}</td>

                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    v-model="item.name"
                                    :error="fieldError(index, 'name')"
                                    @click="addRowAfter(index)"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    v-model="item.code"
                                    disabled="true"
                                    :error="fieldError(index, 'code')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextSelect
                                    v-model="item.measure_id"
                                    :options="unitMeasures"
                                    label-key="name"
                                    :show-arrow="false"
                                    value-key="id"
                                    id="measure"
                                    :error="fieldError(index, 'measure_id')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    type="number"
                                    inputmode="decimal"
                                    v-model="item.quantity"
                                    :error="fieldError(index, 'quantity')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    v-model="item.batch"
                                    :error="fieldError(index, 'batch')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextDate
                                    v-model="item.expire_date"
                                    popover="top-left"
                                    :error="fieldError(index, 'expire_date')"
                                    :placeholder="t('general.enter', { text: t('item.expire_date') })"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextSelect
                                    v-model="item.store_id"
                                    :options="stores"
                                    label-key="name"
                                    value-key="id"
                                    :show-arrow="false"
                                    append-to-body
                                    :error="fieldError(index, 'measure_id')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    type="number"
                                    inputmode="decimal"
                                    v-model="item.purchase_price"
                                    :error="fieldError(index, 'purchase_price')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <NextInput
                                    type="number"
                                    inputmode="decimal"
                                    v-model="item.sale_price"
                                    :error="fieldError(index, 'sale_price')"
                                />
                            </td>
                            <td class="px-1 py-2 align-top">
                                <!-- <Trash2 class="w-4 h-4 cursor-pointer text-fuchsia-500 inline" @click="deleteRow(index)" /> -->
                                    <Button type="button" variant="secondary" class="text-fuchsia-500 text-center" size="sm" @click="removeRow(index)" :disabled="form.items.length === 1">−</Button>
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
                        <Button type="button" variant="secondary" @click="addRow()" >
                            {{ t('general.add_row') }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ t('general.save') }}
                        </Button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
<style scoped>
.entry-table thead {
    border: 2px solid hsl(var(--border));
    border-radius: 8px;
}

.entry-table thead th {
    border-bottom: 1px solid hsl(var(--border));
    padding: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
}

</style>
