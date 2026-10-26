<script setup>
/**
 * One allocation rule (phase 7): its name, and its split across branches.
 *
 * A new rule needs its first split. On an existing rule every earlier version
 * stays on view, read-only, and a new version can be added from a date; a
 * version on a date that already has one replaces it. Expenses already posted
 * keep the split they were posted with.
 */
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Layout.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import { Button } from '@/Components/ui/button'
import { Switch } from '@/Components/ui/switch'
import { Label } from '@/Components/ui/label'

const props = defineProps({
    rule: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
})

const { t } = useI18n()
const isEdit = computed(() => !!props.rule?.id)

const blankShares = () => props.branches.slice(0, 2).map((branch) => ({ branch_id: branch.id, percentage: '' }))

const form = useForm({
    name: props.rule?.name ?? '',
    description: props.rule?.description ?? '',
    is_active: props.rule?.is_active ?? true,
    effective_from: '',
    // A new rule starts with its first split; an existing one with none,
    // which leaves its versions as they are.
    shares: isEdit.value ? [] : blankShares(),
})

const total = computed(() => form.shares.reduce((sum, share) => sum + (Number(share.percentage) || 0), 0))
const totalOk = computed(() => Math.abs(total.value - 100) < 0.0001)
const unusedBranches = (current) => props.branches.filter(
    (branch) => branch.id === current || !form.shares.some((share) => share.branch_id === branch.id),
)

const addShare = () => {
    const free = props.branches.find((branch) => !form.shares.some((share) => share.branch_id === branch.id))
    form.shares.push({ branch_id: free?.id ?? null, percentage: '' })
}
const removeShare = (index) => form.shares.splice(index, 1)

const save = () => {
    // Nothing typed in the new version means the split stays as it is.
    form.transform((data) => (data.shares.length ? data : { ...data, shares: undefined, effective_from: undefined }))

    isEdit.value
        ? form.put(route('allocation-rules.update', props.rule.id), { preserveScroll: true, onSuccess: () => form.reset('shares', 'effective_from') })
        // The edit page it lands on is this same component: start it fresh, or
        // the first split typed here would sit in its "new version" fields.
        : form.post(route('allocation-rules.store'), { preserveState: false })
}

const shareError = (index, field) => form.errors[`shares.${index}.${field}`]
</script>

<template>
    <AppLayout :title="isEdit ? rule.name : t('general.create', { name: t('dimension.allocation_rule') })">
        <div class="mx-auto w-full max-w-4xl space-y-6">
            <Link :href="route('allocation-rules.index')" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4 rtl:rotate-180" /> {{ t('dimension.back') }}
            </Link>

            <section class="space-y-4 rounded-xl border border-border bg-card p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <NextInput v-model="form.name" is-required :label="t('dimension.name')" :error="form.errors.name" />
                    <div class="flex items-center gap-2 sm:pt-6">
                        <Switch id="rule_active" v-model:checked="form.is_active" />
                        <Label for="rule_active">{{ t('dimension.active') }}</Label>
                    </div>
                </div>
                <NextTextarea v-model="form.description" :label="t('dimension.description')" :rows="2" :error="form.errors.description" />
            </section>

            <!-- Earlier versions, newest first: read-only. -->
            <section v-if="isEdit" class="space-y-3 rounded-xl border border-border bg-card p-5">
                <h2 class="text-sm font-semibold text-foreground">{{ t('dimension.versions') }}</h2>
                <p v-if="!rule.versions.length" class="text-sm text-muted-foreground">{{ t('dimension.no_version') }}</p>
                <div v-for="version in rule.versions" :key="version.effective_from" class="rounded-lg border border-border p-3">
                    <p class="mb-2 text-xs font-medium text-muted-foreground">{{ t('dimension.from') }} {{ version.effective_from }}</p>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="share in version.shares" :key="share.branch_id" class="rounded-full bg-muted px-2.5 py-0.5 text-sm">
                            {{ share.branch }} · {{ share.percentage }}%
                        </span>
                    </div>
                </div>
            </section>

            <!-- The split to add: the first one, or a new version. -->
            <section class="space-y-4 rounded-xl border border-border bg-card p-5">
                <div>
                    <h2 class="text-sm font-semibold text-foreground">{{ isEdit ? t('dimension.new_version') : t('dimension.first_version') }}</h2>
                    <p v-if="isEdit" class="text-xs text-muted-foreground">{{ t('dimension.new_version_hint') }}</p>
                </div>

                <div class="max-w-xs">
                    <NextDate v-model="form.effective_from" :label="t('dimension.effective_from')" :error="form.errors.effective_from" />
                </div>

                <div class="space-y-2">
                    <div v-for="(share, index) in form.shares" :key="index" class="grid grid-cols-[1fr_8rem_auto] items-start gap-2">
                        <NextSelect
                            v-model="share.branch_id"
                            :options="unusedBranches(share.branch_id)"
                            label-key="name"
                            value-key="id"
                            :reduce="(option) => option.id"
                            :floating-text="t('dimension.branch')"
                            :error="shareError(index, 'branch_id')"
                        />
                        <NextInput v-model="share.percentage" type="number" min="0" max="100" step="0.01" :label="t('dimension.percentage')" :error="shareError(index, 'percentage')" />
                        <Button type="button" variant="ghost" size="icon" class="mt-1" @click="removeShare(index)">
                            <Trash2 class="size-4 text-red-600" />
                        </Button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <Button type="button" variant="outline" size="sm" :disabled="form.shares.length >= branches.length" @click="addShare">
                        <Plus class="size-4" /> {{ t('dimension.add_branch') }}
                    </Button>
                    <p v-if="form.shares.length" class="text-sm font-medium" :class="totalOk ? 'text-emerald-600' : 'text-amber-600'">
                        {{ t('dimension.total') }}: {{ Number(total.toFixed(4)) }}%
                        <span v-if="!totalOk" class="font-normal"> — {{ t('dimension.total_must_be_100') }}</span>
                    </p>
                </div>
                <p v-if="form.errors.shares" class="text-sm text-red-600">{{ form.errors.shares }}</p>
            </section>

            <div class="flex justify-end">
                <Button :disabled="form.processing" @click="save">{{ t('dimension.save') }}</Button>
            </div>
        </div>
    </AppLayout>
</template>
