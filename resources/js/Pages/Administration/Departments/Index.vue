<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import PlusButton from '@/Components/ui/button/PlusButton.vue';
import { ArrowUpDown } from 'lucide-vue-next'
import  DropdownAction  from '@/Components/DataTableDropdown.vue';

import CreateEditModal from '@/Pages/Administration/Departments/CreateEditModal.vue';
import Dialog from "@/Components/next/Dialog.vue";
const isModalOpen = ref(false)
const selectedDepartment = ref(null)

const isDialogOpen = ref(false);
const fetchDepartments = () => {
    router.reload({ only: ['items'] }) // Refresh the department list
}

const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    items: Object,
})

const columns = ref([
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Name' },
    { key: 'code', label: 'Code' },
    { key: `parent.name`, label: 'Parent',
        render: (row) => row.parent?.name,},
])
</script>

<template>
    <AppLayout title="Designations">
        <div class="flex gap-2 items-center">
            <div class="ml-auto gap-3">
                <Button  @click="isDialogOpen = true" variant="outline" class="bg-gray-100
                hover:bg-gray-200 dark:border-gray-50 dark:text-green-300">Add New</Button>
                <CreateEditModal
                    :isDialogOpen="isDialogOpen"
                    :departments="items"
                    @update:isDialogOpen="isDialogOpen = $event"
                />
            </div>
        </div>
        <DataTable :items="items" :columns="columns" :title="`Departments`" :url="`departments.index`" />

    </AppLayout>
</template>
