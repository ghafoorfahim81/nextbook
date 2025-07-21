<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import CreateEditModal from '@/Pages/Administration/Categories/CreateEditModal.vue';
import { MoreHorizontal } from 'lucide-vue-next'

import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

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
    { key: 'name', label: 'Name' },
    {
        key: 'parent.name',
        label: 'Parent',
        render: (row) => row.parent?.name ?? '-',
    },
]);
</script>

<template>
    <AppLayout title="Categories">

        <div class="flex w-full flex-col items-start justify-between rounded-md border px-4 py-3 sm:flex-row sm:items-center">
            <p class="text-sm font-medium leading-none">
      <span class="mr-2 rounded-lg bg-primary px-2 py-1 text-xs text-primary-foreground">
        {{ labelRef }}
      </span>
                <span class="text-muted-foreground">Create a new project</span>
            </p>
            <DropdownMenu v-model:open="open">
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="sm">
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-[200px]">
                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                    <DropdownMenuGroup>
                        <DropdownMenuItem>
                            Assign to...
                        </DropdownMenuItem>
                        <DropdownMenuItem>
                            Set due date...
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                Apply label
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent class="p-0">
                                <Command>
                                    <CommandInput
                                        placeholder="Filter label..."
                                        auto-focus
                                    />
                                    <CommandList>
                                        <CommandEmpty>No label found.</CommandEmpty>
                                        <CommandGroup>
                                            <CommandItem
                                                v-for="label in labels"
                                                :key="label"
                                                :value="label"
                                                @select="(ev) => {
                        labelRef = ev.detail.value as string
                        open = false
                      }"
                                            >
                                                {{ label }}
                                            </CommandItem>
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="text-red-600">
                            Delete
                            <DropdownMenuShortcut>⌘⌫</DropdownMenuShortcut>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

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
            :title="`Categories`"
            :url="`categories.index`"
        />
    </AppLayout>
</template>
