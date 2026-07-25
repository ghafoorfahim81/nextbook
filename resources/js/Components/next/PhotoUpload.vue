<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { Camera, X } from 'lucide-vue-next'

const props = defineProps({
    // The newly selected File (v-model), or null.
    modelValue: { type: [Object, null], default: null },
    // URL of the already-saved photo (edit mode), if any.
    current: { type: String, default: null },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    showRemove: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])

const inputRef = ref(null)
const objectUrl = ref(null)
// When true the user cleared the current photo (edit mode).
const cleared = ref(false)

watch(() => props.modelValue, (file) => {
    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value)
        objectUrl.value = null
    }
    if (file instanceof File) {
        objectUrl.value = URL.createObjectURL(file)
        cleared.value = false
    }
})

onBeforeUnmount(() => {
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
})

const previewUrl = computed(() => {
    if (objectUrl.value) return objectUrl.value
    if (!cleared.value && props.current) return props.current
    return null
})

const pick = () => inputRef.value?.click()

const onChange = (e) => {
    const file = e.target.files?.[0]
    if (file) emit('update:modelValue', file)
    // Allow re-selecting the same file later.
    e.target.value = ''
}

const clear = () => {
    cleared.value = true
    emit('update:modelValue', null)
}
</script>

<template>
    <div class="flex flex-col items-center gap-2">
        <div class="relative">
            <button
                type="button"
                class="group relative flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-border bg-muted/40 transition-colors hover:border-primary"
                @click="pick"
            >
                <img v-if="previewUrl" :src="previewUrl" alt="" class="h-full w-full object-cover" />
                <Camera v-else class="h-7 w-7 text-muted-foreground" />
                <span class="absolute inset-0 hidden items-center justify-center bg-black/40 text-white group-hover:flex">
                    <Camera class="h-6 w-6" />
                </span>
            </button>
            <button
                v-if="previewUrl && showRemove"
                type="button"
                class="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white shadow hover:bg-red-600"
                :title="'remove'"
                @click.stop="clear"
            >
                <X class="h-3.5 w-3.5" />
            </button>
        </div>
        <span v-if="label" class="text-xs text-muted-foreground">{{ label }}</span>
        <span v-if="error" class="text-xs text-red-500">{{ error }}</span>
        <input ref="inputRef" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onChange" />
    </div>
</template>
