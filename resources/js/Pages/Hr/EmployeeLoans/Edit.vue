<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import Form from './Form.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    employeeLoan: Object,
    filterOptions: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const loan = computed(() => props.employeeLoan?.data ?? props.employeeLoan);
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('hr.employee_loan') })">
        <div class="mx-auto max-w-5xl space-y-4">
            <h1 class="text-xl font-semibold">
                {{ t('general.edit', { name: t('hr.employee_loan') }) }} #{{ loan.number }}
            </h1>

            <Form :employeeLoan="loan" :filterOptions="filterOptions" />
        </div>
    </AppLayout>
</template>
