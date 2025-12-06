<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import ModalDialog from '@/Components/next/Dialog.vue'
import NextInput from "@/Components/next/NextInput.vue";
import NextRadio from "@/Components/next/NextRadio.vue";
import { useI18n } from 'vue-i18n';
const { t } = useI18n()
const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object, // ✅ this is passed from Index.vue
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
    metric: {},
    measure: {},
})

const submit = ref(false)

const closeModal = () => {
    localDialogOpen.value = false
}

const onMetricChange = () => {
    form.measure = {}
}

const  metricList = {
        count: {
          name: t('admin.unit_measure.count'),
          unit: 'Pcs',
          symbol: "ea",
          measure: [
            {
              text: t('admin.unit_measure.pcs'),
              name: "pcs",
              unit: 1,
              symbol: "ea",
            },
            {
              text: t('admin.unit_measure.pair'),
              name: "Pair",
              unit: 2,
              symbol: "pr",

            },
            {
              text: t('admin.unit_measure.dozen'),
              name: "Dozen",
              unit: 12,
              symbol: "dz",

            },
            {
              text: t('admin.unit_measure.other'),
              name: "",
              unit: 1,
              symbol: "",

            },
          ],
        },
        length: {
          name: t('admin.unit_measure.length'),
          unit: "Centimetre",
          symbol: "cm",

          measure: [
            {
              text: t('admin.unit_measure.centimetre'),
              name: "Centimetre",
              unit: 1,
              symbol: "cm",

            },
            {
              text: t('admin.unit_measure.inch'),
              name: "Inch",
              unit: 2.5,
              symbol: "in",

            },
            {
              text: t('admin.unit_measure.metre'),
              name: "Meter",
              unit: 100,
              symbol: "m",

            },
            {
              text: t('admin.unit_measure.other'),
              name: "",
              unit: 1,
              symbol: "",

            },
          ],
        },
        Area: {
          name: t('admin.unit_measure.area'),
          unit: "SquareCentimetre",
          symbol: "cm2",

          measure: [
            {
              text: t('admin.unit_measure.square_centimetre'),
              name: "SquareCentimetre",
              unit: 1,
              symbol: "cm2",

            },
            {
                text: t('admin.unit_measure.square_decimeter'),
              name: "SquareDecimeter",
              unit: 0.01,
              symbol: "dm2",

            },
            {
              text: t('admin.unit_measure.square_meter'),
              name: "SquareMeter",
              unit: 0.0001,
              symbol: "m2",

            },
            {
              text: t('admin.unit_measure.other'),
              name: "",
              unit: 1,
              symbol: "",

            },
          ],
        },
        Weight: {
          name: t('admin.unit_measure.weight'),
          unit: "Gram",
          symbol: "g",
          measure: [
            {
              text: t('admin.unit_measure.gram'),
              name: "Gram",
              unit: 1,
              symbol: "g",
            },
            {
              text: t('admin.unit_measure.kilogram'),
              name: "Kilogram",
              unit: 1000,
              symbol: "kg",
            },
            {
              text: t('admin.unit_measure.ton'),
              name: "Ton",
              unit: 1000000,
              symbol: "ton",
            },
            {
              text: t('admin.unit_measure.other'),
              name: "",
              unit: 1,
              symbol: "",
            },
          ],
        },
        volume: {
          name: t('admin.unit_measure.volume'),
          unit: "Litre",
          symbol: "l",
          measure: [
            {
              text: t('admin.unit_measure.litre'),
              name: "Litre",
              unit: 1,
              symbol: "l",
            },
            {
              text: t('admin.unit_measure.millilitre'),
              name: "Millilitre",
              unit: 0.001,
              symbol: "ml",
            },
            {
              text: t('admin.unit_measure.other'),
              name: "",
              unit: 1,
              symbol: "",
            },
          ],
        },
      }

const handleSubmit = () => {
    if (!form.metric || !form.measure) {
        // Show validation error - you can add a toast notification here
        console.warn('Please select both a quantity type and measure')
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
            <div class="py-4">
                <div class="grid grid-cols-3 gap-6">
                    <!-- Quantity Types Column -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-foreground">{{ t('admin.unit_measure.quantity') }}</h3>
                        <div class="space-y-2">
                            <NextRadio
                                v-for="(metric, key) in metricList"
                                :key="key"
                                v-model="form.metric"
                                :error="form.errors.metric || props.errors?.metric"
                                :value="metric"
                                :label="metric.name"
                                name="metric"
                                @update:modelValue="onMetricChange"
                            />
                        </div>
                    </div>

                    <!-- Measures Column -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-foreground">{{ t('admin.unit_measure.unit_measure') }}</h3>
                        <div class="space-y-2" v-if="form.metric && form.metric.measure">
                            <NextRadio
                                v-for="measure in form.metric.measure"
                                :key="measure.text"
                                v-model="form.measure"
                                :error="form.errors.measure || props.errors?.measure"
                                :value="measure"
                                :label="measure.text"
                                name="measure"
                            />
                        </div>
                        <div v-else class="text-sm text-muted-foreground">
                            {{ t('admin.unit_measure.select_quantity_type_first') }}
                        </div>
                    </div>

                    <!-- Other Measures Column -->
                    <div class="space-y-4" v-if="form.measure && form.measure.text === t('admin.unit_measure.other')">
                        <h3 class="text-lg font-semibold text-foreground">{{ t('admin.unit_measure.other_measure') }}</h3>
                        <div class="space-y-4">
                            <NextInput
                                v-model="form.measure.name"
                                :label="t('general.name')"
                                :error="form.errors.name || props.errors?.name"
                                type="text"
                                :placeholder="t('general.enter', { text: t('general.name') })"
                            />
                            <NextInput
                                v-model="form.measure.unit"
                                :label="t('admin.unit_measure.unit')"
                                :error="form.errors.unit || props.errors?.unit"
                                type="number"
                                :placeholder="t('general.enter', { text: t('admin.unit_measure.unit') })"
                            />
                            <NextInput
                                v-model="form.measure.symbol"
                                :label="t('admin.shared.symbol')"
                                :error="form.errors.symbol || props.errors?.symbol"
                                type="text"
                                :placeholder="t('general.enter', { text: t('admin.shared.symbol') })"
                            />
                        </div>
                    </div>
                </div>

                <!-- Selected Values Display -->
                <!-- <div v-if="form.metric && form.measure" class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Selected Configuration:</h4>
                    <div class="text-sm text-gray-600">
                        <p><strong>Quantity Type:</strong> {{ form.metric.name }}</p>
                        <p><strong>Measure:</strong> {{ form.measure.text }}</p>
                        <p v-if="form.measure.name"><strong>Custom Name:</strong> {{ form.measure.name }}</p>
                        <p><strong>Unit:</strong> {{ form.measure.unit }}</p>
                        <p><strong>Symbol:</strong> {{ form.measure.symbol }}</p>
                    </div>
                </div> -->
            </div>
        </form>
    </ModalDialog>
</template>
