<script setup>
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ChevronDown, ArrowUpDown } from 'lucide-vue-next';

import {
    FlexRender,
    getCoreRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    getFilteredRowModel,
    getExpandedRowModel,
    useVueTable,
} from '@tanstack/vue-table';

import { h, ref,watch,onMounted,computed  } from 'vue';
import { valueUpdater } from '@/Lib/utils.js';
import { useDebounceFn } from "@vueuse/core";
import { router } from "@inertiajs/vue3";
const props = defineProps({
    data: { type: Array, required: true },
    columns: { type: Array, required: true },
    apiEndpoint: { type: String, required: true },
    pagination: { type: Object, required: true }
});

const tableData = ref([...props.data]); // Store table data

watch(() => props.data, (newData) => {
    console.log("🔄 Data updated:", newData);
    console.log('this is pagination props data',props.pagination)
    tableData.value = [...newData]; // ✅ Ensure reactivity
}, { deep: true, immediate: true });

const sorting = ref([]);
const columnFilters = ref([]);
const columnVisibility = ref({});
const rowSelection = ref({});
const expanded = ref({});

const currentPage = ref(props.pagination.current_page || 1);
const totalPages = ref(props.pagination.last_page || 1);
const perPage = ref(10); // Default page size

// 🔄 Watch for page changes and fetch new data
watch([currentPage, perPage], () => {
    fetchData();
});

const table = useVueTable({
    data: computed(() => tableData.value),
    columns: props.columns,

    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getExpandedRowModel: getExpandedRowModel(),
    onSortingChange: updaterOrValue => valueUpdater(updaterOrValue, sorting),
    onColumnFiltersChange: updaterOrValue => valueUpdater(updaterOrValue, columnFilters),
    onColumnVisibilityChange: updaterOrValue => valueUpdater(updaterOrValue, columnVisibility),
    onRowSelectionChange: updaterOrValue => valueUpdater(updaterOrValue, rowSelection),
    onExpandedChange: updaterOrValue => valueUpdater(updaterOrValue, expanded),
    state: {
        get sorting() {
            return sorting.value;
        },
        get columnFilters() {
            return columnFilters.value;
        },
        get columnVisibility() {
            return columnVisibility.value;
        },
        get rowSelection() {
            return rowSelection.value;
        },
        get expanded() {
            return expanded.value;
        },
    },
});


    const searchQuery = ref(""); // Store search input
    const isLoading = ref(false);


    onMounted(() => {
        const urlParams = new URLSearchParams(window.location.search);
        searchQuery.value = urlParams.get("search") || ""; // Preserve search value
    });

// 🔎 Debounced function to fetch data
const currentPage = ref(props.pagination.current_page || 1);
const totalPages = ref(props.pagination.last_page || 1);
const perPage = ref(10); // Default page size
watch(() => props.pagination, (newPagination) => {
    console.log("📌 Pagination updated:", newPagination);
    currentPage.value = newPagination.current_page;
    totalPages.value = newPagination.last_page;
}, { deep: true, immediate: true });

// 🔄 Watch for page changes and fetch new data
watch([currentPage, perPage], () => {
    fetchData();
});

// 🔎 Update API call to include pagination
const fetchData = useDebounceFn(async () => {
    isLoading.value = true;

    try {
        router.replace(props.apiEndpoint, {
            data: {
                search: searchQuery.value,
                page: currentPage.value,  // ✅ Send current page
                perPage: perPage.value // ✅ Send perPage size
            },
            preserveState: true,
            preserveScroll: true,
            only: ["data", "pagination"], // ✅ Fetch both data & pagination
            onSuccess: ({ props }) => {
                console.log("✅ New data received:", props.data);
                tableData.value = [...props.data]; // ✅ Ensure reactivity
                currentPage.value = props.pagination.current_page; // ✅ Sync current page
                totalPages.value = props.pagination.last_page; // ✅ Sync total pages
            },
        });
    } catch (error) {
        console.error("❌ Error fetching data:", error);
    } finally {
        isLoading.value = false;
    }
}, 300);


        try {
            router.replace(props.apiEndpoint, {
                data: {
                    search: searchQuery.value,
                    page: currentPage.value,  // ✅ Send current page
                    perPage: perPage.value // ✅ Send perPage size
            },
                preserveState: true,
                preserveScroll: true,
                only: ["data", "pagination"], // ✅ Ensure only data updates
                onSuccess: ({ props }) => {
                    tableData.value = [...props.data]; // ✅ Ensure reactivity
                    currentPage.value = props.pagination.current_page; // ✅ Sync current page
                    totalPages.value = props.pagination.last_page;
                },
            });
        } catch (error) {
            console.error("❌ Error fetching data:", error);
        } finally {
            isLoading.value = false;
        }
    }, 300);
    watch(searchQuery, () => {
        fetchData();
    });

    const pageSizes = ref([5, 10, 20, 50, 100]);
    watch(pageSizes, (newSize) => {
        table.setPageSize(newSize); // Dynamically update the page size
    });

</script>

<template>
    <div>
        <div class="flex gap-2 items-center py-4">
            <!-- Filter Input -->
            <Input
                class="max-w-sm"
                placeholder="Filter..."
                v-model="searchQuery"
            />

            <!-- Column Visibility Dropdown -->
            <div class="ml-auto flex gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline">
                            Page Size   <ChevronDown class="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuCheckboxItem
                            v-for="size in pageSizes"
                            :key="size"
                            :checked="table.getState().pagination.pageSize === size"
                            @update:checked="value => table.setPageSize(value ? size : table.getState().pagination.pageSize)"
                        >
                            {{ size }}
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline">
                            Columns <ChevronDown class="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuCheckboxItem
                            v-for="column in table.getAllColumns().filter(column => column.getCanHide())"
                            :key="column.id"
                            class="capitalize"
                            :checked="column.getIsVisible()"
                            @update:checked="value => column.toggleVisibility(!!value)"
                        >
                            {{ column.id }}
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead v-for="header in headerGroup.headers" :key="header.id">
                            <FlexRender
                                v-if="!header.isPlaceholder"
                                :render="header.column.columnDef.header"
                                :props="header.getContext()"
                            />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                    <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <template v-for="row in table.getRowModel().rows" :key="row.id">
                            <TableRow :data-state="row.getIsSelected() && 'selected'">
                                <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                                    <FlexRender
                                        :render="cell.column.columnDef.cell"
                                        :props="cell.getContext()"
                                    />
                                </TableCell>
                            </TableRow>
                        </template>
                    </template>
                    <TableRow v-else>
                        <TableCell :colspan="props.columns.length" class="h-24 text-center">
                            No results.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-end space-x-2 py-4">
            <div class="flex-1 text-sm text-muted-foreground">
                Page {{ currentPage }} of {{ totalPages }}
            </div>
            <div class="space-x-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="currentPage <= 1"
                    @click="currentPage--"
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="currentPage >= totalPages"
                    @click="currentPage++"
                >
                    Next
                </Button>
            </div>
        </div>

    </div>
</template>
