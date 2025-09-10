<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()

const options = [
    { value: 'en', label: 'English' },
    { value: 'fa', label: 'فارسی' },
    { value: 'ps', label: 'پښتو' },
]

function setLocale(next) {
    const normalized = next === 'pa' ? 'ps' : next
    locale.value = normalized
    try {
        localStorage.setItem('locale', normalized)
    } catch {}
    document.documentElement.setAttribute('lang', normalized)
    document.documentElement.setAttribute('dir', ['fa','ps','pa'].includes(normalized) ? 'rtl' : 'ltr')
}

const current = computed({
    get: () => locale.value,
    set: (val) => setLocale(val),
})
</script>

<template>
    <select
        v-model="current"
        class="h-9 rounded-md border bg-background text-sm shadow-sm focus:outline-none appearance-none"

    >
        <option v-for="opt in options" :key="opt.value" :value="opt.value">
            {{ opt.label }}
        </option>
    </select>

</template>


