<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import Form from './Form.vue';
import { useI18n } from 'vue-i18n';

defineProps({
    filterOptions: { type: Object, default: () => ({}) },
    components: { type: [Array, Object], default: () => [] },
});

const { t } = useI18n();
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('hr.salary_structure') })">
        <div class="mx-auto max-w-5xl space-y-4">
            <div>
                <h1 class="text-xl font-semibold">{{ t('general.create', { name: t('hr.salary_structure') }) }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('hr.structure_create_hint') }}</p>
            </div>

            <Form :filterOptions="filterOptions" :components="components" />
        </div>
    </AppLayout>
</template>
