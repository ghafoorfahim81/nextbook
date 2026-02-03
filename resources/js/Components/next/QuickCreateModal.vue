<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import ModalDialog from '@/Components/next/Dialog.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import { QUICK_CREATE_EVENT, quickCreateRegistry } from '@/Components/next/quickCreateRegistry'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  open: { type: Boolean, default: false },
  resourceType: { type: String, required: true },
  // optional context from select (filters, defaults)
  additionalParams: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:open', 'created'])

const { t } = useI18n()
const page = usePage()
const submitting = ref(false)
const errors = ref({})

const config = computed(() => quickCreateRegistry?.[props.resourceType] || null)
const title = computed(() => {
  const key = config.value?.titleKey
  return key ? t(key) : ''
})

const currencyOptions = computed(() => page.props?.currencies?.data || page.props?.currencies || [])
const categoryOptions = computed(() => page.props?.categories?.data || page.props?.categories || [])
const brandOptions = computed(() => page.props?.brands?.data || page.props?.brands || [])
const sizeOptions = computed(() => page.props?.sizes?.data || page.props?.sizes || [])
const unitMeasureOptions = computed(() => page.props?.unitMeasures?.data || page.props?.unitMeasures || [])
const accountTypeOptions = computed(() => page.props?.accountTypes?.data || page.props?.accountTypes || [])
const transactionTypeOptions = computed(() => page.props?.transactionTypes?.data || page.props?.transactionTypes || [
  { id: 'debit', name: 'Debit' },
  { id: 'credit', name: 'Credit' },
])

const ledgerTypeOptions = computed(() => [
  { id: 'customer', name: t('ledger.customer.customer') },
  { id: 'supplier', name: t('ledger.supplier.supplier') },
])

const makeInitialForm = () => {
  const base = {}
  const defaults = config.value?.defaults?.({ additionalParams: props.additionalParams }) || {}
  // seed nested objects if any nested key exists
  const hasMetric = config.value?.fields?.some((f) => f.key.startsWith('metric.'))
  const hasMeasure = config.value?.fields?.some((f) => f.key.startsWith('measure.'))
  if (hasMetric) base.metric = { name: '', unit: '', symbol: '' }
  if (hasMeasure) base.measure = { name: '', unit: '', symbol: '' }
  return deepMerge(base, defaults)
}

const form = ref(makeInitialForm())

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    errors.value = {}
    form.value = makeInitialForm()
  }
)

const close = () => emit('update:open', false)

const resolveFieldOptions = (fieldKey) => {
  // Select options derived from field key
  if (fieldKey === 'currency_id') return currencyOptions.value
  if (fieldKey === 'parent_id' || fieldKey === 'category_id') return categoryOptions.value
  if (fieldKey === 'brand_id') return brandOptions.value
  if (fieldKey === 'size_id') return sizeOptions.value
  if (fieldKey === 'unit_measure_id') return unitMeasureOptions.value
  if (fieldKey === 'account_type_id') return accountTypeOptions.value
  if (fieldKey === 'transaction_type') return transactionTypeOptions.value
  if (fieldKey === 'type') return ledgerTypeOptions.value
  return []
}

const submit = async () => {
  if (!config.value) return
  submitting.value = true
  errors.value = {}

  try {
    const endpointType = config.value.endpointType
    const url = `/quick-create/${endpointType}`
    const payload = toPayload(form.value)
    const res = await axios.post(url, payload, {
      headers: { Accept: 'application/json' },
    })

    const created = res?.data?.data
    if (!created) {
      throw new Error('Unexpected response from server.')
    }

    // broadcast globally for other selects
    window.dispatchEvent(new CustomEvent(QUICK_CREATE_EVENT, {
      detail: { resourceType: props.resourceType, endpointType, created },
    }))

    emit('created', created)
    close()
  } catch (e) {
    const status = e?.response?.status
    if (status === 422) {
      errors.value = e.response?.data?.errors || e.response?.data || {}
    } else {
      console.error(e)
      errors.value = { general: e?.response?.data?.message || e?.message || 'Failed to create.' }
    }
  } finally {
    submitting.value = false
  }
}

function deepMerge(a, b) {
  const out = Array.isArray(a) ? [...a] : { ...(a || {}) }
  Object.keys(b || {}).forEach((k) => {
    if (b[k] && typeof b[k] === 'object' && !Array.isArray(b[k])) {
      out[k] = deepMerge(out[k] || {}, b[k])
    } else {
      out[k] = b[k]
    }
  })
  return out
}

function getByPath(obj, path) {
  return path.split('.').reduce((acc, key) => (acc ? acc[key] : undefined), obj)
}

function setByPath(obj, path, value) {
  const parts = path.split('.')
  let cur = obj
  for (let i = 0; i < parts.length - 1; i++) {
    const p = parts[i]
    if (!cur[p] || typeof cur[p] !== 'object') cur[p] = {}
    cur = cur[p]
  }
  cur[parts[parts.length - 1]] = value
}

function toPayload(data) {
  // shallow clone is enough because we already structure nested objects
  return JSON.parse(JSON.stringify(data || {}))
}
</script>

<template>
  <ModalDialog
    :open="open"
    :title="$t('general.new', { name: title })"
    :confirmText="$t('general.create')"
    :cancel-text="$t('general.close')"
    :submitting="submitting"
    width="w-[800px] max-w-[800px]"
    @update:open="emit('update:open', $event)"
    @confirm="submit"
    @cancel="close"
  >
    <div v-if="errors?.general" class="mb-3 rounded-md border border-red-200 bg-red-50 p-3 text-red-700 text-sm">
      {{ errors.general }}
    </div>

    <div v-if="config" class="grid grid-cols-2 gap-4 mt-2">
      <template v-for="field in config.fields" :key="field.key">
        <div class="col-span-2" v-if="field.type === 'textarea'">
          <NextTextarea
            :label="field.labelKey ? $t(field.labelKey) : field.label"
            :model-value="getByPath(form, field.key)"
            @update:modelValue="(v) => setByPath(form, field.key, v)"
            :error="errors?.[field.key]"
          />
        </div>

        <div class="col-span-2" v-else-if="field.type === 'checkbox'">
          <label class="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              :checked="Boolean(getByPath(form, field.key))"
              @change="setByPath(form, field.key, $event.target.checked)"
            />
            <span>{{ field.labelKey ? $t(field.labelKey) : field.label }}</span>
          </label>
          <div v-if="errors?.[field.key]" class="mt-1 text-sm text-red-500">
            {{ errors[field.key] }}
          </div>
        </div>

        <div v-else-if="field.type === 'select'">
          <NextSelect
            :options="resolveFieldOptions(field.key)"
            :quick-create="false"
            :clearable="!field.required"
            :model-value="getByPath(form, field.key)"
            @update:modelValue="(v) => setByPath(form, field.key, v)"
            :reduce="(o) => o?.id"
            label-key="name"
            value-key="id"
            :floating-text="field.labelKey ? $t(field.labelKey) : field.label"
            :error="errors?.[field.key]"
          />
        </div>

        <div v-else>
          <NextInput
            :label="field.labelKey ? $t(field.labelKey) : field.label"
            :type="field.type === 'number' ? 'number' : 'text'"
            :model-value="getByPath(form, field.key)"
            @update:modelValue="(v) => setByPath(form, field.key, v)"
            :error="errors?.[field.key]"
          />
        </div>
      </template>
    </div>
  </ModalDialog>
</template>

