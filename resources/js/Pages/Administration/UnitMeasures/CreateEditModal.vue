<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import ModalDialog from '@/Components/next/Dialog.vue'
import NextInput from "@/Components/next/NextInput.vue";
import NextRadio from "@/Components/next/NextRadio.vue";
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Components/ui/toast/use-toast'
const { t } = useI18n()
const { toast } = useToast()
const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object, // ✅ this is passed from Index.vue
    quantities: {
        type: [Array, Object],
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
    errors: Object,
})

const emit = defineEmits(['update:isDialogOpen', 'saved'])

const branches = computed(() => props.branches.data ?? props.branches)
const localDialogOpen = ref(props.isDialogOpen)

watch(() => props.isDialogOpen, (val) => {
    localDialogOpen.value = val
})

watch(() => localDialogOpen.value, (val) => {
    emit('update:isDialogOpen', val)
})

const isEditing = computed(() => !!props.editingItem?.id)

const form = useForm({
    metric: {
        id: null,
        name: '',
        unit: '',
        symbol: '',
    },
    measure: {
        name: '',
        unit: '',
        symbol: '',
    },
})

const submit = ref(false)

const closeModal = () => {
    localDialogOpen.value = false
}

const isCustomMetric = ref(false)
const selectedQuantityId = ref(null)

const quantityOptions = computed(() => props.quantities?.data ?? props.quantities ?? [])

watch(quantityOptions, (list) => {
    if (!selectedQuantityId.value) {
        selectedQuantityId.value = list.length ? list[0].id : 'custom'
    }
}, { immediate: true })

watch(() => selectedQuantityId.value, (val) => {
    if (!val) return
    if (val === 'custom') {
        isCustomMetric.value = true
        form.metric = { id: null, name: '', unit: '', symbol: '' }
    } else {
        const selected = quantityOptions.value.find((q) => q.id === val)
        if (selected) {
            isCustomMetric.value = false
            form.metric = {
                id: selected.id,
                name: selected.quantity,
                unit: selected.unit,
                symbol: selected.symbol,
            }
        }
    }
    form.measure = { name: '', unit: '', symbol: '' }
})

const suggestedMeasures = computed(() => {
    if (isCustomMetric.value) return []
    const selected = quantityOptions.value.find((q) => q.id === selectedQuantityId.value)
    return selected?.measures ?? []
})

const applySuggestion = (measure) => {
    form.measure = {
        name: measure.name,
        unit: measure.unit,
        symbol: measure.symbol,
    }
}

const handleSubmit = () => {
    console.log('form.metric', form.metric)
    console.log('form.measure', form.measure)
    if (
        !form.metric?.name ||
        !form.metric?.unit ||
        !form.metric?.symbol ||
        !form.measure?.name ||
        form.measure?.unit === '' ||
        !form.measure?.symbol
    ) {
        toast({
            title: t('admin.unit_measure.select_quantity_type_first'),
            description: t('admin.unit_measure.select_quantity_type_first'),
            variant: 'destructive',
            class:'bg-yellow-600 text-white',
        })
        return
    }

    submit.value = true

    // Prepare the data for the custom controller logic
    const requestData = {
        metric: form.metric,
        measure: form.measure
    }

    if (isEditing.value) {
        // Use Inertia's patch method for updates
        form.patch(route('unit-measures.update', props.editingItem.id), {
            data: requestData,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                emit('saved')
                form.reset()
                closeModal()
                submit.value = false
            },
            onError: (errors) => {
                console.error('Validation errors:', errors)
                // Modal will stay open automatically due to preserveState
                submit.value = false
            },
            onFinish: () => {
                submit.value = false
            }
        })
    } else {
        // Use Inertia's post method for creation
        form.post(route('unit-measures.store'), {
            data: requestData,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                emit('saved')
                form.reset()
                closeModal()
                submit.value = false
            },
            onError: (errors) => {
                console.error('Validation errors:', errors)
                // Modal will stay open automatically due to preserveState
                submit.value = false
            },
            onFinish: () => {
                submit.value = false
            }
        })
    }
}


</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ?t('general.edit', { name: t('admin.unit_measure.unit_measure') }) : t('general.create', { name: t('admin.unit_measure.unit_measure') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        width="w-[900px] max-w-[900px]"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <!-- server-side validation errors -->
            <div v-if="Object.keys(form.errors).length || Object.keys(props.errors || {}).length" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-red-700 text-sm">
                <ul class="list-disc ps-5 space-y-1">
                    <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                    <li v-for="(msg, key) in props.errors" :key="key">{{ msg }}</li>
                </ul>
            </div>
            <div class="py-4 space-y-8">
                <div class="grid grid-cols-2 gap-6">
                    <!-- Quantity Types Column -->
                    <div class="space-y-4 p-1">
                        <h3 class="text-lg font-semibold text-foreground">{{ t('admin.unit_measure.quantity') }}</h3>
                        <div class="space-y-2">
                            <NextRadio
                                v-for="quantity in quantityOptions"
                                :key="quantity.id"
                                v-model="selectedQuantityId"
                                :value="quantity.id"
                                :label="quantity.quantity"
                                name="metric"
                                :error="form.errors.metric || props.errors?.metric"
                            />
                            <NextRadio
                                v-model="selectedQuantityId"
                                value="custom"
                                :label="t('admin.unit_measure.custom_quantity')"
                                name="metric"
                                :error="form.errors.metric || props.errors?.metric"
                            />
                        </div>
                        <div v-if="isCustomMetric" class="space-y-4">
                            <NextInput
                                v-model="form.metric.name"
                                :label="t('general.name')"
                                :error="form.errors['metric.name'] || props.errors?.['metric.name']"
                                type="text"
                                :placeholder="t('general.enter', { text: t('general.name') })"
                            />
                            <NextInput
                                v-model="form.metric.unit"
                                :label="t('admin.unit_measure.base_unit')"
                                :error="form.errors['metric.unit'] || props.errors?.['metric.unit']"
                                type="text"
                                :placeholder="t('general.enter', { text: t('admin.unit_measure.unit') })"
                            />
                            <NextInput
                                v-model="form.metric.symbol"
                                :label="t('admin.shared.symbol')"
                                :error="form.errors['metric.symbol'] || props.errors?.['metric.symbol']"
                                type="text"
                                :placeholder="t('general.enter', { text: t('admin.shared.symbol') })"
                            />
                        </div>
                        <div v-else class="text-sm text-muted-foreground" v-if="form.metric.name">
                            {{ t('admin.unit_measure.base_unit') }}: {{ form.metric.unit }} ({{ form.metric.symbol }})
                        </div>
                    </div>

                    <!-- Measures Column -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-foreground">{{ t('admin.unit_measure.custom_measure') }}</h3>
                        <div class="space-y-4">
                            <NextInput
                                v-model="form.measure.name"
                                :label="t('general.name')"
                                :error="form.errors['measure.name'] || props.errors?.['measure.name']"
                                type="text"
                                :placeholder="t('general.enter', { text: t('general.name') })"
                            />
                            <NextInput
                                v-model="form.measure.unit"
                                :label="t('admin.unit_measure.unit')"
                                :error="form.errors['measure.unit'] || props.errors?.['measure.unit']"
                                type="number"
                                :placeholder="t('general.enter', { text: t('admin.unit_measure.unit') })"
                            />
                            <NextInput
                                v-model="form.measure.symbol"
                                :label="t('admin.shared.symbol')"
                                :error="form.errors['measure.symbol'] || props.errors?.['measure.symbol']"
                                type="text"
                                :placeholder="t('general.enter', { text: t('admin.shared.symbol') })"
                            />
                        </div>

                        <div v-if="suggestedMeasures.length" class="space-y-2">
                            <p class="text-sm font-medium text-foreground">{{ t('admin.unit_measure.suggested_measures') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="measure in suggestedMeasures"
                                    :key="measure.id || measure.name"
                                    type="button"
                                    class="border rounded-md px-3 py-2 text-left hover:border-primary"
                                    @click="applySuggestion(measure)"
                                >
                                    <div class="font-semibold">{{ measure.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ measure.unit }} {{ measure.symbol }}</div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
