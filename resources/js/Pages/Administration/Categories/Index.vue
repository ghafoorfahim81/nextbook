<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import CreateEditModal from '@/Pages/Administration/Categories/CreateEditModal.vue';

import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
} from '@/components/ui/dropdown-menu'
import { EllipsisVertical } from 'lucide-vue-next'



const labels = [
    'feature',
    'bug',
    'enhancement',
    'documentation',
    'design',
    'question',
    'maintenance',
]

const labelRef = ref('feature')
const open = ref(false)

const isDialogOpen = ref(false);

const props = defineProps({
    categories: Object,
});

const columns = ref([
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Name',sortable: true },
    {
        key: 'parent.name',
        label: 'Parent',
        sortable: true,
        render: (row) => row.parent?.name ?? '-',
    },
    { key: 'actions', label: 'Actions' },
]);

const editItem = (item) => {
    // isDialogOpen.value = true;
    console.log('hiiiiiiii eidit')
};

const deleteItem = (item) => {
    $inertia.delete(route('categories.destroy', item.id));
    console.log('hiiiiiiii eidit')

};
</script>

<template>
    <AppLayout title="Categories">

 

        <div class="flex gap-2 items-center mb-4">
            <div class="ml-auto gap-3">
                <Button
                    @click="isDialogOpen = true"
                    variant="outline"
                    class="bg-gray-100 hover:bg-gray-200 dark:border-gray-50 dark:text-green-300"
                >
                    Add New
                </Button>
                <CreateEditModal
                    :isDialogOpen="isDialogOpen"
                    @update:isDialogOpen="isDialogOpen = $event"
                    @saved="() => $inertia.reload()"
                />
            </div>
        </div>
        <DataTable
            :items="categories"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            :title="`Categories`"
            :url="`categories.index`"
        />
    </AppLayout>
</template>
