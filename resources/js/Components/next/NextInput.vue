<template>
    <div class="grid w-full max-w-sm items-center gap-1.5">
      <Label :for="inputId">{{ label }}</Label>
      <Input 
        :id="inputId"
        :type="type"
        :placeholder="placeholder"
        :value="modelValue"
        @update:modelValue="handleInput" 
        :min="type === 'number' ? min : undefined"
        :max="type === 'number' ? max : undefined"
        :step="type === 'number' ? step : undefined"
        class="w-full"
      />
      <span v-if="error" class="text-red-500 text-sm">{{ error }}</span>
    </div>
  </template>
  
  <script setup>
  import { Input } from '@/Components/ui/input'
  import { Label } from '@/Components/ui/label'
  import { ref } from 'vue'
  
  const props = defineProps({
    label: {
      type: String,
      required: true
    },
    type: {
      type: String,
      default: 'text',
      validator: (value) => ['text', 'number'].includes(value)
    },
    placeholder: {
      type: String,
      default: ''
    },
    modelValue: {
      type: [String, Number],
      default: ''
    },
    min: {
      type: Number,
      default: undefined
    },
    max: {
      type: Number,
      default: undefined
    },
    step: {
      type: Number,
      default: 1
    }
  })
  
  const emit = defineEmits(['update:modelValue'])
  
  const inputId = ref(`input-${Math.random().toString(36).substring(2, 9)}`)
  const error = ref('')
  
  const handleInput = (value) => {
  
    if (props.type === 'number') {
      // Convert to number and validate
      value = value === '' ? '' : Number(value)
      
      if (value !== '' && isNaN(value)) {
        error.value = 'Please enter a valid number'
        return
      }
  
      // Validate min/max if provided
      if (props.min !== undefined && value !== '' && value < props.min) {
        error.value = `Value must be at least ${props.min}`
        return
      }
      if (props.max !== undefined && value !== '' && value > props.max) {
        error.value = `Value cannot exceed ${props.max}`
        return
      }
    }
  
    error.value = ''
    emit('update:modelValue', value)
  }
  </script>