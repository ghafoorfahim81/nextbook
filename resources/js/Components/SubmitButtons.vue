<script setup>
import { computed } from 'vue'
import { Spinner } from '@/Components/ui/spinner'
import { Button } from '@/Components/ui/button'

const props = defineProps({
    createLabel: { type: String, required: true },
    createAndNewLabel: { type: String, required: true },
    saveAndPrintLabel: { type: String, default: '' },
    cancelLabel: { type: String, required: true },
    creatingLabel: { type: String, required: true },
    createLoading: { type: Boolean, default: false },
    createAndNewLoading: { type: Boolean, default: false },
    saveAndPrintLoading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    showCreateAndNew: { type: Boolean, default: true },
    showSaveAndPrint: { type: Boolean, default: false },
    showCancel: { type: Boolean, default: true },
})

defineEmits(['create-and-new', 'save-and-print', 'cancel'])

const isBusy = computed(() => props.createLoading || props.createAndNewLoading || props.saveAndPrintLoading)
const isDisabled = computed(() => props.disabled || isBusy.value)
const createText = computed(() => (props.createLoading ? props.creatingLabel : props.createLabel))
const createAndNewText = computed(() => (props.createAndNewLoading ? props.creatingLabel : props.createAndNewLabel))
const saveAndPrintText = computed(() => (props.saveAndPrintLoading ? props.creatingLabel : props.saveAndPrintLabel))
</script>

<template>
    <div class="flex items-center gap-2 [--radius:1.1rem] mt-2">
        <Button
            type="submit"
            variant="outline"
            class="  border-primary text-primary hover:bg-primary hover:text-white"
            :disabled="isDisabled" 
            
        >
        {{ createText }}
        <Spinner v-if="createLoading" class="ml-2 h-4 w-4" />
        </Button>
        <Button
            v-if="showCreateAndNew"
            type="button"
            variant="outline"
            class=" border-primary text-primary hover:bg-primary hover:text-white"
            :disabled="isDisabled"
            @click="$emit('create-and-new')"
        >
            {{ createAndNewText }}
            <Spinner v-if="createAndNewLoading" class="ml-2 h-4 w-4" />
        </Button>
        <button
            v-if="showSaveAndPrint"
            type="button"
            variant="outline"
            class=" border-primary text-primary hover:bg-primary hover:text-white"
            :disabled="isDisabled"
            @click="$emit('save-and-print')"
        >
            {{ saveAndPrintText }}
            <Spinner v-if="saveAndPrintLoading" class="ml-2 h-4 w-4" />
        </Button>
        <Button
            v-if="showCancel"
            type="button"
            variant="outline"
            class=" border-primary text-primary hover:bg-primary hover:text-white"
            :disabled="isDisabled"
            @click="$emit('cancel')"
        >
            {{ cancelLabel }}
        </Button>
    </div>
</template>
