<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'

const { t } = useI18n()

const props = defineProps({
  open: Boolean,
  type: { type: String, default: 'post' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  processing: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'update:open'])

const reason = ref('')
const reasonError = ref('')

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    reason.value = ''
    reasonError.value = ''
  }
})

const submit = () => {
  if (props.type === 'reverse' && !reason.value.trim()) {
    reasonError.value = t('general.reason_for_reversal') + ' ' + t('general.is_required')
    return
  }

  emit('confirm', reason.value.trim())
}
</script>

<template>
  <Dialog :open="open" @update:open="value => emit('update:open', value)">
    <DialogContent class="max-w-md rounded-xl">
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription>
          {{ description }}
        </DialogDescription>
      </DialogHeader>

      <div v-if="type === 'reverse'" class="space-y-2">
        <label class="text-sm font-medium text-foreground">{{ t('general.reason_for_reversal') }}</label>
        <Textarea
          v-model="reason"
          rows="3"
          :placeholder="t('general.reversal_placeholder')"
          @input="reasonError = ''"
        />
        <p v-if="reasonError" class="text-xs text-red-600">{{ reasonError }}</p>
      </div>

      <DialogFooter class="gap-2">
        <Button variant="outline" :disabled="processing" @click="emit('update:open', false)">
          {{ t('general.cancel') }}
        </Button>
        <Button
          :variant="type === 'reverse' ? 'destructive' : 'default'"
          :disabled="processing"
          @click="submit"
        >
          {{ type === 'reverse' ? t('general.submit_reversal') : t('general.post_document') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
