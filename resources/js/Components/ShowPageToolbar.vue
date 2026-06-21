<script setup>
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { ArrowLeft, Printer, Download, SquarePen } from 'lucide-vue-next'
import { useAuth } from '@/composables/useAuth'

const { t } = useI18n()
const { can } = useAuth()

const props = defineProps({
    backRoute: { type: String, required: true },
    status: { type: String, default: null },
    editRoute: { type: String, default: null },
    editPermission: { type: String, default: null },
    exportUrl: { type: String, default: null },
    printUrl: { type: String, default: null },
})

const emit = defineEmits(['post', 'reverse'])

const canPost = () => props.status === 'draft'
const canReverse = () => props.status === 'posted'
const canEdit = () => {
    if (!props.editRoute || props.status !== 'draft') return false
    if (props.editPermission) return can(props.editPermission)
    return true
}
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <Button variant="outline" size="sm" @click="router.visit(route(backRoute))">
            <ArrowLeft class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
            {{ t('general.back') }}
        </Button>
        <div class="flex items-center gap-2">
            <Button
                v-if="canPost()"
                variant="default"
                size="sm"
                class="bg-green-600 text-white hover:bg-green-700"
                @click="emit('post')"
            >
                {{ t('general.post') }}
            </Button>
            <Button
                v-if="canReverse()"
                variant="destructive"
                size="sm"
                class="bg-red-600 text-white hover:bg-red-700"
                @click="emit('reverse')"
            >
                {{ t('general.reverse') }}
            </Button>
            <slot />
            <a v-if="exportUrl" :href="exportUrl" target="_blank" rel="noopener noreferrer">
                <Button variant="outline" size="sm">
                    <Download class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
                    {{ t('report.export_excel') }}
                </Button>
            </a>
            <a v-if="printUrl" :href="printUrl" target="_blank" rel="noopener noreferrer">
                <Button variant="outline" size="sm">
                    <Printer class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
                    {{ t('general.print') }}
                </Button>
            </a>
            <Button
                v-if="canEdit()"
                variant="default"
                size="sm"
                class="gap-1.5 bg-primary text-primary-foreground"
                @click="router.visit(editRoute)"
            >
                <SquarePen class="h-4 w-4" />
                {{ t('datatable.edit') }}
            </Button>
        </div>
    </div>
</template>
