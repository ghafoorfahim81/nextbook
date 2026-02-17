<template>
    <div class="space-y-4">
        <!-- Search and Per Page -->
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center">
                <div class="flex justify-items-start">
                    <h1 :class="isRTL ? 'text-lg font-semibold ml-2 text-nowrap mt-1 text-primary' : 'text-lg font-semibold mr-2 text-nowrap mt-1 text-primary'">{{ props.title }}</h1>
                </div>
                <div class="relative w-full max-w-full sm:max-w-md">
                    <Input
                        id="search"
                        v-model="search"
                        @input="debouncedSearch"
                        type="text"
                        :placeholder="`${t('datatable.search')} ${props.title ? props.title : ''}`"
                        :class="isRTL ? 'pl-8 w-full pr-10 border-1 text-primary' : 'pl-8 w-full pr-20 text-primary focus:text-primary/80'"
                    />
                    <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
                        <Search class="size-4 text-primary hover:text-primary/80" />
                    </span>
                    <div class="absolute end-0 inset-y-0 flex items-center gap-1 pr-2 rtl:pl-2">
                        <button
                            v-if="search"
                            type="button"
                            class="flex items-center justify-center text-primary"
                            @click="clearSearch"
                        >
                            <CircleX class="size-4" />
                        </button>

                        <Popover v-if="hasAdvancedFilters" v-model:open="filtersOpen">
                            <PopoverTrigger as-child>
                                <button
                                    type="button"
                                    class="flex items-center justify-center text-primary"
                                    @click="openFilters"
                                >
                                    <SlidersHorizontal
                                        class="size-4"
                                        :class="isAdvancedFiltering ? 'text-primary' : 'text-primary/70 hover:text-primary'"
                                    />
                                </button>
                            </PopoverTrigger>
                            <PopoverContent
                                :align="isRTL ? 'end' : 'start'"
                                side="bottom"
                                :sideOffset="8"
                                class="w-[calc(100vw-2rem)] max-w-[720px] p-5 bg-background shadow-lg"
                            >
                                <DataTableFilterPanel
                                    v-model="advancedFilters"
                                    :title="`${t('general.search')} ${props.title ? props.title : ''}`"
                                    :fields="props.filterFields"
                                    @apply="() => { applyAdvancedFilters(); filtersOpen.value = false }"
                                    @clear="() => { clearAdvancedFilters(); filtersOpen.value = false }"
                                />
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>
            </div>

            <!-- Right actions (Add New) -->
            <div class="flex items-center gap-2 lg:ml-4" v-if="can(`${props.can}.create`)">
                <AddNewButton
                    :title="addTitle"
                    :action="addAction"
                    :route="addRoute"
                    :routeParams="addRouteParams"
                    variant="default"
                    class="bg-primary text-white"
                    @modal-open="$emit('add')"
                    @redirect="$emit('add')"
                />
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto rounded-md border border-primary">
            <Table class="min-w-[720px]">
                <TableHeader class="bg-violet-500">
                    <TableRow class="bg-primary hover:bg-purple-500 h-8 text-white">
                        <TableHead v-for="column in derivedColumns" :key="column.key" class="h-8 py-1 px-3">
                            <div :class="isRTL ? 'flex items-center space-x-reverse space-x-1 text-white' : 'flex items-center space-x-1 text-white text-nowrap'">
                                <span class="text-sm font-medium">{{ column.label }}</span>
                                <div v-if="column.sortable">
                                    <Button variant="ghost" size="sm" @click="sort(column.key)" class="h-5 w-5 p-0">
                                        <ChevronUp v-if="sortField === column.key && sortDirection === 'asc'" class="h-3 w-3" />
                                        <ChevronDown v-else-if="sortField === column.key && sortDirection === 'desc'" class="h-3 w-3" />
                                        <ChevronDown v-else class="h-3 w-3 opacity-50" />
                                    </Button>
                                </div>
                            </div>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <!-- Empty State Message -->
                    <TableRow v-if="isEmpty">
                        <TableCell :colspan="derivedColumns.length" class="h-48 text-center">
                            <div class="flex flex-col items-center justify-center space-y-4 py-8">
                                <div class="rounded-full bg-violet-100 p-4">
                                    <FileX class="h-8 w-8 text-violet-600" />
                                </div>
                                <div class="space-y-1">
                                    <h3 class="text-base font-semibold">{{ t('general.no_record_available') }}</h3>
                                    <p class="text-xs text-gray-500 max-w-sm">
                                        {{ t('general.no_record_available_description') }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                    </TableRow>
                    <!-- Data Rows -->
                    <TableRow v-else v-for="(item, rowIndex) in items.data" :key="item.id" class="h-11 hover:bg-muted/50">
                        <TableCell
                            v-for="column in derivedColumns"
                            :key="column.key"
                            class="py-1 px-3 text-sm"
                            :class="column.key !== 'actions' ? 'cursor-pointer' : ''"
                            @click="column.key !== 'actions' ? $emit('show', item.id) : undefined"
                        >
                            <template v-if="column.key === '__index'">
                                {{ getRowNumber(rowIndex) }}
                            </template>
                            <template v-else-if="column.key === 'actions'">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" class="h-6 w-6 hover:bg-violet-500 hover:text-white">
                                            <Ellipsis class="w-4 h-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent class="w-48 rtl:text-right" side="bottom" :align="isRTL ? 'end' : 'start'">
                                        <DropdownMenuLabel class="rtl:text-right text-xs">{{ t('datatable.actions') }}</DropdownMenuLabel>
                                        <DropdownMenuItem v-if="props.hasEdit && can(`${props.can}.update`)" :class="[isRTL ? 'flex-row-reverse gap-2' : 'gap-2', '[&:hover]:bg-violet-500 [&:hover]:text-white [&:focus]:bg-violet-500 [&:focus]:text-white text-xs py-1.5']"
                                        @click="$emit('edit', item)"><SquarePen class="h-3 w-3" /> {{ t('datatable.edit') }}
                                    </DropdownMenuItem>
                                        <DropdownMenuItem v-if="can(`${props.can}.delete`)" :class="[isRTL ? 'flex-row-reverse gap-2' : 'gap-2', '[&:hover]:bg-violet-500 [&:hover]:text-white [&:focus]:bg-violet-500 [&:focus]:text-white text-xs py-1.5']" @click="$emit('delete', item.id)"><Trash2 class="h-3 w-3" /> {{ t('datatable.delete') }}</DropdownMenuItem>
                                        <DropdownMenuItem v-if="props.hasShow && can(`${props.can}.view`)" :class="[isRTL ? 'flex-row-reverse gap-2' : 'gap-2', '[&:hover]:bg-violet-500 [&:hover]:text-white [&:focus]:bg-violet-500 [&:focus]:text-white text-xs py-1.5']" @click="$emit('show', item.id)"><Eye class="h-3 w-3" /> {{ t('datatable.show') }}</DropdownMenuItem>
                                        <DropdownMenuItem v-if="props.hasPrint && can(`${props.can}.print`)" :class="[isRTL ? 'flex-row-reverse gap-2' : 'gap-2', '[&:hover]:bg-violet-500 [&:hover]:text-white [&:focus]:bg-violet-500 [&:focus]:text-white text-xs py-1.5']" @click="$emit('print', item.id)"><Printer class="h-3 w-3" /> {{ t('datatable.print') }}</DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </template>
                            <template v-else-if="column.render">
                                {{ column.render(item) }}
                            </template>
                            <template v-else>
                                {{ getNestedValue(item, column.key) }}
                            </template>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col gap-2 border-t pt-2 sm:flex-row sm:items-center sm:justify-between">
            <!-- Left: Showing X - Y of Z [title] -->
            <div class="text-xs text-muted-foreground">
                {{ t('datatable.showing', { from: items.meta.from, to: items.meta.to, total: items.total }) }}
                <template v-if="props.title"> {{ props.title.toLowerCase() }}</template>
            </div>

            <!-- Right: Rows per page | First Prev  Page X of Y  Next Last -->
            <div :class="isRTL ? 'flex flex-wrap items-center gap-2 sm:space-x-reverse sm:space-x-2' : 'flex flex-wrap items-center gap-2 sm:space-x-2'">
                <div class="flex items-center gap-1 text-xs text-muted-foreground">
                    <span>{{ t('datatable.per_page') }}:</span>
                    <Select v-model="perPage" @update:modelValue="updatePerPage">
                        <SelectTrigger class="w-[80px] h-7 text-xs border-input">
                            <SelectValue :placeholder="t('datatable.per_page')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in pageOptions"
                                :key="option"
                                :value="option"
                                class="text-xs data-[state=checked]:bg-primary data-[state=checked]:text-white data-[highlighted]:bg-purple-500 data-[highlighted]:text-white"
                            >
                                {{ option }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- First -->
                <Button
                    variant="outline"
                    size="icon"
                    class="w-7 h-7"
                    :disabled="currentPage <= 1"
                    @click="changePage(1)"
                >
                    <component :is="isRTL ? ChevronsRight : ChevronsLeft" class="h-3 w-3" />
                </Button>

                <!-- Prev -->
                <Button
                    variant="outline"
                    size="icon"
                    class="w-7 h-7"
                    :disabled="currentPage <= 1"
                    @click="changePage(currentPage - 1)"
                >
                    <component :is="isRTL ? ChevronRight : ChevronLeft" class="h-3 w-3" />
                </Button>

                <span class="mx-2 text-xs text-muted-foreground">
                    Page {{ currentPage }} of {{ lastPage }}
                </span>

                <!-- Next -->
                <Button
                    variant="outline"
                    size="icon"
                    class="w-7 h-7"
                    :disabled="currentPage >= lastPage"
                    @click="changePage(currentPage + 1)"
                >
                    <component :is="isRTL ? ChevronLeft : ChevronRight" class="h-3 w-3" />
                </Button>

                <!-- Last -->
                <Button
                    variant="outline"
                    size="icon"
                    class="w-7 h-7"
                    :disabled="currentPage >= lastPage"
                    @click="changePage(lastPage)"
                >
                    <component :is="isRTL ? ChevronsLeft : ChevronsRight" class="h-3 w-3" />
                </Button>
            </div>
        </div>

        <!-- Filter popover is anchored to the filter icon -->
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import { useI18n } from 'vue-i18n'
import AddNewButton from '@/Components/next/AddNewButton.vue'
import DataTableFilterPanel from '@/Components/DataTableFilterPanel.vue'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'

// UI Components
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/table'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Search, CircleX, ChevronUp, ChevronDown, SlidersHorizontal, Ellipsis, SquarePen, Trash,
    Trash2, ChevronsLeft, ChevronLeft, ChevronRight, ChevronsRight, FileX,
    Eye,
    Printer,
} from 'lucide-vue-next'
import {
    DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
} from '@/Components/ui/dropdown-menu'
import { useAuth } from '@/composables/useAuth'
const { can } = useAuth();
const props = defineProps({
    can: String,
    items: Object,
    columns: Array,
    url: String,
    title: String,
    filters: Object,
    filterFields: { type: Array, default: () => [] },
    // Controls for AddNewButton in header
    showAddButton: { type: Boolean, default: false },
    addTitle: { type: String, default: null },
    addAction: { type: String, default: 'modal' },
    hasEdit:{ type: Boolean, default: true },
    hasShow:{ type: Boolean, default: false },
    hasPrint:{ type: Boolean, default: false },
    // Hide delete action when the provided key path resolves to true on the row item
    hideDeleteOnKeyTrue: { type: String, default: null },
    addRoute: { type: String, default: null },
    addRouteParams: { type: Object, default: () => ({}) },
    // Controls for empty state and rows
    emptyRowsCount: { type: Number, default: 10 },
})

const { t, locale } = useI18n()
const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))
// Declare emits for clarity
defineEmits(['edit', 'delete', 'add', 'print', 'show'])

const pageOptions = [10, 20, 50, 100]
const search = ref(props.filters?.search || '')
const perPage = ref(props.filters?.perPage || 10)
const sortField = ref(props.filters?.sortField || 'id')
const sortDirection = ref(props.filters?.sortDirection || 'asc')
const advancedFilters = ref(props.filters?.filters || {})
const filtersOpen = ref(false)
const hasAdvancedFilters = computed(() => Array.isArray(props.filterFields) && props.filterFields.length > 0)
const isAdvancedFiltering = computed(() => {
    const f = advancedFilters.value || {}
    return Object.keys(f).some((k) => f[k] !== null && f[k] !== undefined && f[k] !== '')
})
const currentPage = computed(() => props.items?.meta?.current_page ?? 1)
const lastPage = computed(() => props.items?.meta?.last_page ?? 1)

// Empty state and rows handling
const isEmpty = computed(() => !props.items?.data || props.items.data.length === 0)
const dataRowsCount = computed(() => props.items?.data?.length || 0)
const emptyRowsToShow = computed(() => {
    if (isEmpty.value) return props.emptyRowsCount
    return Math.max(0, props.emptyRowsCount - dataRowsCount.value)
})

// Derived columns: inject index column at the start
const derivedColumns = computed(() => {
    const cols = props.columns ? [...props.columns] : []
    // If no explicit index column exists, add one
    const hasIndex = cols.some(c => c.key === '__index')
    if (!hasIndex) {
        cols.unshift({ key: '__index', label: '#', sortable: false })
    }
    return cols
})

// Helper
const getNestedValue = (obj, path) => {
    if (!obj || !path) return ''
    return path.split('.').reduce((acc, part) => acc?.[part], obj)
}

// Search handling
const debouncedSearch = debounce(() => updateFilters(), 300)

const updateFilters = () => {
    router.get(
        route(props.url),
        {
            search: search.value,
            perPage: perPage.value,
            sortField: sortField.value,
            sortDirection: sortDirection.value,
            filters: advancedFilters.value,
        },
        { preserveState: true, preserveScroll: true }
    )
}

const updatePerPage = (value) => {
    perPage.value = value
    updateFilters()
}

const sort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortField.value = field
        sortDirection.value = 'asc'
    }
    updateFilters()
}

const changePage = (page) => {
    router.get(
        route(props.url),
        {
            page,
            search: search.value,
            perPage: perPage.value,
            sortField: sortField.value,
            sortDirection: sortDirection.value,
            filters: advancedFilters.value,
        },
        { preserveState: true, preserveScroll: true }
    )
}

const clearSearch = () => {
    search.value = ''
    updateFilters()
}

const openFilters = () => {
    filtersOpen.value = true
}

const applyAdvancedFilters = () => {
    // Reset to first page when applying filters
    router.get(
        route(props.url),
        {
            page: 1,
            search: search.value,
            perPage: perPage.value,
            sortField: sortField.value,
            sortDirection: sortDirection.value,
            filters: advancedFilters.value,
        },
        { preserveState: true, preserveScroll: true }
    )
}

const clearAdvancedFilters = () => {
    advancedFilters.value = {}
    applyAdvancedFilters()
}

// Row number: starts at 1 and increments across pages
const getRowNumber = (rowIndex) => {
    const current = props.items?.meta?.current_page ?? 1
    const per = props.items?.meta?.per_page ?? (perPage.value || 10)
    return (current - 1) * per + rowIndex + 1
}
 
</script>
