<script setup>
/**
 * The branch form, shared by the create and edit pages: a branch belongs to
 * the company being worked in, so all it asks for is a name, where it is, an
 * optional parent branch and a remark.
 */
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, Loader2, Save, Store } from 'lucide-vue-next'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'

const props = defineProps({
    // The branch being edited; absent when creating.
    branch: { type: Object, default: null },
    parentOptions: { type: Array, default: () => [] },
})

const { t } = useI18n()
const isEdit = computed(() => !!props.branch?.id)

const form = useForm({
    name: props.branch?.name ?? '',
    location: props.branch?.location ?? '',
    parent_id: props.branch?.parent_id ?? null,
    remark: props.branch?.remark ?? '',
})

const title = computed(() => (isEdit.value ? t('admin.branch.edit') : t('admin.branch.new')))
const backHref = computed(() => (isEdit.value ? route('branches.show', props.branch.id) : route('workspace.branches')))

function submit() {
    if (isEdit.value) {
        form.put(route('branches.update', props.branch.id), { preserveScroll: true })
    } else {
        form.post(route('branches.store'), { preserveScroll: true })
    }
}
</script>

<template>
    <div class="w-full px-4 py-6 sm:px-6">
        <Link
            :href="backHref"
            class="mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
        >
            <ArrowLeft class="size-4 rtl:rotate-180" />
            {{ isEdit ? t('admin.branch.back_to_branch') : t('admin.branch.back') }}
        </Link>

        <div class="rounded-2xl border border-border bg-card shadow-sm">
            <div class="flex items-center gap-4 border-b border-border px-6 py-5">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Store class="size-6" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold tracking-tight text-foreground">{{ title }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ isEdit ? t('admin.branch.subtitle_edit') : t('admin.branch.subtitle_new') }}
                    </p>
                </div>
            </div>

            <form class="space-y-6 px-6 py-6" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <NextInput
                        v-model="form.name"
                        is-required
                        autofocus
                        :label="t('general.name')"
                        :placeholder="t('general.enter', { text: t('general.name') })"
                        :error="form.errors.name"
                    />
                    <NextInput
                        v-model="form.location"
                        :label="t('admin.branch.location')"
                        :placeholder="t('general.enter', { text: t('admin.branch.location') })"
                        :error="form.errors.location"
                    />
                    <div>
                        <NextSelect
                            v-model="form.parent_id"
                            :options="parentOptions"
                            label-key="name"
                            value-key="id"
                            :reduce="option => option.id"
                            :floating-text="t('admin.shared.parent')"
                            :error="form.errors.parent_id"
                            :searchable="true"
                        />
                        <p class="mt-2 text-xs text-muted-foreground">{{ t('admin.branch.parent_hint') }}</p>
                    </div>
                    <NextTextarea
                        v-model="form.remark"
                        :label="t('admin.shared.remark')"
                        :placeholder="t('general.enter', { text: t('admin.shared.remark') })"
                        :error="form.errors.remark"
                    />
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-border pt-5">
                    <Link
                        :href="backHref"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    >
                        {{ t('general.close') }}
                    </Link>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <Loader2 v-if="form.processing" class="size-4 motion-safe:animate-spin" />
                        <Save v-else class="size-4" />
                        {{ isEdit ? t('general.update') : t('general.create', { name: t('admin.branch.branch') }) }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
