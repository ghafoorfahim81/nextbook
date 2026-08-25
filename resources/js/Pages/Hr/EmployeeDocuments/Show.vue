<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import AttachmentList from '@/Components/AttachmentList.vue';
import { Button } from '@/Components/ui/button';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';
import { Pencil, User } from 'lucide-vue-next';

const { t } = useI18n();
const { can } = useAuth();

const props = defineProps({
    document: { type: Object, required: true },
});

const doc = computed(() => props.document?.data ?? props.document);
const attachments = computed(() => doc.value.attachments?.data ?? doc.value.attachments ?? []);

// Red once lapsed, amber inside the last month — the same reading the list
// gives the expiry column.
const expiryClasses = computed(() => {
    const d = doc.value.days_until_expiry;
    if (d === null || d === undefined) return 'text-foreground';
    if (d < 0) return 'text-red-600 dark:text-red-400 font-medium';
    if (d <= 30) return 'text-amber-600 dark:text-amber-400 font-medium';
    return 'text-foreground';
});

const expiryNote = computed(() => {
    const d = doc.value.days_until_expiry;
    if (!doc.value.expiry_date || d === null || d === undefined) return '';
    if (d < 0) return t('hr.expired');
    if (d <= 30) return t('hr.expires_in_days', { days: d });
    return '';
});

// The document is edited through the modal on the list, so "edit" walks back
// there rather than duplicating the form on this page.
const goToList = () => router.visit(route('employee-documents.index'));
</script>

<template>
    <AppLayout :title="`${t('hr.document')} ${doc.document_number || ''}`">
        <FormPageToolbar back-route="employee-documents.index" module="employee_documents" />

        <!-- Header -->
        <div class="mb-5 rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        {{ doc.document_type_label }}
                        <span v-if="doc.document_number"> — {{ doc.document_number }}</span>
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ doc.employee_name }}<span v-if="doc.employee_code"> ({{ doc.employee_code }})</span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full border px-3 py-1 text-xs"
                        :class="doc.is_verified
                            ? 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300'
                            : 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'"
                    >
                        {{ doc.is_verified ? t('hr.is_verified') : t('general.no') }}
                    </span>
                    <span
                        v-if="doc.is_expired"
                        class="rounded-full border border-red-500/30 bg-red-500/10 px-3 py-1 text-xs text-red-700 dark:text-red-300"
                    >
                        {{ t('hr.expired') }}
                    </span>

                    <Button
                        v-if="doc.employee_id"
                        size="sm" variant="outline"
                        class="h-9 gap-1"
                        @click="router.visit(route('employees.show', doc.employee_id))"
                    >
                        <User class="h-4 w-4" />
                        {{ t('hr.employee') }}
                    </Button>

                    <Button
                        v-if="can('employee_documents.update')"
                        size="sm" variant="outline"
                        class="h-9 gap-1"
                        @click="goToList"
                    >
                        <Pencil class="h-4 w-4" />
                        {{ t('general.edit', { name: '' }) }}
                    </Button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <!-- Detail -->
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm lg:col-span-2">
                <dl class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('hr.employee') }}</dt>
                        <dd>{{ doc.employee_name || '—' }}<span v-if="doc.employee_code"> ({{ doc.employee_code }})</span></dd>
                    </div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.document_type') }}</dt><dd>{{ doc.document_type_label || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.document_number') }}</dt><dd>{{ doc.document_number || '—' }}</dd></div>

                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.issued_by') }}</dt><dd>{{ doc.issued_by || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.issue_date') }}</dt><dd>{{ doc.issue_date || '—' }}</dd></div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('hr.expiry_date') }}</dt>
                        <dd :class="expiryClasses">
                            {{ doc.expiry_date || '—' }}
                            <span v-if="expiryNote"> ({{ expiryNote }})</span>
                        </dd>
                    </div>

                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">{{ t('admin.shared.remark') }}</dt>
                        <dd>{{ doc.remark || '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-4">
                    <AttachmentList :items="attachments" :label="t('general.attachments')" />
                </div>
            </div>

            <!-- Meta -->
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('general.info') }}</h2>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('hr.is_verified') }}</dt>
                        <dd>{{ doc.is_verified ? t('general.yes') : t('general.no') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('hr.verified_by') }}</dt>
                        <dd>{{ doc.verified_by || '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('hr.verified_at') }}</dt>
                        <dd>{{ doc.verified_at || '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('hr.reminder_days_before') }}</dt>
                        <dd>{{ doc.reminder_days_before ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-border pt-2">
                        <dt class="text-muted-foreground">{{ t('general.created_by') }}</dt>
                        <dd>{{ doc.created_by || '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('general.updated_by') }}</dt>
                        <dd>{{ doc.updated_by || '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>
