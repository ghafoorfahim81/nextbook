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
                        :placeholder="'Search ' + (props.title ? props.title.charAt(0).toLowerCase() + props.title.slice(1).toLowerCase() : 'items')"
                        :class="isRTL ? 'pl-8 w-72 pr-10' : 'pl-8 w-72 pr-20'"
                    />
                    <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
                        <Search class="size-4 text-muted-foreground" />
                    </span>
                    <button
                        v-if="search"
                        class="absolute end-0 inset-y-0 flex items-center justify-center px-2 text-muted-foreground hover:text-foreground"
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

            <div :class="isRTL ? 'flex items-center space-x-reverse space-x-2' : 'flex items-center space-x-2'">
                <Select v-model="perPage" @update:modelValue="updatePerPage">
                    <SelectTrigger class="w-[100px]">
                        <SelectValue placeholder="Per page" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in pageOptions" :key="option" :value="option">
                            {{ option }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Table -->
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead v-for="column in columns" :key="column.key">
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
                <TableRow v-for="item in items.data" :key="item.id">
                    <TableCell v-for="column in columns" :key="column.key">
                        <template v-if="column.key === 'actions'">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon">
                                        <Ellipsis class="w-5 h-5 text-muted-foreground" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent class="w-48 rtl:text-right" side="bottom" :align="isRTL ? 'end' : 'start'">
                                    <DropdownMenuLabel class="rtl:text-right">Actions</DropdownMenuLabel>
                                    <DropdownMenuItem :class="isRTL ? 'flex-row-reverse gap-2' : 'gap-2'" @click="$emit('edit', item)"><SquarePen /> Edit</DropdownMenuItem>
                                    <DropdownMenuItem :class="isRTL ? 'flex-row-reverse gap-2' : 'gap-2'" @click="$emit('delete', item.id)"><Trash2 /> Delete</DropdownMenuItem>
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
        <div class="flex items-center justify-between">
            <div class="text-sm text-muted-foreground">
                Showing {{ items.meta.from }} to {{ items.meta.to }} of {{ items.total }} entries
            </div>
            <div :class="isRTL ? 'flex space-x-reverse space-x-2' : 'flex space-x-2'">
                <Button v-if="!isRTL" :disabled="!items.links.prev" @click="changePage(items.meta.current_page - 1)">Previous</Button>
                <Button v-if="!isRTL" :disabled="!items.links.next" @click="changePage(items.meta.current_page + 1)">Next</Button>

                <!-- RTL: Swap button order and labels -->
                <Button v-if="isRTL" :disabled="!items.links.next" @click="changePage(items.meta.current_page + 1)">Previous</Button>
                <Button v-if="isRTL" :disabled="!items.links.prev" @click="changePage(items.meta.current_page - 1)">Next</Button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import { useI18n } from 'vue-i18n'

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
    Search, CircleX, ChevronUp, ChevronDown, SlidersHorizontal, Ellipsis,SquarePen, Trash,
    Trash2
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
})

const { locale } = useI18n()
const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))

const pageOptions = [5, 10, 20, 50]
const search = ref(props.filters?.search || '')
const perPage = ref(props.filters?.perPage || 10)
const sortField = ref(props.filters?.sortField || 'id')
const sortDirection = ref(props.filters?.sortDirection || 'asc')

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
</script>
