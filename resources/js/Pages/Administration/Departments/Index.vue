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

const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    items: Object,
})
// const  data = props.data.data
// const links = props.data.links;
// console.log('this is data',props.data.links)

const columns = ref([
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Name' },
    { key: 'code', label: 'Code' },
    { key: `parent.name`, label: 'Parent',
        render: (row) => row.parent?.name,},
    // Add more columns as needed
])
</script>

<template>
    <AppLayout title="Designations">
        <div class="flex gap-2 items-center">
            <div class="ml-auto gap-3">
                <PlusButton :onClick="openModal"/>
                <CreateEditModal v-model:show="showModal" />
            </div>
        </div>
        <DataTable :items="items" :columns="columns" :url="`departments.index`" />

    </AppLayout>
</template>
