<template>
    <div class="relative w-full">
      <v-select
        :id="id"
        :options="searchableOptions"
        :label="labelKey"
        :reduce="reduceInternal"
        :modelValue="modelValue"
        :dir="isRTL ? 'rtl' : 'ltr'"
        @update:modelValue="val => emit('update:modelValue', val)"
        @search="handleSearch"
        @update:search="onSearchUpdate"
        :filterable="false"
        :clearable="clearable"
        :loading="isLoading"
        :placeholder="placeholder"
        :close-on-select="true"
        :no-options-text="noResultsText"
        :append-to-body="shouldAppendToBody"
        :calculate-position="shouldAppendToBody ? calculatePosition : null"
        class="col-span-3 w-full sm:text-sm"
        :class="[{ 'no-arrow': !showArrow }]"

        v-bind="$attrs"
      >
        <!-- Add New Option Button (always visible at bottom when open) -->
        <template #list-footer v-if="showQuickCreateButton">
          <div class="list-footer">
            <button
              type="button"
              @click="openAddDialog"
              class="btn btn-sm btn-outline-primary px-3 drop-btn"
            >
              <i class="bx bx-list-plus ml-2 text-center"></i>
              {{ addNewButtonText }}
            </button>
          </div>
        </template>
      </v-select>

      <QuickCreateModal
        v-if="quickCreateConfig"
        :open="quickCreateOpen"
        :resource-type="resourceType"
        :additional-params="normalizedSearchOptions.additionalParams || {}"
        @update:open="quickCreateOpen = $event"
        @created="handleQuickCreated"
      />

      <!-- Floating label (does NOT block clicks) -->
      <FloatingLabel
        :id="id"
        :label="floatingText"
        class="pointer-events-none z-20"
      />

      <!-- Error Display -->
      <span v-if="error" class="mt-1 block text-red-500 text-sm">
        {{ error }}
      </span>
    </div>
  </template>



  <script setup>
    import { ref, computed, watch, onMounted, onUnmounted, getCurrentInstance, nextTick } from 'vue'
  import { useI18n } from 'vue-i18n'
  import FloatingLabel from '@/Components/next/FloatingLabel.vue'
  import { useSearchResources } from '@/composables/useSearchResources.js'
  import QuickCreateModal from '@/Components/next/QuickCreateModal.vue'
  import { QUICK_CREATE_EVENT, quickCreateRegistry } from '@/Components/next/quickCreateRegistry'

  const { t } = useI18n()

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
    hasAddButton: { type: Boolean, default: true },
    addNewLabel: { type: String, default: '' },
    clearable: { type: Boolean, default: true },

    /* default OFF — can be enabled per-usage if needed */
    appendToBody: { type: Boolean, default: false },
  })

  const emit = defineEmits(['update:modelValue', 'add-new'])

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

  const quickCreateOpen = ref(false)
  const quickCreateConfig = computed(() => {
    if (!props.resourceType) return null
    return quickCreateRegistry?.[props.resourceType] || null
  })

  const normalizedSearchOptions = computed(() => {
    const so = props.searchOptions || {}
    const knownKeys = new Set([
      'labelKey',
      'valueKey',
      'searchFields',
      'minSearchLength',
      'cacheTimeout',
      'debounceMs',
      'limit',
      'additionalParams',
    ])

    // If user already provided additionalParams, keep it as-is.
    if (so && typeof so === 'object' && 'additionalParams' in so) {
      return so
    }

    // Backward compatible: treat unknown keys as additional params.
    const additionalParams = {}
    Object.entries(so || {}).forEach(([k, v]) => {
      if (!knownKeys.has(k)) additionalParams[k] = v
    })

    return {
      ...so,
      additionalParams,
    }
  })

  const showQuickCreateButton = computed(() => {
    return Boolean(props.hasAddButton && quickCreateConfig.value)
  })

  const addNewButtonText = computed(() => {
    if (props.addNewLabel) return props.addNewLabel
    const titleKey = quickCreateConfig.value?.titleKey
    const name = titleKey ? t(titleKey) : ''
    return t('general.new', { name })
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
  const results = await searchResources(
    term,
    props.options,
    props.resourceType,
    {
      labelKey: props.labelKey,
      valueKey: props.valueKey,
      searchFields: props.searchFields,
      ...normalizedSearchOptions.value,
    }
  )

  // 🔥 COMPOSABLE SIGNALS RESET
  if (results === null) return

  searchableOptions.value = results
}

const onSearchUpdate = (val) => {
  if (!val || !val.trim()) {
    searchableOptions.value = []
    nextTick(() => {
      searchableOptions.value = [...props.options]
      ensureSelectedOptionInOptions()
    })
  }
}

const openAddDialog = () => {
  // If this select supports quick-create, open the reusable modal.
  if (quickCreateConfig.value) {
    quickCreateOpen.value = true
    return
  }

  // Fallback for legacy usages that listen to this event externally.
  emit('add-new')
}

const addCreatedOptionToOptions = (created) => {
  if (!created) return
  const createdValue = reduceInternal(created)

  // cache for ensureSelectedOptionInOptions()
  cachedOptions.value.set(createdValue, created)

  // prepend and dedupe
  searchableOptions.value = [
    created,
    ...searchableOptions.value.filter((o) => reduceInternal(o) !== createdValue),
  ]
}

const handleQuickCreated = (created) => {
  addCreatedOptionToOptions(created)

  const newModel = props.reduce ? props.reduce(created) : created?.[props.valueKey]
  emit('update:modelValue', newModel)
}

const onGlobalQuickCreated = (event) => {
  const detail = event?.detail || {}
  const myEndpoint = quickCreateConfig.value?.endpointType
  if (!myEndpoint) return

  // match on endpointType so aliases like items-for-sale update items selects too
  if (detail.endpointType && detail.endpointType === myEndpoint) {
    addCreatedOptionToOptions(detail.created)
  }
}

onMounted(() => {
  window.addEventListener(QUICK_CREATE_EVENT, onGlobalQuickCreated)
})

onUnmounted(() => {
  window.removeEventListener(QUICK_CREATE_EVENT, onGlobalQuickCreated)
})
  /* ---------------- PAGE-ONLY POSITIONING ---------------- */

  const calculatePosition = (dropdownEl, component) => {
    const toggle = component?.$refs?.toggle
    if (!toggle) return

    const rect = toggle.getBoundingClientRect()

    // When `append-to-body` is enabled, the dropdown is moved under <body>.
    // `getBoundingClientRect()` is viewport-relative, so we must add page scroll
    // to convert to document coordinates.
    const scrollX = window.scrollX ?? window.pageXOffset ?? 0
    const scrollY = window.scrollY ?? window.pageYOffset ?? 0

    dropdownEl.style.left = `${rect.left + scrollX}px`
    dropdownEl.style.top = `${rect.bottom + scrollY + 2}px`
    dropdownEl.style.width = `${rect.width}px`
  }
  </script>

  <style scoped>
  /* ---------------- SAFE STYLES ---------------- */

  .list-footer {
    position: sticky;
    bottom: 0;
    z-index: 2;
    background-color: hsl(var(--popover));
    padding: 0.5rem;
    /* Remove border and margin below button to eliminate unwanted space */
    border-top: none;
    margin-bottom: 0;
  }

  /* Styling for the "Add New Account" button */
  .drop-btn {
    background-color: transparent;
    border: 1px solid hsl(var(--primary));
    color: hsl(var(--primary));
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    cursor: pointer;
    border-radius: var(--radius);
    width: 100%;
  }

  /* Hover effect (optional, for style) */
  .drop-btn:hover {
    background-color: hsl(var(--primary));
    color: hsl(var(--primary-foreground));
  }

  /* Material Icon Size */
  .bx {
    font-size: 20px;
  }

  /* Center alignment */
  .d-flex {
    display: flex;
  }

  /* Ensure button is at the bottom of the dropdown */
  .vs__dropdown-menu {
    position: absolute;
    bottom: auto !important;
    left: 0;
    width: 100%;
    margin-top: 2px;
    max-height: 150px;
    overflow-y: auto;
    z-index: 500;
    background-color: hsl(var(--popover));
    color: hsl(var(--popover-foreground));
    border-color: hsl(var(--border));
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.45);
    padding-bottom: 0 !important; /* REMOVE bottom padding to eliminate space */
  }

  /* match app input look (light + dark via CSS vars) */
  :deep(.vs__dropdown-toggle) {
    background-color: hsl(var(--background));
    border: 1px solid hsl(var(--border));
    border-radius: calc(var(--radius) - 2px);
    height: 2.5rem;      /* match input h-10 */
    min-height: 2.5rem;
    padding: 0 0.75rem;  /* avoid extra vertical height from padding */
    display: flex;
    align-items: center;
    color: hsl(var(--foreground));
  }

  /* Ensure internal content centers vertically and doesn't add extra height */
  :deep(.vs__selected-options) {
    display: flex;
    align-items: center;
    padding: 0;
    margin: 0;
  }

  :deep(.vs__actions) {
    display: flex;
    align-items: center;
    padding: 0;
  }

  :deep(.vs__search),
  :deep(.vs__selected) {
    margin: 0;
    line-height: 1.25rem; /* text-sm-ish */
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
    max-height: 150px;
    overflow-y: auto;
    z-index: 500;
    background-color: hsl(var(--popover));
    color: hsl(var(--popover-foreground));
    border-color: hsl(var(--border));
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.45);
    padding-bottom: 0 !important; /* REMOVE bottom padding to eliminate space */
  }

  /* focus parity with inputs */
  :deep(.vs--open .vs__dropdown-toggle),
  :deep(.vs__dropdown-toggle:focus-within) {
    border-color: rgb(137, 80, 221);
    box-shadow: 0 0 0 1px rgba(99,102,241,.25);
  }
  </style>
