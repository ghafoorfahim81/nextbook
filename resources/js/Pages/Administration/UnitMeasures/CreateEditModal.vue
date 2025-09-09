<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import ModalDialog from '@/Components/next/Dialog.vue'
import vSelect from 'vue-select'
import NextInput from "@/Components/next/NextInput.vue";
import NextTextarea from "@/Components/next/NextTextarea.vue";
import NextRadio from "@/Components/next/NextRadio.vue";

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
          name: "Count",
          unit: "Pcs",
          symbol: "ea",
          description: "this is description for count type measure",
          measure: [
            {
              text: "pcs",
              name: "pcs",
              unit: 1,
              symbol: "ea",
              description: "this is description for pcs  measure",
            },
            {
              text: "Pair",
              name: "Pair",
              unit: 2,
              symbol: "pr",
              description: "this is description for pcs  measure",
            },
            {
              text: "Dozen",
              name: "Dozen",
              unit: 12,
              symbol: "dz",
              description: "this is description for pcs  measure",
            },
            {
              text: "Other",
              name: "",
              unit: 1,
              symbol: "",
              description: "",
            },
          ],
        },
        length: {
          name: "Length",
          unit: "Centimetre",
          symbol: "cm",
          description: "this is description for Centimetre type measure",
          measure: [
            {
              text: "Centimetre",
              name: "Centimetre",
              unit: 1,
              symbol: "cm",
              description: "this is description for Centimetre  measure",
            },
            {
              text: "Inch",
              name: "Inch",
              unit: 2.5,
              symbol: "in",
              description: "this is description for Inch  measure",
            },
            {
              text: "Meter",
              name: "Meter",
              unit: 100,
              symbol: "m",
              description: "this is description for Meter  measure",
            },
            {
              text: "Other",
              name: "",
              unit: 1,
              symbol: "",
              description: "",
            },
          ],
        },
        Area: {
          name: "Area",
          unit: "SquareCentimetre",
          symbol: "cm2",
          description: "this is description for SquareCentimetre type area",
          measure: [
            {
              text: "SquareCentimetre",
              name: "SquareCentimetre",
              unit: 1,
              symbol: "cm2",
              description: "this is description for SquareCentimetre  area",
            },
            {
              text: "SquareDecimeter",
              name: "SquareDecimeter",
              unit: 0.01,
              symbol: "dm2",
              description: "this is description for SquareInch  area",
            },
            {
              text: "SquareMetere",
              name: "SquareMeter",
              unit: 0.0001,
              symbol: "m2",
              description: "this is description for SquareMeter  area",
            },
            {
              text: "Other",
              name: "",
              unit: 1,
              symbol: "",
              description: "",
            },
          ],
        },
        Weight: {
          name: "Weight",
          unit: "Gram",
          symbol: "g",
          description: "this is description for Gram type measure",
          measure: [
            {
              text: "Gram",
              name: "Gram",
              unit: 1,
              symbol: "g",
              description: "this is description for Gram  measure",
            },
            {
              text: "Kilogram",
              name: "Kilogram",
              unit: 1000,
              symbol: "kg",
              description: "this is description for Kilogram  measure",
            },
            {
              text: "Ton",
              name: "Ton",
              unit: 1000000,
              symbol: "ton",
              description: "this is description for Ton  measure",
            },
            {
              text: "Other",
              name: "",
              unit: 1,
              symbol: "",
              description: "",
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
            onSuccess: () => {
                emit('saved')
                form.reset()
                closeModal()
                submit.value = false
            },
            onError: (errors) => {
                console.error('Validation errors:', errors)
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
            onSuccess: () => {
                emit('saved')
                form.reset()
                closeModal()
                submit.value = false
            },
            onError: (errors) => {
                console.error('Validation errors:', errors)
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
        :title="isEditing ? 'Edit Unit Measure' : 'Create Unit Measure'"
        :confirmText="isEditing ? 'Update' : 'Create'"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        width="w-[900px] max-w-[900px]"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="py-4">
                <div class="grid grid-cols-3 gap-6">
                    <!-- Quantity Types Column -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900">Quantity</h3>
                        <div class="space-y-2">
                            <NextRadio
                                v-for="(metric, key) in metricList"
                                :key="key"
                                v-model="form.metric"
                                :value="metric"
                                :label="metric.name"
                                name="metric"
                                @update:modelValue="onMetricChange"
                            />
                        </div>
                    </div>

                    <!-- Measures Column -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900">Measures</h3>
                        <div class="space-y-2" v-if="form.metric && form.metric.measure">
                            <NextRadio
                                v-for="measure in form.metric.measure"
                                :key="measure.text"
                                v-model="form.measure"
                                :value="measure"
                                :label="measure.text"
                                name="measure"
                            />
                        </div>
                        <div v-else class="text-sm text-gray-500">
                            Select a quantity type first
                        </div>
                    </div>

                    <!-- Other Measures Column -->
                    <div class="space-y-4" v-if="form.measure && form.measure.text === 'Other'">
                        <h3 class="text-lg font-semibold text-gray-900">Other Measures</h3>
                        <div class="space-y-4">
                            <NextInput
                                v-model="form.measure.name"
                                label="Other Measure Name"
                                type="text"
                                placeholder="Enter measure name"
                            />
                            <NextInput
                                v-model="form.measure.unit"
                                label="Measure Unit"
                                type="number"
                                placeholder="Enter unit value"
                            />
                            <NextInput
                                v-model="form.measure.symbol"
                                label="Measure Symbol"
                                type="text"
                                placeholder="Enter symbol"
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
