<script setup>
/**
 * Edit a company: the same wizard as Create, filled in from the company
 * (Administration\CompanyController::edit).
 */
import { usePage } from '@inertiajs/vue3'
import CompanyWizard from '@/Components/Company/CompanyWizard.vue'

// The page's props, handed over explicitly; the wizard declares the ones it uses.
const page = usePage()
</script>

<template>
    <CompanyWizard v-bind="page.props" mode="edit" />
</template>
