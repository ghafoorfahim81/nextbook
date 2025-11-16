<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  open: Boolean,
  ownerId: String,
})
const emit = defineEmits(['update:open'])

const owner = ref(null)
const loading = ref(false)

watch(() => props.open, async (isOpen) => {
  if (isOpen && props.ownerId) {
    loading.value = true
    try {
      const { data } = await axios.get(`/owners/${props.ownerId}`)
      owner.value = data?.data || data || null
    } finally {
      loading.value = false
    }
  }
})

function closeDialog() {
  emit('update:open', false)
  owner.value = null
}
</script>

<template>
  <Dialog :open="open" @update:open="closeDialog">
    <DialogContent class="max-w-2xl">
      <DialogHeader>
        <DialogTitle class="text-xl">
          Owner <span v-if="owner">- {{ owner.name }}</span>
        </DialogTitle>
      </DialogHeader>

      <div v-if="loading" class="py-6 text-center text-muted-foreground">
        Loading...
      </div>

      <div v-else-if="owner" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div><strong>Name:</strong> {{ owner.name }}</div>
          <div><strong>Father Name:</strong> {{ owner.father_name }}</div>
          <div><strong>NIC:</strong> {{ owner.nic || '-' }}</div>
          <div><strong>Email:</strong> {{ owner.email || '-' }}</div>
          <div><strong>Phone:</strong> {{ owner.phone_number || '-' }}</div>
          <div><strong>Address:</strong> {{ owner.address || '-' }}</div>
          <div><strong>Ownership %:</strong> {{ owner.ownership_percentage }}</div>
          <div><strong>Active:</strong> {{ owner.is_active ? 'Yes' : 'No' }}</div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <div class="text-sm text-muted-foreground mb-1">Capital Account</div>
            <div class="font-medium">{{ owner.capital_account?.name || '-' }}</div>
          </div>
          <div>
            <div class="text-sm text-muted-foreground mb-1">Drawing Account</div>
            <div class="font-medium">{{ owner.drawing_account?.name || '-' }}</div>
          </div>
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="closeDialog">Close</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
  </template>


