<script setup>
import { defineProps } from 'vue';
import { Button } from '@/Components/ui/button';
import { Plus } from 'lucide-vue-next';

const props = defineProps({
    onClick: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <Button @click="onClick" class="relative flex items-center justify-center p-2 hover:bg-teal-400">
        <span>Add New</span>
        <Plus />
    </Button>
</template>
