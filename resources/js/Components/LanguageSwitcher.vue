<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const page = usePage()

const options = computed(() => {
    const locales = page.props.locales || []
    if (Array.isArray(locales) && locales.length) {
        return locales.map((l) => ({ value: l.id, label: l.name }))
    }
    return [
        { value: 'en', label: 'English' },
        { value: 'fa', label: 'فارسی' },
        { value: 'ps', label: 'پښتو' },
    ]
})

const current = computed({
    get: () => page.props.locale || 'en',
    set: (val) => {
        router.post(route('locale.update'), { locale: val }, { preserveScroll: true })
    },
})
</script>

<template>
    <select
        v-model="current"
        class="h-9 rounded-md border-border bg-background text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
    >
        <option v-for="opt in options" :key="opt.value" :value="opt.value">
            {{ opt.label }}
        </option>
    </select>

</template>


