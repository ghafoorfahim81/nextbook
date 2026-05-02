<script setup>
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { ArrowLeft } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    backRoute: { type: String, required: true },
    /** Route parameters object, or a single route parameter value (e.g. model id) */
    backRouteParams: { type: [Object, String, Number], default: () => ({}) },
    module: { type: String, required: true },
})

const { t } = useI18n()

function goBack() {
    const p = props.backRouteParams
    const hasObjParams = p && typeof p === 'object' && !Array.isArray(p) && Object.keys(p).length > 0
    const hasScalar = p !== undefined && p !== null && p !== '' && typeof p !== 'object'
    if (hasObjParams) {
        router.visit(route(props.backRoute, p))
        return
    }
    if (hasScalar) {
        router.visit(route(props.backRoute, p))
        return
    }
    router.visit(route(props.backRoute))
}
</script>

<template>
    <div
        class="mb-2 flex flex-wrap items-center gap-2 rounded-lg border border-primary/60 px-3 py-2 shadow-sm backdrop-blur-sm justify-between"
    >
        <div class="flex items-center gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-8 gap-1.5 bg-background"
                @click="goBack"
            >
                <ArrowLeft class="h-4 w-4 rtl:rotate-180" />
                {{ t('general.back') }}
            </Button>
        </div>
        <div class="flex items-center gap-2 ms-auto">
            <ModuleHelpButton :module="module" toolbar />
        </div>
    </div>
</template>
