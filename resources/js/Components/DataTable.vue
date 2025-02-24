<!-- resources/js/Components/DataTable.vue -->
<template>
    <div class="space-y-4">
        <!-- Search and Per Page Controls -->
        <div class="flex items-center justify-between">
            <div class="flex relative w-full max-w-sm items-center">
                <Input
                    id="search"
                    v-model="search"
                    @input="debouncedSearch"
                    type="text"
                    placeholder="Search..."
                    class="pl-8 pr-10"
                />
                <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
                <Search class="size-4 text-muted-foreground" />
                </span>
                <button
                    v-if="search"
                    class="absolute end-0 inset-y-0 flex items-center justify-center px-2 text-muted-foreground hover:text-foreground"
                    @click="clearSearch"
                >
                    <X class="size-5" />
                </button>
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
                                <ArrowUpDown class="h-4 w-4" />
                            </Button>
                        </div>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="item in items.data" :key="item.id">
                    <TableCell v-for="column in columns" :key="column.key">
                        {{ item[column.key] }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <!-- Pagination -->
        <div class="flex items-center justify-between">
            <div class="text-sm text-muted-foreground">
                Showing {{ items.from }} to {{ items.to }} of {{ items.total }} entries
            </div>
            <div class="flex space-x-2">
                <Button
                    :disabled="!items.prev_page_url"
                    @click="changePage(items.current_page - 1)"
                >
                    Previous
                </Button>
                <Button
                    :disabled="!items.next_page_url"
                    @click="changePage(items.current_page + 1)"
                >
                    Next
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Search, X } from 'lucide-vue-next'
import { router } from "@inertiajs/vue3";
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

import { ArrowUpDown } from 'lucide-vue-next'

const props = defineProps({
    items: Object,
    columns:Object,
    filters: Array,
})

console.log('props data is ',props.columns)



const pageOptions = [5, 10, 20, 50]
const search = ref(props.filters.search)
const perPage = ref(props.filters.perPage)
const sortField = ref(props.filters.sortField)
const sortDirection = ref(props.filters.sortDirection)

const debouncedSearch = debounce(() => {
    updateFilters()
}, 300)

const updateFilters = () => {
    router.get(
        route('departments.index'),
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
        route('departments.index'),
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
