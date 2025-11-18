<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    owner: Object,
});

const owner = computed(() => props.owner?.data ?? props.owner);
</script>

<template>
    <AppLayout :title="owner?.name ?? 'Owner'">
        <div class="p-6 space-y-4">
            <h2 class="text-xl font-semibold">Owner Details</h2>
            <div class="grid grid-cols-2 gap-4">
                <div><strong>Name:</strong> {{ owner.name }}</div>
                <div><strong>Father Name:</strong> {{ owner.father_name }}</div>
                <div><strong>NIC:</strong> {{ owner.nic }}</div>
                <div><strong>Email:</strong> {{ owner.email }}</div>
                <div><strong>Phone:</strong> {{ owner.phone_number }}</div>
                <div><strong>Address:</strong> {{ owner.address }}</div>
                <div><strong>Ownership %:</strong> {{ owner.ownership_percentage }}</div>
                <div><strong>Active:</strong> {{ owner.is_active ? 'Yes' : 'No' }}</div>
            </div>
        </div>
    </AppLayout>
</template>


