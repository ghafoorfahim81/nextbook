<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {Separator} from "@/Components/ui/separator";
import {Button} from "@/Components/ui/button";

defineProps({
    open: Boolean,
    title: String,
    description: String,
    confirmText: String,
    cancelText: String,
    showCancel: Boolean,
    showConfirm: Boolean,
    closeable: {
        type: Boolean,
        default: true, // ✅ The modal handles it internally
    },
});

const emit = defineEmits(["update:open", "confirm", "cancel"]);
</script>

<template>
    <Dialog :open="open" :closeable="closeable" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <separator/>
            <slot />

            <DialogFooter>
                <Button type="button" variant="outline"
                        @click="$emit('cancel'); $emit('update:open', false)">
                    Close
                </Button>
                <Button
                    variant="outline"
                    @click="$emit('confirm'); $emit('update:open', false)"
                >
                    {{ confirmText || "Submit" }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
