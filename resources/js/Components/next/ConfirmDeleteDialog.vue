<script setup>
import { ref } from 'vue'
import { cn } from '@/lib/utils'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Spinner } from '@/Components/ui/spinner'
const props = defineProps({
    open: Boolean,
    title: String,
    description: String,
    cancelText: String,
    continueText: String,
    // While the delete is in flight the dialog stays up with a spinner: closing
    // it would hide the only sign that anything is happening, and leaving the
    // button live invites a second delete on the same row.
    loading: { type: Boolean, default: false },
    loadingText: String,
    contentClass: { type: String, default: '' },
    // On for every caller, deletes included — the operator's decision: the
    // answer to a confirmation is nearly always yes, and reaching for Cancel
    // with the mouse only to press Enter anyway was the slower path. A caller
    // that wants the cautious default can still turn this off.
    focusConfirm: { type: Boolean, default: true },
})
const emit = defineEmits(['confirm', 'update:open'])

const confirmButton = ref(null)

/**
 * Radix focuses the first tabbable element in the content, which is Cancel.
 * That is the wrong default here — the answer is almost always yes, and the
 * operator's hand is on Enter — so the ring starts on Confirm instead.
 */
const onOpenAutoFocus = (event) => {
    if (!props.focusConfirm) return

    event.preventDefault()
    // Button renders a single root through Primitive, so $el is the <button>.
    const el = confirmButton.value?.$el ?? confirmButton.value
    el?.focus?.()
}
</script>

<template>
    <Dialog :open="open" @update:open="value => !loading && emit('update:open', value)">
        <!-- Proportions are taken from the reference confirmation dialog: a 500px
             box with a 12px corner, 20px between the text block and the buttons,
             and a body line at 16px rather than 14. The box and the 36px buttons
             were already close to that; what the reference actually gets right is
             the typography — two sentences at a readable size, sitting in a frame
             that is only as big as they need. -->
        <DialogContent
            :class="cn('gap-5 rounded-xl p-6 sm:max-w-[31.25rem] sm:rounded-xl', contentClass)"
            @open-auto-focus="onOpenAutoFocus"
        >
            <DialogHeader class="gap-y-2">
                <DialogTitle>{{ title }} ?</DialogTitle>
                <DialogDescription class="text-base">{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <!-- A shared minimum width so the two read as a pair rather than
                     as one button the width of its word and one the width of
                     another — "Cancel" and "Delete" are not the same length in any
                     of the three languages. -->
                <Button
                    variant="outline"
                    class="min-w-[5.5rem] rounded-lg"
                    :disabled="loading"
                    @click="$emit('update:open', false)"
                >
                    {{ cancelText }}
                </Button>
                <Button
                    ref="confirmButton"
                    class="min-w-[5.5rem] gap-2 rounded-lg"
                    :disabled="loading"
                    @click="$emit('confirm')"
                >
                    <Spinner v-if="loading" />
                    {{ loading ? (loadingText || continueText) : continueText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
