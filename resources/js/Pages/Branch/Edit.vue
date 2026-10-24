<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import BranchForm from '@/Components/Branch/BranchForm.vue'
import { useI18n } from 'vue-i18n'

defineProps({
    branch: { type: Object, required: true },
    parentOptions: { type: Array, default: () => [] },
})

const { t } = useI18n()
</script>

<template>
    <AppLayout :title="t('admin.branch.edit')">
        <BranchForm :branch="branch" :parent-options="parentOptions" />
    </AppLayout>
</template>
