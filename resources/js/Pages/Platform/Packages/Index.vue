<script setup>
/**
 * Packages (phase 10): what a customer gets for its price. Platform admin only.
 */
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import AppLayout from '@/Layouts/Layout.vue'
import ModalDialog from '@/Components/next/Dialog.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import { Switch } from '@/Components/ui/switch'
import { Label } from '@/Components/ui/label'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'

defineProps({
    packages: { type: Array, default: () => [] },
    modules: { type: Array, default: () => [] },
})

const { t } = useI18n()
const moduleName = (key) => t(`company.modules.${key}`)

const dialogOpen = ref(false)
const editing = ref(null)

const form = useForm({
    code: '', name: '', description: '', modules: [], max_users: null, max_branches: null,
    price: 0, currency: 'AFN', period_months: 12, is_active: true,
})

watch(editing, (item) => {
    form.clearErrors()
    form.code = item?.code ?? ''
    form.name = item?.name ?? ''
    form.description = item?.description ?? ''
    form.modules = [...(item?.modules ?? [])]
    form.max_users = item?.max_users ?? null
    form.max_branches = item?.max_branches ?? null
    form.price = item?.price ?? 0
    form.currency = item?.currency ?? 'AFN'
    form.period_months = item?.period_months ?? 12
    form.is_active = item?.is_active ?? true
})

const openCreate = () => {
    editing.value = null
    form.reset()
    form.modules = []
    dialogOpen.value = true
}
const openEdit = (item) => {
    editing.value = item
    dialogOpen.value = true
}

const toggleModule = (key) => {
    form.modules = form.modules.includes(key) ? form.modules.filter((m) => m !== key) : [...form.modules, key]
}

const save = () => {
    const isEdit = !!editing.value?.id
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false
            toast.success(t('general.success'))
        },
    }
    isEdit
        ? form.put(route('platform.packages.update', editing.value.id), options)
        : form.post(route('platform.packages.store'), options)
}

// A package in use is refused by the server with a message; nothing is
// recoverable from the trash here, so ask first.
const remove = (item) => {
    if (!window.confirm(t('general.delete', { name: item.name }))) return
    router.delete(route('platform.packages.destroy', item.id), { preserveScroll: true })
}

const limit = (value) => (value ?? t('platform.unlimited'))
const dialogTitle = computed(() => (editing.value ? t('platform.edit_package') : t('platform.new_package')))
</script>

<template>
    <AppLayout :title="t('platform.packages')">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">{{ t('platform.packages_hint') }}</p>
            <Button @click="openCreate">{{ t('platform.new_package') }}</Button>
        </div>

        <ModalDialog
            :open="dialogOpen"
            :title="dialogTitle"
            :confirmText="editing ? t('general.update') : t('general.create')"
            :cancel-text="t('general.close')"
            :closeable="true"
            :submitting="form.processing"
            @update:open="dialogOpen = $event"
            @confirm="save"
            @cancel="dialogOpen = false"
        >
            <form id="modalForm" class="grid gap-4 py-4" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-3">
                    <NextInput v-model="form.code" is-required :label="t('platform.code')" :error="form.errors.code" />
                    <div class="sm:col-span-2">
                        <NextInput v-model="form.name" is-required :label="t('platform.name')" :error="form.errors.name" />
                    </div>
                </div>
                <NextTextarea v-model="form.description" :label="t('platform.description')" :error="form.errors.description" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <NextInput v-model="form.price" type="number" :label="t('platform.price')" :error="form.errors.price" />
                    <NextInput v-model="form.currency" :label="t('platform.currency')" :error="form.errors.currency" />
                    <NextInput v-model="form.period_months" type="number" :label="t('platform.period_months')" :error="form.errors.period_months" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <NextInput v-model="form.max_users" type="number" :label="t('platform.max_users') + ' (' + t('platform.empty_limit') + ')'" :error="form.errors.max_users" />
                    <NextInput v-model="form.max_branches" type="number" :label="t('platform.max_branches') + ' (' + t('platform.empty_limit') + ')'" :error="form.errors.max_branches" />
                </div>
                <div>
                    <p class="mb-1 text-sm font-medium">{{ t('platform.modules') }}</p>
                    <p class="mb-2 text-xs text-muted-foreground">{{ t('platform.modules_hint') }}</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="key in modules" :key="key" class="flex items-center gap-2 text-sm">
                            <input type="checkbox" :checked="form.modules.includes(key)" @change="toggleModule(key)" />
                            {{ moduleName(key) }}
                        </label>
                    </div>
                    <p v-if="form.errors.modules" class="mt-1 text-xs text-destructive">{{ form.errors.modules }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Switch id="package_active" v-model="form.is_active" />
                    <Label for="package_active">{{ t('platform.active') }}</Label>
                </div>
            </form>
        </ModalDialog>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-xs text-muted-foreground">
                    <tr>
                        <th class="p-3 text-start">{{ t('platform.name') }}</th>
                        <th class="p-3 text-start">{{ t('platform.price') }}</th>
                        <th class="p-3 text-start">{{ t('platform.max_users') }}</th>
                        <th class="p-3 text-start">{{ t('platform.max_branches') }}</th>
                        <th class="p-3 text-start">{{ t('platform.modules') }}</th>
                        <th class="p-3 text-start">{{ t('platform.customers') }}</th>
                        <th class="p-3 text-start">{{ t('general.status') }}</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in packages" :key="item.id" class="border-t">
                        <td class="p-3">
                            <div class="font-medium">{{ item.name }}</div>
                            <div class="text-xs text-muted-foreground">{{ item.code }}</div>
                        </td>
                        <td class="p-3 whitespace-nowrap">{{ item.price }} {{ item.currency }} / {{ item.period_months }} {{ t('platform.months') }}</td>
                        <td class="p-3">{{ limit(item.max_users) }}</td>
                        <td class="p-3">{{ limit(item.max_branches) }}</td>
                        <td class="p-3">
                            <div class="flex max-w-xs flex-wrap gap-1">
                                <Badge v-for="key in item.modules" :key="key" variant="secondary">{{ moduleName(key) }}</Badge>
                            </div>
                        </td>
                        <td class="p-3">{{ item.subscriptions_count }}</td>
                        <td class="p-3">{{ item.is_active ? t('platform.active') : t('platform.inactive') }}</td>
                        <td class="p-3 whitespace-nowrap text-end">
                            <Button variant="ghost" size="sm" @click="openEdit(item)">{{ t('general.edit', { name: '' }) }}</Button>
                            <Button variant="ghost" size="sm" class="text-destructive" @click="remove(item)">{{ t('general.delete', { name: '' }) }}</Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
