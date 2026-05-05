<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    TableEmpty,
} from '@/Components/ui/table'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { useDebounceFn } from '@vueuse/core'
import { Search, Pencil, Check, X, Tag } from 'lucide-vue-next'

const { t } = useI18n()
const { can } = useAuth()

const props = defineProps({
    items: Object,
    filters: Object,
})

// Search
const search = ref(props.filters?.search ?? '')

const doSearch = useDebounceFn(() => {
    router.get(route('item-pricing.index'), { search: search.value || undefined }, {
        preserveState: true,
        replace: true,
    })
}, 400)

watch(search, doSearch)

// Inline edit state
const editingId = ref(null)
const editingPrice = ref('')
const saving = ref(false)

function startEdit(item) {
    editingId.value = item.id
    editingPrice.value = item.sale_price ?? ''
}

function cancelEdit() {
    editingId.value = null
    editingPrice.value = ''
}

function savePrice(item) {
    if (saving.value) return
    saving.value = true
    router.patch(route('item-pricing.update', item.id), {
        sale_price: editingPrice.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null
            editingPrice.value = ''
        },
        onFinish: () => {
            saving.value = false
        },
    })
}

function handleKeydown(event, item) {
    if (event.key === 'Enter') savePrice(item)
    if (event.key === 'Escape') cancelEdit()
}

function expiryBadgeVariant(status) {
    if (status === 'expired') return 'destructive'
    if (status === 'expiring_soon') return 'warning'
    return 'default'
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleDateString()
}

function formatNumber(val) {
    if (val === null || val === undefined || val === '') return '-'
    return Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<template>
    <AppLayout :title="t('sidebar.inventory.pricing')">
        <div class="p-4 md:p-6 space-y-4">
            <!-- Page header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <Tag class="size-5 text-muted-foreground" />
                    <h1 class="text-lg font-semibold">{{ t('sidebar.inventory.pricing') }}</h1>
                </div>

                <!-- Search -->
                <div class="relative w-full sm:w-72">
                    <Search class="absolute start-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="t('item.pricing_search_placeholder')"
                        class="w-full h-9 rounded-md border border-input bg-background ps-9 pe-3 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('general.name') }}</TableHead>
                            <TableHead>{{ t('item.code') }}</TableHead>
                            <TableHead class="text-end">{{ t('general.on_hand') }}</TableHead>
                            <TableHead class="text-end">{{ t('item.sale_price') }}</TableHead>
                            <TableHead class="text-end">{{ t('item.purchase_price') }}</TableHead>
                            <TableHead class="text-end">{{ t('item.cost') }}</TableHead>
                            <TableHead>{{ t('item.expiry_status') }}</TableHead>
                            <TableHead v-if="can('items.update')" class="w-28">{{ t('general.actions') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="!items?.data?.length" :colspan="can('items.update') ? 8 : 7">
                            {{ t('general.no_data_found') }}
                        </TableEmpty>

                        <TableRow v-for="item in items?.data" :key="item.id">
                            <!-- Name -->
                            <TableCell class="font-medium">
                                <div>{{ item.name }}</div>
                                <div v-if="item.barcode" class="text-xs text-muted-foreground">{{ item.barcode }}</div>
                            </TableCell>

                            <!-- Code -->
                            <TableCell class="text-muted-foreground">{{ item.code || '-' }}</TableCell>

                            <!-- On Hand -->
                            <TableCell class="text-end tabular-nums">{{ formatNumber(item.on_hand) }}</TableCell>

                            <!-- Sale Price (inline edit) -->
                            <TableCell class="text-end">
                                <div v-if="editingId === item.id" class="flex items-center justify-end gap-1">
                                    <input
                                        v-model="editingPrice"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-28 h-8 rounded-md border border-input bg-background px-2 text-sm text-end shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        @keydown="handleKeydown($event, item)"
                                        autofocus
                                    />
                                    <Button size="icon" variant="ghost" class="size-7 text-green-600 hover:text-green-700" :disabled="saving" @click="savePrice(item)">
                                        <Check class="size-4" />
                                    </Button>
                                    <Button size="icon" variant="ghost" class="size-7 text-muted-foreground" @click="cancelEdit">
                                        <X class="size-4" />
                                    </Button>
                                </div>
                                <span v-else class="tabular-nums">{{ formatNumber(item.sale_price) }}</span>
                            </TableCell>

                            <!-- Purchase Price -->
                            <TableCell class="text-end tabular-nums">{{ formatNumber(item.purchase_price) }}</TableCell>

                            <!-- Avg Cost -->
                            <TableCell class="text-end tabular-nums">{{ formatNumber(item.avg_cost) }}</TableCell>

                            <!-- Expiry Status -->
                            <TableCell>
                                <template v-if="!item.is_expiry_tracked">
                                    <span class="text-muted-foreground text-xs">-</span>
                                </template>
                                <template v-else-if="item.expiry_status === 'expired'">
                                    <Badge variant="destructive">{{ t('item.expired') }}</Badge>
                                    <div class="text-xs text-muted-foreground mt-0.5">{{ formatDate(item.earliest_expiry) }}</div>
                                </template>
                                <template v-else-if="item.expiry_status === 'expiring_soon'">
                                    <Badge class="bg-amber-100 text-amber-800 hover:bg-amber-100 border-amber-300">{{ t('item.expiring_soon') }}</Badge>
                                    <div class="text-xs text-muted-foreground mt-0.5">{{ formatDate(item.earliest_expiry) }}</div>
                                </template>
                                <template v-else-if="item.expiry_status === 'ok'">
                                    <Badge variant="secondary">{{ formatDate(item.earliest_expiry) }}</Badge>
                                </template>
                                <template v-else>
                                    <span class="text-muted-foreground text-xs">-</span>
                                </template>
                            </TableCell>

                            <!-- Actions -->
                            <TableCell v-if="can('items.update')">
                                <Button
                                    v-if="editingId !== item.id"
                                    size="sm"
                                    variant="outline"
                                    class="h-7 text-xs gap-1"
                                    @click="startEdit(item)"
                                >
                                    <Pencil class="size-3" />
                                    {{ t('item.update_price') }}
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <div v-if="items?.last_page > 1" class="flex items-center justify-between text-sm text-muted-foreground">
                <span>
                    {{ t('item.showing_results', { from: items.from, to: items.to, total: items.total }) }}
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in items.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="[
                            'px-3 py-1 rounded-md border text-sm transition-colors',
                            link.active
                                ? 'bg-primary text-primary-foreground border-primary'
                                : 'bg-background hover:bg-muted border-input',
                            !link.url ? 'pointer-events-none opacity-50' : '',
                        ]"
                        preserve-scroll
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
