<template>
    <div class="relative">
        <component
            :is="VuePersianDatetimePicker"
            :class="['block w-full z-5000 no-error-style dark:text-white', { 'no-icon': !showIcon, 'icon-only': showIcon && !showLabel }]"
            v-model="normalizedModel"
            :format="resolvedFormat"
            :display-format="resolvedDisplayFormat"
            :editable="editable"
            :auto-submit="autoSubmit"
            :type="type"
            :disabled="disabled"
            :clearable="clearable"
            :color="color"
            :label="showLabel ? undefined : ''"
            :input-attrs="{ placeholder, class: inputClass, style: 'width:100%' }"
            :locale="effectiveLocale"
            :current="shouldShowCurrentDate"
        >
            <template v-if="!showLabel" #label>
                <!-- Empty template to override any default label -->
            </template>
            <template v-if="isJalali" #header-date="{ vm }">
                {{ vm.convertToLocaleNumber(vm.date.xFormat('ddd jD')) }} {{ monthLabel(vm.date) }}
            </template>
            <template v-if="isJalali" #month-item="{ monthItem }">
                {{ monthLabel(monthItem) }}
            </template>
            <template v-if="isJalali" #month-name="{ date }">
                <span>{{ monthLabel(date) }} {{ safeYear(date) }}</span>
            </template>
        </component>

        <!-- Display error outside the component if present -->
        <span v-if="error" class="mt-1 block text-red-500 text-sm">{{ error }}</span>
    </div>
</template>

<script setup>
import { computed, onMounted, watch, ref } from 'vue'
import VuePersianDatetimePicker from 'vue3-persian-datetime-picker'
import { usePage } from '@inertiajs/vue3'

const user = computed(() => usePage().props.auth?.user || null)

const props = defineProps({
    modelValue: [String, Number, Date],
    format: { type: String, default: '' },
    displayFormat: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    inputClass: { type: String, default: '' },
    editable: { type: Boolean, default: false },
    currentDate: { type: Boolean, default: false },
    autoSubmit: { type: Boolean, default: true },
    error: String,
    type: { type: String, default: 'date' },
    min: [String, Number, Date],
    max: [String, Number, Date],
    clearable: { type: Boolean, default: true },
    locale: { type: String, default: '' },
    showIcon: { type: Boolean, default: true },
    showLabel: { type: Boolean, default: false },
    popover: { type: String, default: 'bottom-left' },
    color: { type: String, default: '#8b5cf6' }, // violet-500
    disabled: { type: Boolean, default: false },
})
const calendarType = computed(() => user.value?.calendar_type || 'gregorian')
const emit = defineEmits(['update:modelValue', 'change'])
const initialized = ref(false)
onMounted(() => {
    if (
        props.currentDate &&
        !initialized.value &&
        (props.modelValue === null || props.modelValue === '' || props.modelValue === undefined)
    ) {
        const today = new Date()

        // Emit ISO date for Gregorian
        if (effectiveLocale.value === 'en') {
            emit('update:modelValue', today.toISOString().slice(0, 10))
        }
        // Emit Date object for Jalali picker
        else {
            emit('update:modelValue', today)
        }

        initialized.value = true
    }
})

// Use the picker's built-in current prop
const shouldShowCurrentDate = computed(() => {
    return props.currentDate && (!props.modelValue || props.modelValue === '' || props.modelValue === null)
})

// Enhanced model that sets current date as default when current-date is true
const model = computed({
    get: () => props.modelValue,
    set: (value) => {
        emit('update:modelValue', value)
        emit('change', value)
    },
})

// Avoid passing empty strings; the picker expects null/undefined when empty
const normalizedModel = computed({
    get: () => (model.value === '' ? null : model.value),
    set: (v) => (model.value = v),
})

const page = usePage()

// Determine picker calendar mode from Inertia locale (fallback to 'fa' / Jalali).
const effectiveLocale = computed(() => {
    if (calendarType.value === 'jalali') {
        return 'fa'
    }

    return 'en'
})

const isJalali = computed(() => effectiveLocale.value === 'fa')

// Resolve formats based on calendar type so output is consistent
const resolvedFormat = computed(() => {
    if (props.format && props.format !== 'date') return props.format
    return calendarType.value === 'jalali' ? 'jYYYY-jMM-jDD' : 'YYYY-MM-DD'
})

const resolvedDisplayFormat = computed(() => {
    if (props.displayFormat) return props.displayFormat
    return calendarType.value === 'jalali' ? 'jYYYY-jMM-jDD' : 'YYYY-MM-DD'
})

const afghanMonths = [
    'حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله',
    'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'
]

function monthLabel(monthItem) {
    try {
        const idx = typeof monthItem?.xMonth === 'function'
            ? monthItem.xMonth()
            : typeof monthItem?.jMonth === 'function'
                ? monthItem.jMonth()
            : typeof monthItem?.month === 'function'
                ? monthItem.month()
                : monthItem?.month
        return afghanMonths[idx ?? 0] ?? afghanMonths[0]
    } catch (e) {
        return afghanMonths[0]
    }
}

function safeYear(m) {
    try {
        return typeof m?.xYear === 'function' ? m.xYear() : m?.year?.() ?? ''
    } catch (e) {
        return ''
    }
}
</script>

<style scoped>
/* -----------------------------
   Input parity with NextInput / NextSelect
   - height: 40px (h-10 / 2.5rem)
   - border: 2px using theme --border
   - radius: theme --radius
   ----------------------------- */

/* Ensure the date input fits exactly inside its container */
:deep(.vpd-input-group) {
    width: 100%;
    max-width: 100%;
    display: flex;
    align-items: stretch;
    box-sizing: border-box;
    height: 2.5rem; /* 40px */
    min-height: 2.5rem;
    background-color: hsl(var(--background));
    border: 1px solid hsl(var(--border)) !important;
    border-radius: calc(var(--radius) - 2px);
    overflow: hidden; /* makes icon + input share same outer radius */
}

/* Focus parity (same as NextInput/NextSelect) */
:deep(.vpd-input-group:focus-within) {
    border-color: rgb(99 102 241) !important;
    box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.25);
}

:deep(.vpd-day) {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 33px;
    width: 36px; /* Set a fixed width for consistency */
    border-radius: 50%;
}

:deep(.vpd-day.selected) {
    background-color: #8b5cf6; /* Color for the selected day */
    color: white;
}



:deep(.vpd-weekday) {
    color: #8b5cf6 !important; /* violet-500 */
    font-weight: 600;
}

/* Compact the calendar icon addon so it doesn't expand the cell */
:deep(.vpd-input-group .vpd-addon) {
    margin: 0;
    height: 100%;
    display: flex;
    align-items: center;
    padding: 0 8px;
    background-color: #8b5cf6 !important; /* violet-500 */
}

/* v3 picker uses .vpd-icon-btn instead of .vpd-addon; style it similarly */
:deep(.vpd-input-group .vpd-icon-btn) {
    margin: 0;
    height: 100%;
    display: flex;
    align-items: center;
    padding: 0 8px;
    background-color: #8b5cf6 !important; /* violet-500 */
}

/* Hide addon when showIcon is false by class added to root */
:deep(.no-icon .vpd-input-group .vpd-addon) {
    display: none !important;
}
/* Also hide v3 icon button when showIcon is false */
:deep(.no-icon .vpd-input-group .vpd-icon-btn) {
    display: none !important;
}

/* Make the text input fill remaining space and be compact */
:deep(.vpd-input-group .vpd-input),
:deep(.vpd-input-group input) {
    width: 100%;
    max-width: 100%;
    height: 100%;
    line-height: normal;
    padding: 0 0.75rem; /* matches shadcn input px-3 */
    box-sizing: border-box;
    border: 0 !important;
    outline: none !important;
    box-shadow: none !important;
    background-color: transparent !important;
    color: hsl(var(--foreground));
    font-size: 0.875rem; /* text-sm */
}

/* Prevent internal container from forcing a fixed width */
:deep(.vpd-container) {
    width: auto;
    max-width: 100%;
}

/* If the picker marks the group as error, keep our theme border (errors are shown outside anyway) */
:deep(.no-error-style .vpd-input-group.error) {
    border-color: hsl(var(--border)) !important;
}

/* Position clear icon at the end of the input */
:deep(.vpd-input-group .vpd-clear-btn) {
    position: absolute !important;
    color: #c026d3 !important; /* fuchsia-600 */
}


/* LTR layout: clear button on the right */
:global([dir='ltr'] .vpd-input-group .vpd-clear-btn),
:global([dir="ltr"] .vpd-input-group .vpd-clear-btn) {
    right: 0 !important;
    left: auto !important;
}

/* In RTL layouts, the input "end" is the left side */
:global([dir='rtl'] .vpd-input-group .vpd-clear-btn),
:global([dir="rtl"] .vpd-input-group .vpd-clear-btn) {
    right: auto !important;
    left: 0 !important;
}

</style>
