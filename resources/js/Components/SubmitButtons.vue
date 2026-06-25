<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Spinner } from '@/Components/ui/spinner'
import { Button } from '@/Components/ui/button'
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue'

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
    /**
     * Module slug used to resolve the per-module "confirm before save" preference
     * (user_preferences.confirmations[module]). When empty, no confirmation is shown
     * — e.g. User Management forms intentionally omit this.
     */
    module: { type: String, default: '' },
})

const emit = defineEmits(['create-and-new', 'save-and-print', 'cancel'])

const { t } = useI18n()
const page = usePage()

const isBusy = computed(() => props.createLoading || props.createAndNewLoading || props.saveAndPrintLoading)
const isDisabled = computed(() => props.disabled || isBusy.value)
const createText = computed(() => (props.createLoading ? props.creatingLabel : props.createLabel))
const createAndNewText = computed(() => (props.createAndNewLoading ? props.creatingLabel : props.createAndNewLabel))
const saveAndPrintText = computed(() => (props.saveAndPrintLoading ? props.creatingLabel : props.saveAndPrintLabel))

// Per-module save confirmation. Defaults to enabled when a module is provided.
const confirmOnSave = computed(() => {
    if (!props.module) return false
    const confirmations = page.props?.user_preferences?.confirmations
    return confirmations?.[props.module] ?? true
})

const confirmOpen = ref(false)
const pendingAction = ref(null) // 'create' | 'create-and-new' | 'save-and-print'
const createButtonRef = ref(null)

const runAction = (action) => {
    if (action === 'create') {
        // Submit the surrounding <form>, preserving each page's @submit.prevent handler.
        const formEl = createButtonRef.value?.$el?.closest('form')
            ?? createButtonRef.value?.closest?.('form')
        if (formEl) formEl.requestSubmit()
    } else if (action === 'create-and-new') {
        emit('create-and-new')
    } else if (action === 'save-and-print') {
        emit('save-and-print')
    }
}

const requestAction = (action, event) => {
    if (confirmOnSave.value) {
        event?.preventDefault?.()
        pendingAction.value = action
        confirmOpen.value = true
        return
    }
    if (action !== 'create') {
        // 'create' falls through to the native submit when no confirmation is needed.
        runAction(action)
    }
}

const handleCreateClick = (event) => requestAction('create', event)
const handleCreateAndNew = () => requestAction('create-and-new')
const handleSaveAndPrint = () => requestAction('save-and-print')

const handleConfirm = () => {
    confirmOpen.value = false
    const action = pendingAction.value
    pendingAction.value = null
    runAction(action)
}

const handleConfirmDialogClose = (value) => {
    confirmOpen.value = value
    if (!value) pendingAction.value = null
}
</script>

<template>
    <div class="flex items-center gap-2 [--radius:1.1rem] mt-2">
        <Button
            ref="createButtonRef"
            type="submit"
            variant="outline"
            class="  border-primary text-primary hover:bg-primary hover:text-white"
            :disabled="isDisabled"
            @click="handleCreateClick"
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
            @click="handleCreateAndNew"
        >
            {{ createAndNewText }}
            <Spinner v-if="createAndNewLoading" class="ml-2 h-4 w-4" />
        </Button>
        <Button
            v-if="showSaveAndPrint"
            type="button"
            variant="outline"
            class=" border-primary text-primary hover:bg-primary hover:text-white"
            :disabled="isDisabled"
            @click="handleSaveAndPrint"
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

        <ConfirmDeleteDialog
            :open="confirmOpen"
            :title="t('general.save_confirmation_title')"
            :description="t('general.save_confirmation_message')"
            :cancel-text="t('general.cancel')"
            :continue-text="t('general.confirm')"
            content-class="!top-[28%]"
            @update:open="handleConfirmDialogClose"
            @confirm="handleConfirm"
        />
    </div>
</template>
