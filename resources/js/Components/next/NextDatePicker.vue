<template>
    <component
        :is="VuePersianDatetimePicker"
        :class="['block w-full', { 'no-icon': !showIcon }]"
		v-model="normalizedModel"
		:format="resolvedFormat"
		:display-format="resolvedDisplayFormat"
		:editable="editable"
		:auto-submit="autoSubmit"
		:type="type"
		:min="min || undefined"
		:max="max || undefined"
		:clearable="clearable"
		:input-attrs="{ placeholder, class: inputClass, style: 'width:100%' }"
		:locale="effectiveLocale"
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
</template>

<script setup>
import { computed } from 'vue'
import VuePersianDatetimePicker from 'vue3-persian-datetime-picker'

const props = defineProps({
	modelValue: [String, Number, Date],
	format: { type: String, default: '' },
	displayFormat: { type: String, default: '' },
	placeholder: { type: String, default: '' },
	inputClass: { type: String, default: 'form-control' },
	editable: { type: Boolean, default: false },
	autoSubmit: { type: Boolean, default: true },
	type: { type: String, default: 'date' },
	min: [String, Number, Date],
	max: [String, Number, Date],
	clearable: { type: Boolean, default: true },
	// 'fa' (shamsi/jalali) or 'en' (gregorian/miladi). If not provided, read from localStorage
	locale: { type: String, default: '' },
    // Toggle the calendar icon addon
    showIcon: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'change'])

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

// Determine locale from prop or localStorage (fallback to 'fa')
const effectiveLocale = computed(() => {
	if (props.locale) return props.locale
	const stored = typeof localStorage !== 'undefined' ? (localStorage.getItem('calendar_type') || '') : ''
	if (/miladi|gregorian|en/i.test(stored)) return 'en'
	return 'fa'
})

const isJalali = computed(() => effectiveLocale.value === 'fa')

// Resolve formats based on calendar type so output is consistent
const resolvedFormat = computed(() => {
	if (props.format && props.format !== 'date') return props.format
	return isJalali.value ? 'jYYYY/jMM/jDD' : 'YYYY/MM/DD'
})

const resolvedDisplayFormat = computed(() => {
	if (props.displayFormat) return props.displayFormat
	return isJalali.value ? 'jYYYY/jMM/jDD' : 'YYYY/MM/DD'
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
/* Ensure the date input fits exactly inside its container (e.g., table cell) */
:deep(.vpd-input-group) {
    width: 100%;
    max-width: 100%;
    display: flex;
    align-items: center;
    box-sizing: border-box;
}

/* Compact the calendar icon addon so it doesn't expand the cell */
:deep(.vpd-input-group .vpd-addon) {
    margin: 0;
    height: 36px;
    display: flex;
    align-items: center;
    padding: 0 8px;
}

/* Hide addon when showIcon is false by class added to root */
:deep(.no-icon .vpd-input-group .vpd-addon) {
    display: none !important;
}

/* Make the text input fill remaining space and be compact */
:deep(.vpd-input-group .vpd-input),
:deep(.vpd-input-group input) {
    width: 100%;
    max-width: 100%;
    height: 36px;
    line-height: 36px;
    padding: 0 8px;
    box-sizing: border-box;
}

/* Prevent internal container from forcing a fixed width */
:deep(.vpd-container) {
    width: auto;
    max-width: 100%;
}
</style>
