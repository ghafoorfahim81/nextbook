<script setup>
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'

const props = defineProps({
    open: Boolean,
    title: String,
    description: String,
})
const emit = defineEmits(['confirm', 'update:open'])
</script>

<template>
    <Dialog :open="open" @update:open="value => emit('update:open', value)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="$emit('update:open', false)">Cancel</Button>
                <Button @click="$emit('confirm')">Continue</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
