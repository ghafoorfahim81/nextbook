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
import { useI18n } from 'vue-i18n';

const { t } = useI18n()

const props = defineProps({
  open: Boolean,
  title: String,
  description: String,
  submitting: Boolean,
  width: {
    type: String,
    default: "w-[95vw] max-w-[95vw] sm:w-[500px] sm:max-w-[500px]",
  },
  contentClass: {
    type: String,
    default: '',
  },
  bodyClass: {
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

const isVueSelectOverlayTarget = (event: any) => {
  const target = event?.target || event?.detail?.originalEvent?.target;

  return target instanceof Element
    && Boolean(target.closest('.vs__dropdown-menu, .vs__dropdown-toggle, .vs__search'));
};

const preventOutsideDismiss = (event: any) => {
  if (isVueSelectOverlayTarget(event)) {
    return;
  }

  event?.preventDefault?.();
};
</script>

<template>
  <Dialog :open="open" @update:open="value => emit('update:open', value)">
    <!-- ✅ Lighter background (more transparency) -->
    <div v-if="open" class="fixed inset-0 z-[1090] bg-white/5" />

    <DialogContent
      :disable-outside-pointer-events="false"
      :class="[
        width, 
        'z-[1100] p-0 flex flex-col overflow-hidden',
        'max-h-[90vh]',                                    // Limit modal height for all screens
        'rounded-none sm:rounded-2xl',                     // No border-radius on mobile, rounded on sm+
        contentClass
      ]"
      @pointer-down-outside="preventOutsideDismiss"
      @interact-outside="preventOutsideDismiss"
    >
      <div class="flex flex-col max-h-[90vh]">
        <div class="sticky top-0 z-10 bg-background px-4 pt-5 pb-3 sm:px-6 sm:pt-6 sm:pb-4">
          <DialogHeader>
            <DialogTitle class="text-base sm:text-lg">{{ title }}</DialogTitle>
            <DialogDescription v-if="description" class="text-sm sm:text-base">
              {{ description }}
            </DialogDescription>
          </DialogHeader>
        </div>

        <Separator class="sticky top-[64px] sm:top-[72px] z-10" />

        <div :class="['flex-1 overflow-y-auto px-4 py-3 sm:px-6 sm:py-4', bodyClass]">
          <slot />
        </div>

        <Separator class="sticky bottom-[64px] sm:bottom-[72px] z-10" />

        <div class="sticky bottom-0 z-10 bg-background px-4 pt-3 pb-5 sm:px-6 sm:pt-4 sm:pb-6">
          <DialogFooter class="justify-end gap-2">
            <Button type="button" variant="outline"
              @click="$emit('cancel'); $emit('update:open', false)">
              {{ cancelText || t('general.close') }}
            </Button>
            <Button
              variant="outline"
              :disabled="submitting"
              class="bg-primary text-white"
              @click="$emit('confirm')"
            >
              <span v-if="submitting">{{ t('general.saving') }}</span>
              <span v-else>{{ confirmText || t('general.save') }}</span>
            </Button>
          </DialogFooter>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
