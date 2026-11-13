<script setup>
/**
 * The company wizard on sample data: four steps, each checked before you move
 * on, a review page with Edit links, and the animated set-up list. Nothing is
 * created.
 */
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { Check, Loader2 } from 'lucide-vue-next'
import DemoFrame from './DemoFrame.vue'
import { BUTTON, FIELD, PRIMARY } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)

const step = ref(0)
const error = ref('')
const phase = ref('form') // form | building | done
const doneCount = ref(0)
const form = reactive({
    name_en: '', name_fa: '', name_ps: '', abbreviation: '',
    business_type: 0, currency: 0, language: 0, calendar: 0, costing: 0,
    phone: '', email: '', license_number: '', license_issued: '', license_expires: '',
})
const steps = computed(() => L.value.steps)

const validate = () => {
    error.value = ''
    if (step.value === 0 && !form.name_en.trim()) { error.value = L.value.name_required; return false }
    if (step.value === 2 && form.license_issued && form.license_expires && form.license_expires < form.license_issued) { error.value = L.value.license_dates; return false }
    return true
}
const next = () => { if (validate()) step.value = Math.min(step.value + 1, steps.value.length - 1) }
const back = () => { error.value = ''; step.value = Math.max(step.value - 1, 0) }
const goto = (i) => { error.value = ''; step.value = i }

let timer = null
const create = () => {
    phase.value = 'building'
    doneCount.value = 0
    timer = setInterval(() => {
        doneCount.value += 1
        if (doneCount.value >= L.value.setup_steps.length) { clearInterval(timer); phase.value = 'done' }
    }, 380)
}
const restart = () => { phase.value = 'form'; step.value = 0; doneCount.value = 0 }
onBeforeUnmount(() => clearInterval(timer))

const review = computed(() => [
    [L.value.name, form.name_en || '—', 0],
    [L.value.business_type, L.value.business_types[form.business_type], 0],
    [L.value.base_currency, L.value.currencies[form.currency], 1],
    [L.value.calendar, L.value.calendars[form.calendar], 1],
    [L.value.costing, L.value.costings[form.costing], 1],
    [L.value.license_number, form.license_number || L.value.not_set, 2],
])
</script>

<template>
    <DemoFrame :banner="L.banner" :title="L.form_title">
        <template v-if="phase === 'form'">
            <ol class="flex flex-wrap gap-2 text-xs">
                <li v-for="(s, i) in steps" :key="s" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1" :class="i === step ? 'border-primary bg-primary/10 text-primary' : i < step ? 'border-emerald-500/40 text-emerald-600 dark:text-emerald-400' : 'border-border text-muted-foreground'">
                    <Check v-if="i < step" class="size-3" />
                    <span v-else>{{ i + 1 }}</span> {{ s }}
                </li>
            </ol>

            <div v-if="step === 0" class="grid gap-3 sm:grid-cols-2">
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.name_en }} *<input v-model="form.name_en" :class="FIELD" /></label>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.abbreviation }}<input v-model="form.abbreviation" :class="FIELD" /></label>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.name_fa }}<input v-model="form.name_fa" :class="FIELD" /></label>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.name_ps }}<input v-model="form.name_ps" :class="FIELD" /></label>
                <label class="space-y-1 text-xs text-muted-foreground sm:col-span-2">{{ L.business_type }}
                    <select v-model.number="form.business_type" :class="FIELD"><option v-for="(b, i) in L.business_types" :key="b" :value="i">{{ b }}</option></select>
                    <span class="block">{{ L.business_type_hint }}</span>
                </label>
            </div>

            <div v-if="step === 1" class="grid gap-3 sm:grid-cols-2">
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.base_currency }}
                    <select v-model.number="form.currency" :class="FIELD"><option v-for="(c, i) in L.currencies" :key="c" :value="i">{{ c }}</option></select>
                    <span class="block">{{ L.currency_hint }}</span>
                </label>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.language }}
                    <select v-model.number="form.language" :class="FIELD"><option v-for="(c, i) in L.languages" :key="c" :value="i">{{ c }}</option></select>
                </label>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.calendar }}
                    <select v-model.number="form.calendar" :class="FIELD"><option v-for="(c, i) in L.calendars" :key="c" :value="i">{{ c }}</option></select>
                </label>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.costing }}
                    <select v-model.number="form.costing" :class="FIELD"><option v-for="(c, i) in L.costings" :key="c" :value="i">{{ c }}</option></select>
                    <span class="block">{{ L.costing_hint }}</span>
                </label>
            </div>

            <div v-if="step === 2" class="grid gap-3 sm:grid-cols-2">
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.phone }}<input v-model="form.phone" :class="FIELD" /></label>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.email }}<input v-model="form.email" :class="FIELD" /></label>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.license_number }}<input v-model="form.license_number" :class="FIELD" /></label>
                <span class="text-xs text-muted-foreground">{{ L.license_hint }}</span>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.license_issued }}<input v-model="form.license_issued" type="date" :class="FIELD" /></label>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.license_expires }}<input v-model="form.license_expires" type="date" :class="FIELD" /></label>
            </div>

            <div v-if="step === 3" class="divide-y divide-border rounded-xl border border-border text-sm">
                <div v-for="r in review" :key="r[0]" class="flex items-center justify-between gap-3 px-3 py-2">
                    <span class="text-muted-foreground">{{ r[0] }}</span>
                    <span class="flex items-center gap-3 text-foreground">{{ r[1] }} <button type="button" class="text-xs text-primary underline" @click="goto(r[2])">{{ L.edit }}</button></span>
                </div>
            </div>

            <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
            <div class="flex gap-2">
                <button v-if="step > 0" type="button" :class="BUTTON" @click="back">{{ L.back }}</button>
                <button v-if="step < steps.length - 1" type="button" :class="PRIMARY" @click="next">{{ L.next }}</button>
                <button v-else type="button" :class="PRIMARY" @click="create">{{ L.create }}</button>
            </div>
        </template>

        <template v-else>
            <p class="font-semibold text-foreground">{{ phase === 'done' ? L.ready : L.building }}</p>
            <ul class="space-y-1.5 text-sm">
                <li v-for="(s, i) in L.setup_steps" :key="s" class="flex items-center gap-2" :class="i < doneCount ? 'text-foreground' : 'text-muted-foreground'">
                    <Check v-if="i < doneCount" class="size-4 text-emerald-500" />
                    <Loader2 v-else-if="phase === 'building' && i === doneCount" class="size-4 animate-spin" />
                    <span v-else class="size-4" />
                    {{ s }}
                </li>
            </ul>
            <button v-if="phase === 'done'" type="button" :class="BUTTON" @click="restart">{{ L.again }}</button>
        </template>
    </DemoFrame>
</template>
