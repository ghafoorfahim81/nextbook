<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import FormPreferencesPanel from '@/Components/FormPreferencesPanel.vue'
import { ArrowLeft, SlidersHorizontal } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    backRoute: { type: String, required: true },
    /** Route parameters object, or a single route parameter value (e.g. model id) */
    backRouteParams: { type: [Object, String, Number], default: () => ({}) },
    module: { type: String, required: true },
    showPreferences: { type: Boolean, default: false },
    /**
     * When set (and the page has no rich preferences panel of its own), the toolbar
     * renders a built-in Settings button + confirm-only panel exposing the per-module
     * "confirm before save" toggle. Value is the confirmations preference key.
     */
    confirmModule: { type: String, default: '' },
})

const emit = defineEmits(['preferences'])

const { t } = useI18n()

// Built-in confirm-only settings panel (used by modules without their own panel).
const showConfirmPanel = ref(false)

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
        class="mb-2 flex flex-wrap items-center gap-2 rounded-lg px-3 py-2 backdrop-blur-sm justify-between"
    >
        <div class="flex items-center gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-8 gap-1.5 bg-background border-primary/60 hover:bg-primary/40 hover:text-balck"
                @click="goBack"
            >
                <ArrowLeft class="h-4 w-4 rtl:rotate-180 text-primary" />
                {{ t('general.back') }}
            </Button>
        </div>
        <div class="flex items-center gap-2 ms-auto">
            <Button
                v-if="showPreferences"
                type="button"
                variant="outline"
                size="sm"
                class="h-8 gap-1.5 bg-background border-primary/60 hover:bg-primary/40"
                @click="emit('preferences')"
            >
                <SlidersHorizontal class="h-4 w-4 text-primary" />
                {{ t('general.settings') }}
            </Button>
            
            <Button
                v-else-if="confirmModule"
                type="button"
                variant="outline"
                size="sm"
                class="h-8 gap-1.5 bg-background border-primary/60 hover:bg-primary/40"
                @click="showConfirmPanel = true"
            >
                <SlidersHorizontal class="h-4 w-4 text-primary" />
                {{ t('general.settings') }}
            </Button>
            <ModuleHelpButton :module="module" toolbar />
        </div>
    </div>

    <FormPreferencesPanel
        v-if="confirmModule && !showPreferences"
        v-model:open="showConfirmPanel"
        :module="confirmModule"
        :title="t('general.settings')"
    />
</template>
