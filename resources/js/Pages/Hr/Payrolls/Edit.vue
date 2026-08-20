<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import Form from './Form.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    payroll: Object,
    filterOptions: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const record = computed(() => props.payroll?.data ?? props.payroll);
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('hr.payroll') })">
        <div class="mx-auto max-w-5xl space-y-4">
            <h1 class="text-xl font-semibold">
                {{ t('general.edit', { name: t('hr.payroll') }) }} #{{ record.number }}
            </h1>

            <Form :payroll="record" :filterOptions="filterOptions" />
        </div>
    </AppLayout>
</template>
