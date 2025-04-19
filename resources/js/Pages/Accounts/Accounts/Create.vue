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

</script>

<template>
    <AppLayout title="Designations">
        <div class="flex gap-2 items-center">
            <div class="grid grid-cols-3 gap-4">
                <Input  placeholder="Name" />
                <Input  placeholder="Code" />
                <Input  placeholder="Remark" />
                <Button @click="isModalOpen = true" variant="outline" class="bg-gray-100 hover:bg-gray-200 dark:border-gray-50 dark:text-green-300">Add New</Button>
            </div>
        </div>
    </AppLayout>
</template>
