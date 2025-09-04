<template>
    <div class="relative">
        <!-- Input container with NextInput styling -->
        <div class="relative">
            <!-- The actual input -->
            <input
                :id="id"
                v-model="inputValue"
                :type="inputType"
                :max="maxValue"
                :min="minValue"
                :step="stepValue"
                :disabled="disabled"
                placeholder=" "
                class="peer block w-full rounded-md border border-input bg-background px-3 py-3 pr-12 text-sm shadow-sm
                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2
                disabled:cursor-not-allowed disabled:opacity-50"
                @input="handleInput"
                @blur="handleBlur"
            />

            <!-- Toggle button positioned absolutely -->
            <button
                type="button"
                @click="toggleDiscountType"
                :disabled="disabled"
                class="absolute right-2 top-1/2 -translate-y-1/2 px-2 py-1 rounded text-white text-sm font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2"
                :class="discountType === 'percentage'
                    ? 'bg-teal-500 hover:bg-teal-600 focus:ring-teal-500'
                    : 'bg-blue-500 hover:bg-blue-600 focus:ring-blue-500'"
            >
                <span v-if="discountType === 'percentage'">%</span>
                <span v-else>$</span>
            </button>

            <!-- Floating label -->
            <label
                :for="id"
                class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 rounded bg-background px-1
                text-muted-foreground transition-all duration-150
                peer-placeholder-shown:top-1/2 peer-placeholder-shown:text-sm
                peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:text-foreground
                peer-[:not(:placeholder-shown)]:top-0
                peer-[:not(:placeholder-shown)]:-translate-y-1/2
                peer-[:not(:placeholder-shown)]:text-xs">
                {{ label }}
                <span v-if="isRequired" class="text-red-600 ms-[2px]">*</span>
            </label>
        </div>

        <!-- Error message -->
        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
    modelValue: [String, Number],
    discountType: {
        type: String,
        default: 'percentage',
        validator: (value) => ['percentage', 'currency'].includes(value)
    },
    label: { type: String, required: true },
    id: { type: String, default: () => `discount-${Math.random().toString(36).slice(2, 9)}` },
    disabled: Boolean,
    isRequired: Boolean,
    error: String,
    hint: String
})

const emit = defineEmits(['update:modelValue', 'update:discountType'])

// Local state for discount type
const localDiscountType = ref(props.discountType)

// Computed properties
const inputValue = computed({
    get: () => props.modelValue || '',
    set: (value) => emit('update:modelValue', value)
})

const inputType = computed(() => {
    return localDiscountType.value === 'percentage' ? 'number' : 'number'
})

const maxValue = computed(() => {
    return localDiscountType.value === 'percentage' ? 100 : null
})

const minValue = computed(() => {
    return 0
})

const stepValue = computed(() => {
    return localDiscountType.value === 'percentage' ? 0.01 : 0.01
})

// Methods
const toggleDiscountType = () => {
    localDiscountType.value = localDiscountType.value === 'percentage' ? 'currency' : 'percentage'
    emit('update:discountType', localDiscountType.value)
}

const handleInput = (event) => {
    let value = event.target.value

    // For percentage, ensure it doesn't exceed 100
    if (localDiscountType.value === 'percentage') {
        const numValue = parseFloat(value)
        if (!isNaN(numValue) && numValue > 100) {
            value = '100'
            event.target.value = value
        }
    }

    // For currency, ensure it's a positive number
    if (localDiscountType.value === 'currency') {
        const numValue = parseFloat(value)
        if (!isNaN(numValue) && numValue < 0) {
            value = '0'
            event.target.value = value
        }
    }

    inputValue.value = value
}

const handleBlur = () => {
    // Ensure the value is properly formatted
    if (inputValue.value && !isNaN(parseFloat(inputValue.value))) {
        const numValue = parseFloat(inputValue.value)

        if (localDiscountType.value === 'percentage' && numValue > 100) {
            inputValue.value = '100'
        } else if (numValue < 0) {
            inputValue.value = '0'
        }
    }
}

// Watch for external changes to discount type
watch(() => props.discountType, (newType) => {
    localDiscountType.value = newType
})

// Watch for changes in discount type to reset value if needed
watch(localDiscountType, (newType, oldType) => {
    if (newType !== oldType && inputValue.value) {
        const numValue = parseFloat(inputValue.value)

        // If switching from percentage to currency and value is > 100, reset to 0
        if (oldType === 'percentage' && newType === 'currency' && numValue > 100) {
            inputValue.value = '0'
        }
    }
})
</script>
