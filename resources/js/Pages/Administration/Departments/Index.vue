<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import PlusButton from '@/Components/ui/button/PlusButton.vue';
import { ArrowUpDown } from 'lucide-vue-next'
import  DropdownAction  from '@/Components/DataTableDropdown.vue';

import CreateEditModal from '@/Pages/Designations/CreateEditModal.vue';
const showModal = ref(false);

const openModal = () => {
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};
const props = defineProps({
    data: Object,
    pagination:Object
})
const  data = props.data.data
const links = props.data.links;
console.log('this is data',props.data.links)

const columns = [
    {
        accessorKey: 'number',
        header: '#',
        cell: ({ row }) => h('div', { class: 'lowercase' }, 1),
    },
    {
        accessorKey: 'name',
        header: ({ column }) => {
            return h(Button, {
                variant: 'ghost',
                onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
            }, () => ['Name', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
        },
        cell: ({ row }) => h('div', { class: 'lowercase' }, row.getValue('name')),
    },
    {
        accessorKey: 'code',
        header: ({ column }) => {
            return h(Button, {
                variant: 'ghost',
                onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
            }, () => ['Code', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
        },
        cell: ({ row }) => h('div', { class: 'lowercase' }, row.getValue('code')),
    },
    {
        accessorKey: 'parent',
        header: ({ column }) => {
            return h(Button, {
                variant: 'ghost',
                onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
            }, () => ['Parent', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
        },
        cell: ({ row }) => h('div', { class: 'lowercase' }, row.getValue('parent')),
    },

    {
        accessorKey: 'remark',
        header: 'Remark',
        cell: ({ row }) => h('div', { class: 'lowercase' }, row.getValue('remark')),
    },
    {
        id: 'actions',
        header:'Action',
        cell: ({ row }) => {
            const payment = row.original

            return h('div', { class: 'relative' }, h(DropdownAction, {
                payment,
                onExpand: row.toggleExpanded,
            }))
        },
    },
];
</script>

<template>
    <AppLayout title="Designations">
        <div class="flex gap-2 items-center">
            <h1 class="text-lg font-semibold">Designations</h1>
            <div class="ml-auto gap-3">
                <PlusButton :onClick="openModal"/>
                <CreateEditModal v-model:show="showModal" />
            </div>
        </div>
        <DataTable :data="data" :columns="columns" :pagination="links" api-endpoint="/departments" :key="data.length" />
    </AppLayout>
</template>
