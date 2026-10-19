<template>
    <div
        class="next-field"
        :class="{
            // Floats on the same terms as an input: when it holds a value or
            // has focus. Pinned up, an empty picker sat in a row next to an
            // empty input and the two labels were in different places.
            'next-field--floated': Boolean(label) && (hasValue || isFocused),
            'next-field--error': Boolean(error),
            'next-field--disabled': disabled,
        }"
        @focusin="isFocused = true"
        @focusout="isFocused = false"
    >
        <component
            :is="VuePersianDatetimePicker"
            :class="['block w-full z-5000 no-error-style dark:text-white', { 'no-icon': !showIcon || !!label, 'icon-only': showIcon && !label }]"
            v-model="normalizedModel"
            :format="resolvedFormat"
            :display-format="resolvedDisplayFormat"
            :editable="editable"
            :auto-submit="autoSubmit"
            :type="type"
            :disabled="disabled"
            :clearable="clearable"
            :color="color"
            :label="''"
            :input-attrs="{ id, placeholder, class: inputClass, style: 'width:100%' }"
            :locale="effectiveLocale"
            :current="shouldShowCurrentDate" 
            :min="min"
            :max="resolvedMaxDate"
        >
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

        <!-- Same notched outline as NextInput / NextSelect (next-field.css):
             the frame is the fieldset and the label's clearance is its legend,
             so nothing paints a patch that has to match the surface behind it. -->
        <fieldset aria-hidden="true" class="next-field-outline">
            <legend v-if="label"><span>{{ label }}<span v-if="isRequired">*</span></span></legend>
        </fieldset>

        <label v-if="label" :for="id" class="next-field-label text-sm">{{ label }}<span
            v-if="isRequired" class="next-field-required">*</span></label>

        <!-- Display error outside the component if present -->
        <span v-if="error" class="mt-1 block text-red-500 text-sm">{{ error }}</span>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import VuePersianDatetimePicker from 'vue3-persian-datetime-picker'
import { usePage } from '@inertiajs/vue3'
import { alignDateToCalendar, formatGregorianDate, formatJalaliDate } from '@/utils/dateDefaults'

const user = computed(() => usePage().props.auth?.user || null)

const props = defineProps({
    modelValue: [String, Number, Date],
    label: { type: String, default: '' },
    id: { type: String, default: () => `dp-${Math.random().toString(36).slice(2, 9)}` },
    format: { type: String, default: '' },
    displayFormat: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    inputClass: { type: String, default: '' },
    editable: { type: Boolean, default: false },
    currentDate: { type: Boolean, default: false },
    autoSubmit: { type: Boolean, default: true },
    error: String,
    isRequired: Boolean,
    type: { type: String, default: 'date' },
    min: [String, Number, Date],
    max: [String, Number, Date],
    clearable: { type: Boolean, default: true },
    locale: { type: String, default: '' },
    showIcon: { type: Boolean, default: true },
    showLabel: { type: Boolean, default: false },
    popover: { type: String, default: 'bottom-left' },
    color: { type: String, default: 'hsl(var(--primary))' },
    disabled: { type: Boolean, default: false },
    lockFutureDates: { type: Boolean, default: true },
})
const calendarType = computed(() => user.value?.calendar_type || 'gregorian')
const emit = defineEmits(['update:modelValue', 'change'])
const initialized = ref(false)
const isFocused = ref(false)
const hasValue = computed(() => props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '')

function normalizeDigits(value) {
    return String(value)
        .replace(/[\u06F0-\u06F9]/g, digit => String(digit.charCodeAt(0) - 0x06F0))
        .replace(/[\u0660-\u0669]/g, digit => String(digit.charCodeAt(0) - 0x0660))
}

function normalizeDateValue(value) {
    if (props.type !== 'date' || value === null || value === undefined || value === '') {
        return value
    }

    if (value instanceof Date) {
        return calendarType.value === 'jalali' ? formatJalaliDate(value) : formatGregorianDate(value)
    }

    if (typeof value === 'string') {
        const normalized = normalizeDigits(value).trim().replace(/\//g, '-')

        // A value written in the other calendar (e.g. a Gregorian date straight from a
        // controller) would be read by the picker as if it were already in this one, so
        // convert it first. Values already in the right calendar pass through untouched.
        return alignDateToCalendar(normalized, calendarType.value)
    }

    return value
}

onMounted(() => {
    if (
        props.currentDate &&
        !initialized.value &&
        (props.modelValue === null || props.modelValue === '' || props.modelValue === undefined)
    ) {
        const today = new Date()
        const todayValue = calendarType.value === 'jalali' ? formatJalaliDate(today) : formatGregorianDate(today)
        emit('update:modelValue', todayValue)

        initialized.value = true
    }
})

// Use the picker's built-in current prop
const shouldShowCurrentDate = computed(() => {
    return props.currentDate && (!props.modelValue || props.modelValue === '' || props.modelValue === null)
})

// Disallow future dates by default. Set lock-future-dates to false for fields
// such as expiration dates that legitimately need to accept a future value.
const resolvedMaxDate = computed(() => {
    if (props.max !== null && props.max !== undefined && props.max !== '') {
        return props.max
    }

    if (props.type !== 'date' || !props.lockFutureDates) {
        return undefined
    }

    const today = new Date()
    return calendarType.value === 'jalali' ? formatJalaliDate(today) : formatGregorianDate(today)
})

// Enhanced model that sets current date as default when current-date is true
const model = computed({
    get: () => props.modelValue,
    set: (value) => {
        const normalized = normalizeDateValue(value)
        emit('update:modelValue', normalized)
        emit('change', normalized)
    },
})

// Avoid passing empty strings; the picker expects null/undefined when empty
const normalizedModel = computed({
    get: () => {
        const normalized = normalizeDateValue(model.value)
        return normalized === '' ? null : normalized
    },
    set: (v) => (model.value = v),
})

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

/* The frame belongs to .next-field-outline now, so the picker's own group is
   only a transparent box of the right height — two borders in the same place
   is what refilled the label's gap with a stub. */
:deep(.vpd-input-group) {
    width: 100%;
    max-width: 100%;
    display: flex;
    align-items: stretch;
    box-sizing: border-box;
    height: 2.5rem;
    min-height: 2.5rem;
    background-color: transparent !important;
    border: 1px solid transparent !important;
    box-shadow: none !important;
    overflow: hidden;
}

:deep(.vpd-input-group input) {
    background-color: transparent;
    border: 0;
    box-shadow: none;
    outline: none;
    padding-inline: calc(var(--next-field-notch) + var(--next-field-label-pad));
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
    background-color: hsl(var(--primary));
    color: white;
}

/* Make dates outside the allowed range visibly distinct. */
:deep(.vpd-day[disabled='true']) {
    color: hsl(var(--muted-foreground)) !important;
    cursor: not-allowed;
    opacity: 0.55;
}

:deep(.vpd-day[disabled='true'] .vpd-day-text) {
    color: hsl(var(--muted-foreground)) !important;
}



:deep(.vpd-weekday) {
    color: hsl(var(--primary)) !important;
    font-weight: 600;
}

/* Compact the calendar icon addon so it doesn't expand the cell */
:deep(.vpd-input-group .vpd-addon) {
    margin: 0;
    height: 100%;
    display: flex;
    align-items: center;
    padding: 0 8px;
    background-color: hsl(var(--primary)) !important;
}

/* v3 picker uses .vpd-icon-btn instead of .vpd-addon; style it similarly */
:deep(.vpd-input-group .vpd-icon-btn) {
    margin: 0;
    height: 100%;
    display: flex;
    align-items: center;
    padding: 0 8px;
    background-color: hsl(var(--primary)) !important;
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
    color: hsl(var(--primary)) !important;
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
