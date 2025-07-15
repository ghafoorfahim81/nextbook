<!-- resources/js/Components/DataTable.vue -->
<template>
    <div class="space-y-4">
        <!-- Search and Per Page Controls -->
        <div class="flex items-center justify-between">
            <div class="flex relative w-full max-w-sm">
               <div class="flex justify-items-start">
                   <h1 class="text-lg font-semibold mr-2 text-nowrap mt-1">{{props.title}}</h1>
               </div>

                <div class="flex relative gap-4">
                    <Input
                        id="search"
                        v-model="search"
                        @input="debouncedSearch"
                        type="text"
                        :placeholder="'Search ' + (props.title ? props.title.charAt(0).toLowerCase() + props.title.slice(1).toLowerCase() : 'items')"
                        class="pl-8 w-72"
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
            <div class="flex items-center space-x-2">
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
                        <div class="flex items-center space-x-1">
                            <span>{{ column.label }}</span>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="sort(column.key)"
                            >
                                <ChevronUp
                                    v-if="sortField === column.key && sortDirection === 'asc'"
                                    class="h-4 w-4"
                                />
                                <ChevronDown
                                    v-else-if="sortField === column.key && sortDirection === 'desc'"
                                    class="h-4 w-4"
                                />
                                <ChevronDown
                                    v-else
                                    class="h-4 w-4 opacity-50"
                                />
                            </Button>
                        </div>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="item in items.data" :key="item.id">
                    <TableCell v-for="column in columns" :key="column.key">
                        {{ getNestedValue(item, column.key) }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <!-- Pagination -->
        <div class="flex items-center justify-between">
            <div class="text-sm text-muted-foreground">
                Showing {{ items.meta.from }} to {{ items.meta.to }} of {{ items.total }} entries
            </div>
            <div class="flex space-x-2">
                <Button
                    :disabled="!items.links.prev"
                    @click="changePage(items.meta.current_page - 1)"
                >
                    Previous
                </Button>
                <Button
                    :disabled="!items.links.next"
                    @click="changePage(items.meta.current_page + 1)"
                >
                    Next
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Search, CircleX, ChevronUp, ChevronDown, SlidersHorizontal } from 'lucide-vue-next'
import {SliderRange} from "reka-ui";

// Helper function to get nested values
const getNestedValue = (obj, path) => {
    if (!obj || !path) return ''

    return path.split('.').reduce((acc, part) => {
        return acc ? acc[part] : ''
    }, obj)
}

const props = defineProps({
    items: Object,
    columns: Array,
    url:String,
    title:String
})

// Default values with fallbacks
const pageOptions = [5, 10, 20, 50]
const search = ref(props.filters?.search || '')
const perPage = ref(props.filters?.perPage || 10)
const sortField = ref(props.filters?.sortField || 'id')
const sortDirection = ref(props.filters?.sortDirection || 'asc')
const url = (props.url)
console.log('url issss ',props.url)
const debouncedSearch = debounce(() => {
    updateFilters()
}, 300)

const updateFilters = () => {
    router.get(
        route(url),
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
        route(url),
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
