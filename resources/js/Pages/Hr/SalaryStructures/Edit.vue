<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import Form from './Form.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    salaryStructure: Object,
    filterOptions: { type: Object, default: () => ({}) },
    components: { type: [Array, Object], default: () => [] },
});

const { t } = useI18n();

const structure = computed(() => props.salaryStructure?.data ?? props.salaryStructure);
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('hr.salary_structure') })">
        <div class="mx-auto max-w-5xl space-y-4">
            <h1 class="text-xl font-semibold">{{ structure.name }}</h1>
            <Form :salaryStructure="structure" :filterOptions="filterOptions" :components="components" />
        </div>
    </AppLayout>
</template>
