<script setup>
import { ref, computed } from 'vue'
 const props = defineProps({
    labelText: {
      type: String,
      required: true
    },
    options: {
      type: Array,
      required: true
    },
    modelValue: {
      type: [String, Number],
      default: ''
    }
  })
  const emit = defineEmits(['update:modelValue'])
  const modelValue = computed({
    get() {
      return props.modelValue
    },
    set(value) {
      emit('update:modelValue', value)
    }
  })

  const itemSearch = (search, loading) => {
    if (search.length > 0) {
      clearTimeout(debounce.value)
      loading(true)
      debounce.value = setTimeout(() => {
        emit('search', search, loading)
        loading(false)
      }, 350)
    }
  }
  const click = (e) => {
    emit('update:modelValue', e)
    emit('click')
  }
  const input = (e) => {
    emit('update:modelValue', e)
    emit('input')
  }
  const id = ref(`input-${Math.random().toString(36).substr(2, 9)}`)
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" :for="id">{{ labelText }}</label>
        <v-select 
        type="any"
        :options.sync="options"
        :label="label ? label : 'name'"
        @search="itemSearch"
        @click="click($event)"
        @input="input($event)"
        :multiple="multiple ? multiple : false"
        :filterable="filterable ? filterable : true"
        :value.sync="value" />
    </div>
</template>
