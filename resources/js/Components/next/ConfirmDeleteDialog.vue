<script setup>
import {
    AlertDialog,
    AlertDialogTrigger,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogFooter,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogCancel,
    AlertDialogAction,
} from '@/components/ui/alert-dialog'

const props = defineProps({
    open: Boolean,
    title: {
        type: String,
        default: 'Are you absolutely sure?',
    },
    description: {
        type: String,
        default: 'This action cannot be undone.',
    },
    confirmText: {
        type: String,
        default: 'Continue',
    },
    cancelText: {
        type: String,
        default: 'Cancel',
    },
})

const emit = defineEmits(['update:open', 'confirm'])

const handleConfirm = () => {
    emit('confirm')
    emit('update:open', false)
}
</script>

<template>
    <AlertDialog :open="open" @update:open="emit('update:open', $event)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>{{ description }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ cancelText }}</AlertDialogCancel>
                <AlertDialogAction @click="handleConfirm">{{ confirmText }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
