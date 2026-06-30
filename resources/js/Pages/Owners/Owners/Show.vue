<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import AttachmentList from '@/Components/AttachmentList.vue'
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { useAuth } from '@/composables/useAuth'
import {
    User, Phone, Mail, MapPin, FileText, Percent, CheckCircle,
    Landmark, Wallet, ArrowLeft, SquarePen,
} from 'lucide-vue-next'

const { t } = useI18n()
const { can } = useAuth()

const props = defineProps({
    owner: { type: Object, required: true },
    drawings: { type: Object, default: () => [] },
})

const owner = computed(() => props.owner?.data ?? props.owner ?? {})
const drawings = computed(() => props.drawings?.data ?? props.drawings ?? [])

const formatAmount = (value) => {
    if (value === null || value === undefined || value === '') return '-'
    return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })
}

const details = computed(() => [
    { label: t('general.name'), value: owner.value?.name, icon: User },
    { label: t('owner.father_name'), value: owner.value?.father_name, icon: User },
    { label: t('owner.nic'), value: owner.value?.nic || '-', icon: FileText },
    { label: t('owner.ownership_percentage'), value: `${owner.value?.ownership_percentage ?? owner.value?.share_percentage ?? 0}%`, icon: Percent },
    { label: t('general.email'), value: owner.value?.email || '-', icon: Mail },
    { label: t('owner.phone_number'), value: owner.value?.phone_number || '-', icon: Phone },
    { label: t('general.address'), value: owner.value?.address || '-', icon: MapPin },
    { label: t('general.status'), value: owner.value?.is_active ? t('general.active') : t('general.inactive'), icon: CheckCircle },
    { label: t('owner.capital_account'), value: owner.value?.capital_account_name || '-', icon: Landmark },
    { label: t('owner.drawing_account'), value: owner.value?.drawing_account_name || '-', icon: Wallet },
])
</script>

<template>
    <AppLayout :title="`${t('owner.owner')} - ${owner.name || ''}`">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5 bg-background border-primary/60 hover:bg-primary/40"
                    @click="router.visit(route('owners.index'))"
                >
                    <ArrowLeft class="h-4 w-4 rtl:rotate-180 text-primary" />
                    {{ t('general.back') }}
                </Button>
                <Button
                    v-if="can('owners.update') && owner.id"
                    variant="default"
                    size="sm"
                    class="gap-1.5 bg-primary text-primary-foreground"
                    @click="router.visit(route('owners.edit', owner.id))"
                >
                    <SquarePen class="h-4 w-4" />
                    {{ t('datatable.edit') }}
                </Button>
            </div>

            <!-- Details -->
            <fieldset class="rounded-xl border border-border bg-muted/40 px-5 pb-5 pt-3">
                <legend class="px-2 text-sm font-semibold text-violet-500">{{ owner.name }}</legend>
                <div class="flex items-center gap-2 mb-4">
                    <div class="bg-violet-500 text-white p-1.5 rounded">
                        <User class="w-4 h-4" />
                    </div>
                    <h3 class="text-sm font-semibold text-foreground">{{ t('general.details') }}</h3>
                </div>
                <div class="grid gap-x-6 gap-y-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
                    <div v-for="detail in details" :key="detail.label" class="flex items-start gap-2">
                        <component :is="detail.icon" class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0">
                            <div class="text-xs text-muted-foreground">{{ detail.label }}</div>
                            <div class="text-sm font-medium text-foreground break-words">{{ detail.value }}</div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <!-- Capital contribution -->
            <fieldset
                v-if="owner.capital_account_transaction"
                class="rounded-xl border border-violet-500 px-5 pb-5 pt-3"
            >
                <legend class="px-2 flex items-center gap-2 text-sm font-semibold text-violet-500">
                    <Landmark class="h-4 w-4" />
                    {{ t('owner.capital_contribution') }}
                </legend>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <div class="text-xs text-muted-foreground">{{ t('general.amount') }}</div>
                        <div class="text-sm font-medium">{{ owner.opening_currency?.symbol || '' }} {{ formatAmount(owner.amount) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">{{ t('admin.currency.currency') }}</div>
                        <div class="text-sm font-medium">{{ owner.opening_currency?.code || '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">{{ t('general.rate') }}</div>
                        <div class="text-sm font-medium">{{ owner.rate ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">{{ t('general.account') }}</div>
                        <div class="text-sm font-medium">{{ owner.bank_account_name || '-' }}</div>
                    </div>
                </div>
            </fieldset>

            <!-- Attachments -->
            <fieldset class="rounded-xl border border-border px-5 pb-5 pt-3">
                <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('general.attachments') }}</legend>
                <AttachmentList :items="owner.attachments || []" />
                <p v-if="!(owner.attachments && owner.attachments.length)" class="text-sm text-muted-foreground">-</p>
            </fieldset>

            <!-- Drawing history -->
            <fieldset class="rounded-xl border border-border px-5 pb-5 pt-3">
                <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('owner.drawing_history') }}</legend>
                <div v-if="drawings.length" class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-muted-foreground">
                                <th class="px-2 py-2">{{ t('general.number') }}</th>
                                <th class="px-2 py-2">{{ t('general.date') }}</th>
                                <th class="px-2 py-2">{{ t('general.amount') }}</th>
                                <th class="px-2 py-2">{{ t('general.status') }}</th>
                                <th class="px-2 py-2 text-right">{{ t('general.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="drawing in drawings" :key="drawing.id" class="border-b border-border/60">
                                <td class="px-2 py-2">{{ drawing.number || '-' }}</td>
                                <td class="px-2 py-2">{{ drawing.date || '-' }}</td>
                                <td class="px-2 py-2">
                                    {{ drawing.currency?.symbol || '' }} {{ formatAmount(drawing.amount) }}
                                </td>
                                <td class="px-2 py-2">{{ drawing.status || '-' }}</td>
                                <td class="px-2 py-2 text-right">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="router.visit(route('drawings.show', drawing.id))"
                                    >
                                        {{ t('general.show') }}
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-muted-foreground">-</p>
            </fieldset>
        </div>
    </AppLayout>
</template>
