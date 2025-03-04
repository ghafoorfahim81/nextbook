

<script setup>
import { Button } from '@/Components/ui/button/index.js'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog/index.js'
import { Input } from '@/Components/ui/input/index.js'
import { Label } from '@/Components/ui/label/index.js'

const props = defineProps({
    show: Boolean, // Receive the modal visibility state as a prop
});

import {watch} from "vue"
watch(() => props.show, (newVal) => {
    console.log('Modal visibility changed:', newVal);
});


const emit = defineEmits(['update:show']); // Emit an event to update the parent's state
</script>
<template>
    <Dialog :open="show" @openChange="(isOpen) => $emit('update:show', isOpen)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Create New Designation</DialogTitle>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="name" class="text-right">
                        Name
                    </Label>
                    <Input id="name" placeholder="Enter name" class="col-span-3" />
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="username" class="text-right">
                        Username
                    </Label>
                    <Input id="username" placeholder="Enter username" class="col-span-3" />
                </div>
            </div>
            <DialogFooter>
                <Button type="submit">Save changes</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>


