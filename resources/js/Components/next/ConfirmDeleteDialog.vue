<script setup>
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Spinner } from '@/Components/ui/spinner'
const props = defineProps({
    open: Boolean,
    title: String,
    description: String,
    cancelText: String,
    continueText: String,
    // While the delete is in flight the dialog stays up with a spinner: closing
    // it would hide the only sign that anything is happening, and leaving the
    // button live invites a second delete on the same row.
    loading: { type: Boolean, default: false },
    loadingText: String,
    contentClass: { type: String, default: '' },
})
const emit = defineEmits(['confirm', 'update:open'])
</script>

<template>
    <Dialog :open="open" @update:open="value => !loading && emit('update:open', value)">
        <DialogContent :class="contentClass">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="loading"
                    @click="$emit('update:open', false)"
                >
                    {{ cancelText }}
                </Button>
                <Button :disabled="loading" class="gap-2" @click="$emit('confirm')">
                    <Spinner v-if="loading" />
                    {{ loading ? (loadingText || continueText) : continueText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
