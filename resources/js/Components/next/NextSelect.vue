<template>
    <div class="relative w-full">
      <div class="relative">
        <v-select
          :id="id"
          :options="searchableOptions"
          :label="labelKey"
          :reduce="reduceInternal"
          :modelValue="modelValue"
          :dir="isRTL ? 'rtl' : 'ltr'"
          @update:modelValue="val => emit('update:modelValue', val)"
          @search="handleSearch"
          :filterable="false"
          :clearable="clearable"
          :loading="isLoading"
          :placeholder="placeholder"
          :close-on-select="true"
          :append-to-body="shouldAppendToBody"
          :calculate-position="shouldAppendToBody ? calculatePosition : null"
          class="col-span-3 w-full sm:text-sm"
          :class="[{ 'no-arrow': !showArrow }]"
          v-bind="$attrs"
        />

        <!-- Floating label (does NOT block clicks) -->
        <FloatingLabel
          :id="id"
          :label="floatingText"
          class="pointer-events-none z-20"
        />
      </div>

      <span v-if="error" class="mt-1 block text-red-500 text-sm">
        {{ error }}
      </span>
    </div>
  </template>

  <script setup>
  import { ref, computed, watch, onMounted, getCurrentInstance } from 'vue'
  import { useI18n } from 'vue-i18n'
  import FloatingLabel from '@/Components/next/FloatingLabel.vue'
  import { useSearchResources } from '@/composables/useSearchResources.js'

  /* ---------------- PROPS ---------------- */

  const props = defineProps({
    modelValue: [String, Number, Object, Array, null],
    options: { type: Array, default: () => [] },
    labelKey: { type: String, default: 'name' },
    valueKey: { type: String, default: 'id' },
    reduce: { type: Function, default: null },
    id: { type: String, default: () => 'sel-' + Math.random().toString(36).slice(2) },
    floatingText: { type: String, default: '' },
    error: { type: String, default: '' },
    placeholder: { type: String, default: '' },

    searchable: { type: Boolean, default: false },
    resourceType: { type: String, default: null },
    searchFields: { type: Array, default: () => ['name'] },
    searchOptions: { type: Object, default: () => ({}) },

    showArrow: { type: Boolean, default: true },
    clearable: { type: Boolean, default: true },

    /* default OFF — can be enabled per-usage if needed */
    appendToBody: { type: Boolean, default: false },
  })

  const emit = defineEmits(['update:modelValue'])

  /* ---------------- I18N / DIRECTION ---------------- */

  const { locale } = useI18n()
  const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))

  /* ---------------- DIALOG AUTO DETECTION ---------------- */

  const instance = getCurrentInstance()
  const isInDialog = ref(false)

  onMounted(() => {
    let el = instance?.proxy?.$el?.parentElement
    while (el) {
      if (
        el.classList?.contains('dialog-root') ||
        el.classList?.contains('modal') ||
        el.getAttribute?.('role') === 'dialog'
      ) {
        isInDialog.value = true
        break
      }
      el = el.parentElement
    }
  })

  const shouldAppendToBody = computed(() => {
    return props.appendToBody && !isInDialog.value
  })

  /* ---------------- SEARCH & OPTIONS ---------------- */

  const cachedOptions = ref(new Map())
  const searchableOptions = ref([...props.options])
  const { searchResources, isLoading } = useSearchResources()

  const reduceInternal = (option) => {
    if (props.reduce) return props.reduce(option)
    return option ? option[props.valueKey] : null
  }

  const ensureSelectedOptionInOptions = () => {
    if (!props.modelValue) return
    const selectedValue = props.modelValue
    const exists = searchableOptions.value.some(
      o => reduceInternal(o) === selectedValue
    )
    if (!exists) {
      const found =
        props.options.find(o => reduceInternal(o) === selectedValue) ||
        cachedOptions.value.get(selectedValue)

      if (found) {
        searchableOptions.value = [
          found,
          ...searchableOptions.value.filter(o => reduceInternal(o) !== selectedValue),
        ]
      }
    }
  }

  watch(() => props.options, (opts) => {
    searchableOptions.value = [...opts]
    ensureSelectedOptionInOptions()
  }, { immediate: true })

  watch(() => props.modelValue, ensureSelectedOptionInOptions, { immediate: true })

  const handleSearch = async (term) => {
    if (!props.searchable || !props.resourceType) return
    try {
      const results = await searchResources(
        term,
        props.options,
        props.resourceType,
        {
          labelKey: props.labelKey,
          valueKey: props.valueKey,
          searchFields: props.searchFields,
          ...props.searchOptions,
        }
      )

      results.forEach(o => {
        cachedOptions.value.set(reduceInternal(o), o)
      })

      searchableOptions.value = results
    } catch {
      searchableOptions.value = [...props.options]
    } finally {
      ensureSelectedOptionInOptions()
    }
  }

  /* ---------------- PAGE-ONLY POSITIONING ---------------- */

  const calculatePosition = (dropdownEl, component) => {
    const toggle = component?.$refs?.toggle
    if (!toggle) return

    const rect = toggle.getBoundingClientRect()
    dropdownEl.style.left = `${rect.left}px`
    dropdownEl.style.top = `${rect.bottom + 2}px`
    dropdownEl.style.width = `${rect.width}px`
  }
  </script>

  <style scoped>
  /* ---------------- SAFE STYLES ---------------- */

  /* match app input look (light + dark via CSS vars) */
  :deep(.vs__dropdown-toggle) {
    background-color: hsl(var(--background));
    border: 2px solid hsl(var(--border));
    border-radius: var(--radius);
    min-height: 2.5rem;
    padding: 0.25rem 0.75rem 0.5rem;
    color: hsl(var(--foreground));
  }

  :deep(.vs__selected),
  :deep(.vs__search) {
    color: hsl(var(--foreground));
  }

  :deep(.vs__open-indicator),
  :deep(.vs__clear) {
    fill: hsl(var(--muted-foreground));
  }

  /* dropdown always clickable */
  :deep(.vs__dropdown-option) {
    cursor: pointer;
    color: hsl(var(--popover-foreground));
  }

  /* hover / highlight */
  :deep(.vs__dropdown-option--highlight),
  :deep(.vs__dropdown-option:hover) {
    background: hsl(var(--primary));
    color: hsl(var(--primary-foreground));
  }

  /* no arrow option */
  .no-arrow :deep(.vs__open-indicator) {
    display: none;
  }

  /* Force dropdown to open *below* the control (never as a drop-up) */
  :deep(.vs__dropdown-menu) {
    position: absolute;   /* anchor just under the toggle */
    bottom: auto !important;
    left: 0;
    width: 100%;
    margin-top: 2px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 50;
    background-color: hsl(var(--popover));
    color: hsl(var(--popover-foreground));
    border-color: hsl(var(--border));
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.45);
  }

  /* focus parity with inputs */
  :deep(.vs--open .vs__dropdown-toggle),
  :deep(.vs__dropdown-toggle:focus-within) {
    border-color: rgb(99 102 241);
    box-shadow: 0 0 0 1px rgba(99,102,241,.25);
  }
  </style>
