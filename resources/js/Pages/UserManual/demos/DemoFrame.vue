<script setup>
import { Sparkles } from 'lucide-vue-next'

defineProps({
    banner: { type: String, required: true },
    title: { type: String, default: '' },
})
</script>

<template>
    <div class="overflow-hidden rounded-2xl border-2 border-dashed border-primary/40 bg-card">
        <div class="flex items-center justify-between gap-2 border-b border-dashed border-primary/40 bg-primary/10 px-4 py-2 text-xs font-medium text-primary">
            <span class="inline-flex items-center gap-1.5"><Sparkles class="size-3.5" /> {{ banner }}</span>
            <span class="text-muted-foreground">{{ title }}</span>
        </div>
        <div class="space-y-4 p-4"><slot /></div>
    </div>
</template>
