<script setup>
import { ref, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { BookOpen } from 'lucide-vue-next'

const { t } = useI18n()
const page = usePage()

const open = ref(false)
let dismissed = false

function dismiss() {
    open.value = false
    if (dismissed) return
    dismissed = true
    // Fire and forget — the dialog is already closed client-side and the
    // shared prop only needs to be right on the next page load.
    window.axios?.post(route('onboarding.manual-prompt.dismiss')).catch(() => {})
}

function onOpenChange(value) {
    open.value = value
    if (!value) dismiss()
}

onMounted(() => {
    // Not on the manual page itself — the user is already where the prompt
    // would send them, and its own mount marks the prompt handled.
    const onManualPage = window.location?.pathname?.startsWith('/user-manual')
    if (page.props.auth?.user?.show_manual_prompt && !onManualPage) {
        open.value = true
    }
})
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="mb-1 flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <BookOpen class="size-5" />
                </div>
                <DialogTitle>{{ t('layout.manual_prompt_title') }}</DialogTitle>
                <DialogDescription class="leading-6">
                    {{ t('layout.manual_prompt_body') }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button variant="outline" @click="onOpenChange(false)">
                    {{ t('layout.manual_prompt_later') }}
                </Button>
                <Button as-child class="gap-2">
                    <Link :href="route('user-manual')" @click="dismiss">
                        <BookOpen class="size-4" />
                        {{ t('layout.manual_prompt_open') }}
                    </Link>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
