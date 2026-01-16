<script setup>
import { computed } from 'vue'
import { Spinner } from '@/components/ui/spinner'

const props = defineProps({
    createLabel: { type: String, required: true },
    createAndNewLabel: { type: String, required: true },
    cancelLabel: { type: String, required: true },
    creatingLabel: { type: String, required: true },
    createLoading: { type: Boolean, default: false },
    createAndNewLoading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    showCreateAndNew: { type: Boolean, default: true },
    showCancel: { type: Boolean, default: true },
})

defineEmits(['create-and-new', 'cancel'])

const isBusy = computed(() => props.createLoading || props.createAndNewLoading)
const isDisabled = computed(() => props.disabled || isBusy.value)
const createText = computed(() => (props.createLoading ? props.creatingLabel : props.createLabel))
const createAndNewText = computed(() => (props.createAndNewLoading ? props.creatingLabel : props.createAndNewLabel))
</script>

<template>
    <div class="flex items-center gap-2 [--radius:1.1rem]">
        <button
            type="submit"
            class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white disabled:bg-gray-300"
            :disabled="isDisabled"
            size="sm"
        >
        {{ createText }}
        <Spinner v-if="createLoading" class="ml-2 h-4 w-4" />
        </button>
        <button
            v-if="showCreateAndNew"
            type="button"
            class="btn btn-primary px-4 py-2 rounded-md bg-primary border text-white disabled:bg-gray-300"
            :disabled="isDisabled"
            @click="$emit('create-and-new')"
        >
            {{ createAndNewText }}
            <Spinner v-if="createAndNewLoading" class="ml-2 h-4 w-4" />
        </button>
        <button
            v-if="showCancel"
            type="button"
            class="btn px-4 py-2 rounded-md border"
            :disabled="isDisabled"
            @click="$emit('cancel')"
        >
            {{ cancelLabel }}
        </button>
    </div>
</template>
