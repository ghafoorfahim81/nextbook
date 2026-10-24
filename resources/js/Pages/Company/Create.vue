<script setup>
/**
 * Company set-up (phase 5): sign-up, and a super-admin adding another company
 * from the companies page. The steps live in CompanyWizard, which the edit page
 * shares.
 */
import { usePage } from '@inertiajs/vue3'
import CompanyWizard from '@/Components/Company/CompanyWizard.vue'

// The page's props, handed over explicitly; the wizard declares the ones it uses.
const page = usePage()
</script>

<template>
    <CompanyWizard v-bind="page.props" mode="create" />
</template>
