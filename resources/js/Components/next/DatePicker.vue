<template>
	<component
		:is="VuePersianDatetimePicker"
		class="block w-full"
		v-model="normalizedModel"
		:format="resolvedFormat"
		:display-format="resolvedDisplayFormat"
		:editable="editable"
		:auto-submit="autoSubmit"
		:type="type"
		:min="min || undefined"
		:max="max || undefined"
		:clearable="clearable"
		:input-attrs="{ placeholder, class: inputClass }"
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


