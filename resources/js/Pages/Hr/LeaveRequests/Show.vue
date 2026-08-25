<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import AttachmentList from '@/Components/AttachmentList.vue';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Button } from '@/Components/ui/button';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';
import { toast } from 'vue-sonner';
import { CheckCircle2, XCircle, Send, Ban, Pencil } from 'lucide-vue-next';

const { t } = useI18n();
const { can } = useAuth();

const props = defineProps({
    leaveRequest: { type: Object, required: true },
    balance: { type: Object, default: null },
});

const req = computed(() => props.leaveRequest?.data ?? props.leaveRequest);
const attachments = computed(() => req.value.attachments?.data ?? req.value.attachments ?? []);

const showReject = ref(false);
const busy = ref(false);

const rejectForm = useForm({ rejection_reason: '' });

const statusClasses = computed(() => {
    switch (req.value.status) {
        case 'approved':
            return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'rejected':
            return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        case 'pending':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'cancelled':
        case 'withdrawn':
            return 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300';
        default:
            return 'border-border bg-muted text-foreground';
    }
});

// The server publishes which transitions the state machine allows, so the UI
// never has to reimplement it — and can never offer one the backend refuses.
const allows = (target) => (req.value.allowed_transitions || []).includes(target);

// Approving can be refused for good reasons — no allocation for that leave
// type, not enough balance left. Those come back as validation errors on keys
// this page has no field for, so without this they vanished and the button
// looked like it did nothing.
const reportErrors = (errors) => {
    const messages = Object.values(errors || {}).flat().filter(Boolean);

    toast.error(t('general.error'), {
        description: messages.length ? messages.join(' ') : t('general.update_error'),
        class: 'bg-red-600',
    });
};

const transition = (action) => {
    busy.value = true;

    router.patch(route(`leave-requests.${action}`, req.value.id), {}, {
        preserveScroll: true,
        onError: reportErrors,
        onFinish: () => { busy.value = false; },
    });
};

const submitReject = () => {
    rejectForm.patch(route('leave-requests.reject', req.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showReject.value = false;
            rejectForm.reset();
        },
        onError: reportErrors,
    });
};
</script>

<template>
    <AppLayout :title="`${t('hr.leave_request')} #${req.number}`">
        <FormPageToolbar back-route="leave-requests.index" module="leave_requests" />

        <ModalDialog
            :open="showReject"
            :title="t('hr.reject')"
            :confirm-text="t('hr.reject')"
            :cancel-text="t('general.close')"
            width="w-[95vw] max-w-[95vw] sm:w-[520px] sm:max-w-[520px]"
            @update:open="showReject = $event"
            @confirm="submitReject"
            @cancel="showReject = false"
            :submitting="rejectForm.processing"
        >
            <div class="py-4">
                <NextTextarea
                    :label="t('hr.rejection_reason')"
                    v-model="rejectForm.rejection_reason"
                    :error="rejectForm.errors?.rejection_reason"
                />
            </div>
        </ModalDialog>

        <!-- Header -->
        <div class="mb-5 rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        #{{ req.number }} — {{ req.employee_name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        <span
                            v-if="req.leave_type_colour"
                            class="mr-1.5 inline-block h-2.5 w-2.5 rounded-full align-middle"
                            :style="{ backgroundColor: req.leave_type_colour }"
                        />
                        {{ req.leave_type_name }} · {{ req.from_date }} → {{ req.to_date }} · {{ req.days }} {{ t('hr.days') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border px-3 py-1 text-xs" :class="statusClasses">
                        {{ req.status_label }}
                    </span>

                    <Button
                        v-if="allows('pending') && can('leave_applications.update')"
                        size="sm" variant="outline"
                        class="h-9 gap-1 border-primary text-primary hover:bg-primary hover:text-white"
                        :disabled="busy"
                        @click="transition('submit')"
                    >
                        <Send class="h-4 w-4" />
                        {{ t('hr.submit_for_approval') }}
                    </Button>

                    <Button
                        v-if="allows('approved') && can('leave_applications.approve')"
                        size="sm"
                        class="h-9 gap-1 bg-green-600 text-white hover:bg-green-700"
                        :disabled="busy"
                        @click="transition('approve')"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        {{ t('hr.approve') }}
                    </Button>

                    <Button
                        v-if="allows('rejected') && can('leave_applications.reject')"
                        size="sm" variant="destructive"
                        class="h-9 gap-1"
                        :disabled="busy"
                        @click="showReject = true"
                    >
                        <XCircle class="h-4 w-4" />
                        {{ t('hr.reject') }}
                    </Button>

                    <Button
                        v-if="allows('cancelled') && can('leave_applications.update')"
                        size="sm" variant="outline"
                        class="h-9 gap-1"
                        :disabled="busy"
                        @click="transition('cancel')"
                    >
                        <Ban class="h-4 w-4" />
                        {{ t('hr.cancel_request') }}
                    </Button>

                    <Button
                        v-if="['draft', 'pending'].includes(req.status) && can('leave_applications.update')"
                        size="sm" variant="outline"
                        class="h-9 gap-1"
                        @click="router.visit(route('leave-requests.edit', req.id))"
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
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.employee') }}</dt><dd>{{ req.employee_name }} ({{ req.employee_code }})</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.leave_type') }}</dt><dd>{{ req.leave_type_name }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.is_paid') }}</dt><dd>{{ req.is_paid ? t('general.yes') : t('general.no') }}</dd></div>

                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.from_date') }}</dt><dd>{{ req.from_date }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.to_date') }}</dt><dd>{{ req.to_date }}</dd></div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('hr.days') }}</dt>
                        <dd>{{ req.days }}<span v-if="req.is_half_day"> ({{ req.half_day_period_label }})</span></dd>
                    </div>

                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.contact_during_leave') }}</dt><dd>{{ req.contact_during_leave || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.handover_to') }}</dt><dd>{{ req.handover_to_name || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.applied_at') }}</dt><dd>{{ req.applied_at || '—' }}</dd></div>

                    <div v-if="req.approved_by"><dt class="text-xs text-muted-foreground">{{ t('hr.approved_by') }}</dt><dd>{{ req.approved_by }}</dd></div>
                    <div v-if="req.rejection_reason" class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">{{ t('hr.rejection_reason') }}</dt>
                        <dd class="text-red-600 dark:text-red-400">{{ req.rejection_reason }}</dd>
                    </div>

                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">{{ t('hr.reason') }}</dt>
                        <dd>{{ req.reason || '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-4">
                    <AttachmentList :items="attachments" :label="t('general.attachments')" />
                </div>
            </div>

            <!-- Balance -->
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('hr.balance_after') }}</h2>

                <dl v-if="balance" class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted-foreground">{{ t('hr.entitled_days') }}</dt><dd>{{ balance.entitled }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted-foreground">{{ t('hr.carried_forward_days') }}</dt><dd>{{ balance.carried }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted-foreground">{{ t('hr.taken_days') }}</dt><dd>{{ balance.taken }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted-foreground">{{ t('hr.pending_days') }}</dt><dd>{{ balance.pending }}</dd></div>
                    <div class="flex justify-between border-t border-border pt-2 font-semibold">
                        <dt>{{ t('hr.available_days') }}</dt>
                        <dd :class="balance.available < 0 ? 'text-red-600 dark:text-red-400' : ''">{{ balance.available }}</dd>
                    </div>
                </dl>

                <p v-else class="text-sm text-muted-foreground">{{ t('hr.no_allocation_hint') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
