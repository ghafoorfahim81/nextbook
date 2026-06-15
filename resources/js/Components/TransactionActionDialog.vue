<script setup>
import { ref, watch } from 'vue'
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
    reasonError.value = 'Reason for reversal is required.'
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
        <label class="text-sm font-medium text-foreground">Reason for reversal</label>
        <Textarea
          v-model="reason"
          rows="3"
          placeholder="Enter a short reason..."
          @input="reasonError = ''"
        />
        <p v-if="reasonError" class="text-xs text-red-600">{{ reasonError }}</p>
      </div>

      <DialogFooter class="gap-2">
        <Button variant="outline" :disabled="processing" @click="emit('update:open', false)">
          Cancel
        </Button>
        <Button
          :variant="type === 'reverse' ? 'destructive' : 'default'"
          :disabled="processing"
          @click="submit"
        >
          {{ type === 'reverse' ? 'Submit reversal' : 'Post document' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
