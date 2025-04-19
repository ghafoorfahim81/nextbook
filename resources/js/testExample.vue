<template>
  <v-select
    :type="type"
    :options="options"
    :label="label || 'name'"
    @search="itemSearch"
    @click="click"
    @update:modelValue="input"
    :multiple="multiple"
    :filterable="filterable"
    v-model="modelValue"
    class="select-item w-full select-40px"
    :dir="setDir"
    :placeholder="$t(placeholder || 'SearchItem')"
  >
    <template #no-options>
      <li>No record found!</li>
    </template>
  </v-select>
</template>

<script setup>
import { ref, computed } from 'vue'   

const props = defineProps({
  name: {
    type: String,
    default: ''
  },
  textlabel: {
    type: String,
    default: ''
  },
  label: {
    type: String,
    default: ''
  },
  modelValue: {
    type: Object,
    default: null
  },
  placeholder: {
    type: String,
    default: ''
  },
  title: {
    type: String,
    default: ''
  },
  options: {
    type: Array,
    default: () => []
  },
  filterable: {
    type: Boolean,
    default: true
  },
  has_footer: {
    type: Boolean,
    default: true
  },
  multiple: {
    type: Boolean,
    default: false
  },
  type: {
    type: String,
    default: 'any'
  }
})

const emit = defineEmits(['update:modelValue', 'search', 'click', 'add_click', 'input'])

// const store = useStore()
// const debounce = ref(null)

// const authGetter = computed(() => store.getters['permissions/authGetter'])

// const setDir = computed(() => {
//   return authGetter.value?.lang === 'en-us' ? 'ltr' : 'rtl'
// })

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
</script>

<style scoped>
.select-item select {
  -moz-appearance: none;
  -webkit-appearance: none;
}

#add {
  cursor: pointer;
}

.select-item select::-ms-expand {
  display: none;
}

.vs__dropdown-toggle {
  height: 40px;
}
</style>