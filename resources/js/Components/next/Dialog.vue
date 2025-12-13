<script setup lang="ts">
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle
} from "@/Components/ui/dialog";
import { Separator } from "@/Components/ui/separator";
import { Button } from "@/Components/ui/button";

const props = defineProps({
  open: Boolean,
  title: String,
  description: String,
  submitting: Boolean,
    width: {
        type: String,
        default: "w-[500px] max-w-[500px]"
    },
    contentClass: {
        type: String,
        default: '',
    },
  confirmText: String,
  cancelText: String,
  showCancel: Boolean,
  showConfirm: Boolean,
  closeable: {
    type: Boolean,
    // Kept for backward compatibility / future control, but outside-click is always prevented below.
    default: true,
  },
});

const emit = defineEmits(["update:open", "confirm", "cancel"]);

const preventOutsideDismiss = (event: any) => {
  event?.preventDefault?.();
};
</script>

<template>
  <Dialog :open="open" @update:open="value => emit('update:open', value)">
    <!-- ✅ Lighter background (more transparency) -->
    <div v-if="open" class="fixed inset-0 bg-white/5" />

    <DialogContent
        :class="[width, 'max-h-[90vh] p-0 flex flex-col overflow-hidden', contentClass]"
        @pointer-down-outside="preventOutsideDismiss"
        @interact-outside="preventOutsideDismiss"
    >
        <div class="flex flex-col max-h-[90vh]">
            <div class="sticky top-0 z-10 bg-background px-6 pt-6 pb-4">
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription v-if="description">
                        {{ description }}
                    </DialogDescription>
                </DialogHeader>
            </div>

            <Separator class="sticky top-[72px] z-10" />

            <div class="flex-1 overflow-y-auto px-6 py-4">
                <slot />
            </div>

            <Separator class="sticky bottom-[72px] z-10" />

            <div class="sticky bottom-0 z-10 bg-background px-6 pt-4 pb-6">
                <DialogFooter class="justify-end gap-2">
                    <Button type="button" variant="outline"
                            @click="$emit('cancel'); $emit('update:open', false)">
                        {{ cancelText || "Close" }}
                    </Button>
                    <Button
                        variant="outline"
                        :disabled="submitting"
                        class="bg-primary text-white"
                        @click="$emit('confirm')"
                    >
                        <span v-if="submitting">Saving...</span>
                        <span v-else>{{ confirmText || "Save" }}</span>
                    </Button>
                </DialogFooter>
            </div>
        </div>
    </DialogContent>
  </Dialog>
</template>

