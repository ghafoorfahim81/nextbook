<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed } from 'vue'
import { Button } from '@/Components/ui/button'
import NextInput from '@/Components/next/NextInput.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import { useForm } from '@inertiajs/vue3'
import { Label } from '@/Components/ui/label'
import NextSelect from '@/Components/next/NextSelect.vue'
import FloatingLabel     from "@/Components/next/FloatingLabel.vue";
// keep props reactive
const props = defineProps({
    branches: { type: [Array, Object], required: true },
    stores: { type: [Array, Object], required: true },
    unitMeasures: { type: [Array, Object], required: true },
    categories: { type: [Array, Object], required: true },
    companies: { type: [Array, Object], required: true },
    items: { type: [Array, Object], required: true },
})

// normalize lists whether they’re paginated or not
const branches = computed(() => props.branches?.data ?? props.branches ?? [])
const stores = computed(() => props.stores?.data ?? props.stores ?? [])
const unitMeasures = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])
const categories = computed(() => props.categories?.data ?? props.categories ?? [])
const companies = computed(() => props.companies?.data ?? props.companies ?? [])
const items = computed(() => props.items?.data ?? props.items ?? [])



// rows
const addRow = (index) => {
    if (index === form.openings.length - 1) {
        form.openings.push({ batch: '', expire_date: '', quantity: '', store_id: null })
    }
}
const removeRow = (idx) => {
    if (form.openings.length > 1) form.openings.splice(idx, 1)
}

// coerce numbers just before submit (optional but tidy)


const handleSubmit = () => {
    normalize()
    form.post(route('items.store'), {
        forceFormData: true, // required to send the file
        onSuccess: () => {
            form.reset()
        },
    })
}
</script>

<template>
    <AppLayout title="Create Item">
        <form @submit.prevent="handleSubmit" class="space-y-6">

        </form>
    </AppLayout>
</template>
