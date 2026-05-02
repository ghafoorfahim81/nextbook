<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/Components/ui/select'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
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
        router.post(route('locale.update'), { locale: val }, { preserveScroll: true, preserveState: false })
    },
})

const selectDir = computed(() =>
    page.props.direction === 'rtl' ? 'rtl' : 'ltr',
)
</script>

<template>
    <Select v-model="current" :dir="selectDir">
        <SelectTrigger class="h-7 w-[110px] text-xs border-input md:w-[130px]">
            <SelectValue :placeholder="t('general.language')" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="opt in options"
                :key="opt.value"
                :value="opt.value"
                class="px-5 py-2 text-xs data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[highlighted]:bg-primary data-[highlighted]:text-primary-foreground"
            >
                {{ opt.label }}
            </SelectItem>
        </SelectContent>
    </Select>

    <!-- <select
        v-model="current"
        class="h-9 rounded-md border-border bg-background text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
    >
        <option v-for="opt in options" :key="opt.value" :value="opt.value">
            {{ opt.label }}
        </option>
    </select> -->

</template>


