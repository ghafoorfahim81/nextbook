<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import AttachmentList from '@/Components/AttachmentList.vue';
import { Button } from '@/Components/ui/button';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';
import { Pencil } from 'lucide-vue-next';

const { t } = useI18n();
const { can } = useAuth();

const props = defineProps({
    employee: { type: Object, required: true },
});

const emp = computed(() => props.employee?.data ?? props.employee);

// There is no ui/tabs component in this codebase; Preferences does the same
// ref + v-show, so this matches the house pattern rather than adding a dep.
const activeTab = ref('personal');

const tabs = computed(() => ([
    { key: 'personal', label: t('hr.tabs.personal') },
    { key: 'employment', label: t('hr.tabs.employment') },
    { key: 'payroll', label: t('hr.tabs.payroll') },
    { key: 'contracts', label: t('hr.tabs.contracts') },
    { key: 'documents', label: t('hr.tabs.documents') },
    { key: 'ledger', label: t('hr.tabs.ledger') },
]));

const statusBadgeClasses = computed(() => {
    switch (emp.value.employment_status) {
        case 'active':
            return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'probation':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'suspended':
        case 'on_leave':
            return 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300';
        case 'resigned':
        case 'terminated':
        case 'retired':
            return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        default:
            return 'border-border bg-muted text-foreground';
    }
});

// Expiry colouring is shared by contracts and documents: red once lapsed,
// amber inside the last month, otherwise unstyled.
const expiryClasses = (days) => {
    if (days === null || days === undefined) return 'text-muted-foreground';
    if (days < 0) return 'text-red-600 dark:text-red-400 font-medium';
    if (days <= 30) return 'text-amber-600 dark:text-amber-400 font-medium';
    return 'text-foreground';
};

const contracts = computed(() => emp.value.contracts?.data ?? emp.value.contracts ?? []);
const documents = computed(() => emp.value.documents?.data ?? emp.value.documents ?? []);
const attachments = computed(() => emp.value.attachments?.data ?? emp.value.attachments ?? []);
</script>

<template>
    <AppLayout :title="emp.full_name">
        <FormPageToolbar back-route="employees.index" module="employees" />

        <!-- Header -->
        <div class="mb-5 rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <img
                        v-if="emp.photo_url"
                        :src="emp.photo_url"
                        :alt="emp.full_name"
                        class="h-16 w-16 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-violet-500 text-xl font-semibold text-white"
                    >
                        {{ (emp.full_name || '?').slice(0, 1) }}
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">{{ emp.full_name }}</h1>
                        <p class="text-sm text-muted-foreground">
                            {{ emp.code }}
                            <span v-if="emp.designation_name"> · {{ emp.designation_name }}</span>
                            <span v-if="emp.department_name"> · {{ emp.department_name }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="rounded-full border px-3 py-1 text-xs" :class="statusBadgeClasses">
                        {{ emp.employment_status_label }}
                    </span>
                    <Button
                        v-if="can('employees.update')"
                        size="sm"
                        variant="outline"
                        class="h-9 border-primary text-primary hover:bg-primary hover:text-white"
                        @click="router.visit(route('employees.edit', emp.id))"
                    >
                        <Pencil class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
                        {{ t('general.edit', { name: t('hr.employee') }) }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-4 flex flex-wrap gap-1 border-b border-border">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="px-4 py-2 text-sm transition-colors"
                :class="activeTab === tab.key
                    ? 'border-b-2 border-violet-500 font-semibold text-violet-500'
                    : 'text-muted-foreground hover:text-foreground'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
            <!-- Personal -->
            <dl v-show="activeTab === 'personal'" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.father_name') }}</dt><dd>{{ emp.father_name || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.grand_father_name') }}</dt><dd>{{ emp.grand_father_name || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.gender') }}</dt><dd>{{ emp.gender_label || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.marital_status') }}</dt><dd>{{ emp.marital_status_label || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.date_of_birth') }}</dt><dd>{{ emp.date_of_birth || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.national_id') }}</dt><dd>{{ emp.national_id || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.passport_number') }}</dt><dd>{{ emp.passport_number || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.blood_group') }}</dt><dd>{{ emp.blood_group || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.phone_number') }}</dt><dd>{{ emp.phone_number || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('admin.shared.email') }}</dt><dd>{{ emp.email || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.whatsapp_number') }}</dt><dd>{{ emp.whatsapp_number || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('admin.shared.country') }}</dt><dd>{{ emp.country_name || '—' }}</dd></div>
                <div class="md:col-span-3"><dt class="text-xs text-muted-foreground">{{ t('hr.present_address') }}</dt><dd>{{ emp.present_address || '—' }}</dd></div>
                <div class="md:col-span-3">
                    <dt class="text-xs text-muted-foreground">{{ t('hr.sections.emergency_contact') }}</dt>
                    <dd>
                        {{ emp.emergency_contact_name || '—' }}
                        <span v-if="emp.emergency_contact_phone"> · {{ emp.emergency_contact_phone }}</span>
                        <span v-if="emp.emergency_contact_relation"> ({{ emp.emergency_contact_relation }})</span>
                    </dd>
                </div>
            </dl>

            <!-- Employment -->
            <dl v-show="activeTab === 'employment'" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.department') }}</dt><dd>{{ emp.department_name || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.designation') }}</dt><dd>{{ emp.designation_name || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.manager') }}</dt><dd>{{ emp.manager_name || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.employment_type') }}</dt><dd>{{ emp.employment_type_label || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.employment_status') }}</dt><dd>{{ emp.employment_status_label || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.joining_date') }}</dt><dd>{{ emp.joining_date || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.probation_end_date') }}</dt><dd>{{ emp.probation_end_date || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.confirmation_date') }}</dt><dd>{{ emp.confirmation_date || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.separation_date') }}</dt><dd>{{ emp.separation_date || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.linked_user') }}</dt><dd>{{ emp.user_name || '—' }}</dd></div>
                <div class="md:col-span-3" v-if="emp.separation_reason">
                    <dt class="text-xs text-muted-foreground">{{ t('hr.separation_reason') }}</dt><dd>{{ emp.separation_reason }}</dd>
                </div>
            </dl>

            <!-- Payroll -->
            <dl v-show="activeTab === 'payroll'" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.basic_salary') }}</dt><dd>{{ emp.basic_salary }} {{ emp.currency_code || '' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.payment_method') }}</dt><dd>{{ emp.payment_method_label || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.is_tax_exempt') }}</dt><dd>{{ emp.is_tax_exempt ? t('general.yes') : t('general.no') }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.bank_name') }}</dt><dd>{{ emp.bank_name || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.bank_account_number') }}</dt><dd>{{ emp.bank_account_number || '—' }}</dd></div>
                <div><dt class="text-xs text-muted-foreground">{{ t('hr.iban') }}</dt><dd>{{ emp.iban || '—' }}</dd></div>
            </dl>

            <!-- Contracts -->
            <div v-show="activeTab === 'contracts'" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-3 py-2 text-start">{{ t('hr.contract_number') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('hr.contract_type') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('hr.start_date') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('hr.end_date') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('general.status') }}</th>
                            <th class="px-3 py-2 text-end">{{ t('hr.basic_salary') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in contracts" :key="c.id" class="border-t border-border hover:bg-muted/30">
                            <td class="px-3 py-2">{{ c.contract_number }}</td>
                            <td class="px-3 py-2">{{ c.contract_type_label }}</td>
                            <td class="px-3 py-2">{{ c.start_date }}</td>
                            <td class="px-3 py-2" :class="expiryClasses(c.days_until_expiry)">
                                {{ c.end_date || '—' }}
                                <span v-if="c.days_until_expiry !== null && c.days_until_expiry >= 0 && c.days_until_expiry <= 30">
                                    ({{ t('hr.expires_in_days', { days: c.days_until_expiry }) }})
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ c.status_label }}</td>
                            <td class="px-3 py-2 text-end">{{ c.basic_salary }} {{ c.currency_code || '' }}</td>
                        </tr>
                        <tr v-if="!contracts.length">
                            <td colspan="6" class="px-3 py-6 text-center text-muted-foreground">{{ t('general.no_record_available') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Documents -->
            <div v-show="activeTab === 'documents'" class="space-y-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ t('hr.document_type') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.document_number') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.issue_date') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.expiry_date') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.is_verified') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in documents" :key="d.id" class="border-t border-border hover:bg-muted/30">
                                <td class="px-3 py-2">{{ d.document_type_label }}</td>
                                <td class="px-3 py-2">{{ d.document_number || '—' }}</td>
                                <td class="px-3 py-2">{{ d.issue_date || '—' }}</td>
                                <td class="px-3 py-2" :class="expiryClasses(d.days_until_expiry)">
                                    {{ d.expiry_date || '—' }}
                                    <span v-if="d.is_expired">({{ t('hr.expired') }})</span>
                                </td>
                                <td class="px-3 py-2">{{ d.is_verified ? t('general.yes') : t('general.no') }}</td>
                            </tr>
                            <tr v-if="!documents.length">
                                <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">{{ t('general.no_record_available') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <AttachmentList :items="attachments" :label="t('general.attachments')" />
            </div>

            <!-- Ledger -->
            <div v-show="activeTab === 'ledger'">
                <div v-if="emp.ledger_statement" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-border p-4">
                        <p class="text-xs text-muted-foreground">{{ t('hr.salary_payable') }}</p>
                        <p class="text-lg font-semibold">{{ emp.ledger_statement.payable_amount }}</p>
                    </div>
                    <div class="rounded-lg border border-border p-4">
                        <p class="text-xs text-muted-foreground">{{ t('general.debit') }}</p>
                        <p class="text-lg font-semibold">{{ emp.ledger_statement.total_debit }}</p>
                    </div>
                    <div class="rounded-lg border border-border p-4">
                        <p class="text-xs text-muted-foreground">{{ t('general.credit') }}</p>
                        <p class="text-lg font-semibold">{{ emp.ledger_statement.total_credit }}</p>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">{{ t('hr.no_ledger') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
