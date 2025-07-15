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
const selectedBranch = ref(null)

const isDialogOpen = ref(false);
const fetchAccountTypes = () => {
    router.reload({ only: ['items'] }) // Refresh the department list
}

const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    items: Object,
})
console.log(props.items.data);

const columns = ref([
    { key: 'id', label: 'ID', class: 'w-10' },
    { key: 'name', label: 'Name' },
    { key: 'remark', label: 'Remark' },
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
                    :categories="items"
                    @update:isDialogOpen="isDialogOpen = $event"
                />
            </div>
        </div>
        <DataTable :items="items" :columns="columns" :title="`Account Types`" :url="`account-types.index`" />

    </AppLayout>
</template>
