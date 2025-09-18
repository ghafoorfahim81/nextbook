<template>
    <div class="space-y-4">
        <!-- Search and Per Page -->
        <div class="flex items-center justify-between">
            <div class="flex relative w-full max-w-sm">
                <div class="flex justify-items-start">
                    <h1 :class="isRTL ? 'text-lg font-semibold ml-2 text-nowrap mt-1' : 'text-lg font-semibold mr-2 text-nowrap mt-1'">{{ props.title }}</h1>
                </div>
                <div class="flex relative gap-4">
                    <Input
                        id="search"
                        v-model="search"
                        @input="debouncedSearch"
                        type="text"
                        :placeholder="`${t('datatable.search')} ${props.title ? props.title : ''}`"
                        :class="isRTL ? 'pl-8 w-72 pr-10' : 'pl-8 w-72 pr-20'"
                    />
                    <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
                        <Search class="size-4 text-muted-foreground" />
                    </span>
                    <button
                        v-if="search"
                        class="absolute end-0 inset-y-0 flex items-center justify-center px-2 rtl:px-8 text-muted-foreground hover:text-foreground"
                        @click="clearSearch"
                    >
                        <CircleX class="size-4 mr-9" />
                    </button>
                    <button
                        class="absolute end-0 inset-y-0 flex items-center justify-center px-2 text-muted-foreground hover:text-foreground"
                        @click="clearSearch"
                    >
                        <SlidersHorizontal class="size-4 mr-2" />
                    </button>
                </div>
            </div>

            <!-- Right actions (Add New) -->
            <div class="ml-4 flex items-center gap-2" v-if="showAddButton">
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
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead v-for="column in derivedColumns" :key="column.key">
                        <div :class="isRTL ? 'flex items-center space-x-reverse space-x-1' : 'flex items-center space-x-1'">
                            <span>{{ column.label }}</span>
                            <div v-if="column.sortable">
                                <Button variant="ghost" size="sm" @click="sort(column.key)">
                                    <ChevronUp v-if="sortField === column.key && sortDirection === 'asc'" class="h-4 w-4" />
                                    <ChevronDown v-else-if="sortField === column.key && sortDirection === 'desc'" class="h-4 w-4" />
                                    <ChevronDown v-else class="h-4 w-4 opacity-50" />
                                </Button>
                            </div>
                        </div>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="(item, rowIndex) in items.data" :key="item.id">
                    <TableCell v-for="column in derivedColumns" :key="column.key">
                        <template v-if="column.key === '__index'">
                            {{ getRowNumber(rowIndex) }}
                        </template>
                        <template v-else-if="column.key === 'actions'">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon">
                                        <Ellipsis class="w-5 h-5 text-muted-foreground" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent class="w-48 rtl:text-right" side="bottom" :align="isRTL ? 'end' : 'start'">
                                    <DropdownMenuLabel class="rtl:text-right">{{ t('datatable.actions') }}</DropdownMenuLabel>
                                    <DropdownMenuItem :class="isRTL ? 'flex-row-reverse gap-2' : 'gap-2'" @click="$emit('edit', item)"><SquarePen /> {{ t('datatable.edit') }}</DropdownMenuItem>
                                    <DropdownMenuItem :class="isRTL ? 'flex-row-reverse gap-2' : 'gap-2'" @click="$emit('delete', item.id)"><Trash2 /> {{ t('datatable.delete') }}</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </template>
                        <template v-else>
                            {{ getNestedValue(item, column.key) }}
                        </template>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <!-- Pagination -->
        <div class="border-t pt-3 flex items-center justify-between">
            <!-- Left: Showing X - Y of Z [title] -->
            <div class="text-sm text-muted-foreground">
                {{ t('datatable.showing', { from: items.meta.from, to: items.meta.to, total: items.total }) }}
                <template v-if="props.title"> {{ props.title.toLowerCase() }}</template>
            </div>

            <!-- Right: Rows per page | First Prev  Page X of Y  Next Last -->
            <div :class="isRTL ? 'flex items-center space-x-reverse space-x-2' : 'flex items-center space-x-2'">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <span>{{ t('datatable.per_page') }}:</span>
                    <Select v-model="perPage" @update:modelValue="updatePerPage">
                        <SelectTrigger class="w-[90px] h-8">
                            <SelectValue :placeholder="t('datatable.per_page')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in pageOptions" :key="option" :value="option">
                                {{ option }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- First -->
                <Button
                    variant="outline"
                    size="icon"
                    class="w-8 h-8"
                    :disabled="currentPage <= 1"
                    @click="changePage(1)"
                >
                    <component :is="isRTL ? ChevronsRight : ChevronsLeft" class="h-4 w-4" />
                </Button>

                <!-- Prev -->
                <Button
                    variant="outline"
                    size="icon"
                    class="w-8 h-8"
                    :disabled="currentPage <= 1"
                    @click="changePage(currentPage - 1)"
                >
                    <component :is="isRTL ? ChevronRight : ChevronLeft" class="h-4 w-4" />
                </Button>

                <span class="mx-2 text-sm text-muted-foreground">
                    Page {{ currentPage }} of {{ lastPage }}
                </span>

                <!-- Next -->
                <Button
                    variant="outline"
                    size="icon"
                    class="w-8 h-8"
                    :disabled="currentPage >= lastPage"
                    @click="changePage(currentPage + 1)"
                >
                    <component :is="isRTL ? ChevronLeft : ChevronRight" class="h-4 w-4" />
                </Button>

                <!-- Last -->
                <Button
                    variant="outline"
                    size="icon"
                    class="w-8 h-8"
                    :disabled="currentPage >= lastPage"
                    @click="changePage(lastPage)"
                >
                    <component :is="isRTL ? ChevronsLeft : ChevronsRight" class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import { useI18n } from 'vue-i18n'
import AddNewButton from '@/Components/next/AddNewButton.vue'

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
    Trash2, ChevronsLeft, ChevronLeft, ChevronRight, ChevronsRight,
} from 'lucide-vue-next'
import {
    DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
} from '@/Components/ui/dropdown-menu'

const props = defineProps({
    items: Object,
    columns: Array,
    url: String,
    title: String,
    filters: Object,
    // Controls for AddNewButton in header
    showAddButton: { type: Boolean, default: false },
    addTitle: { type: String, default: null },
    addAction: { type: String, default: 'modal' },
    addRoute: { type: String, default: null },
    addRouteParams: { type: Object, default: () => ({}) },
})

const { t, locale } = useI18n()
const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))
// Declare emits for clarity
defineEmits(['edit', 'delete', 'add'])

const pageOptions = [10, 20, 50, 100]
const search = ref(props.filters?.search || '')
const perPage = ref(props.filters?.perPage || 10)
const sortField = ref(props.filters?.sortField || 'id')
const sortDirection = ref(props.filters?.sortDirection || 'asc')
const currentPage = computed(() => props.items?.meta?.current_page ?? 1)
const lastPage = computed(() => props.items?.meta?.last_page ?? 1)

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
        },
        { preserveState: true, preserveScroll: true }
    )
}

const clearSearch = () => {
    search.value = ''
    updateFilters()
}

// Row number: starts at 1 and increments across pages
const getRowNumber = (rowIndex) => {
    const current = props.items?.meta?.current_page ?? 1
    const per = props.items?.meta?.per_page ?? (perPage.value || 10)
    return (current - 1) * per + rowIndex + 1
}
</script>
