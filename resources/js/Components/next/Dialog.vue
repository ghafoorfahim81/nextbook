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
  confirmText: String,
  cancelText: String,
  showCancel: Boolean,
  showConfirm: Boolean,
  closeable: {
    type: Boolean,
    default: true, // ✅ Controls closing behavior
  },
});

const emit = defineEmits(["update:open", "confirm", "cancel"]);
</script>

<template>
  <Dialog :open="open" >
    <!-- ✅ Lighter background (more transparency) -->
    <div v-if="open" class="fixed inset-0 bg-white/5" />

    <DialogContent
    >
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription v-if="description">
          {{ description }}
        </DialogDescription>
      </DialogHeader>
      <Separator/>
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

