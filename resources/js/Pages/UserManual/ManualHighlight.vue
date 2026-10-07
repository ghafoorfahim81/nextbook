<script setup>
import { computed } from 'vue'
import { highlightSegments } from './content/search'

const props = defineProps({
    text: { type: [String, Number], default: '' },
    query: { type: String, default: '' },
})

const parts = computed(() => highlightSegments(String(props.text ?? ''), props.query))
</script>

<template>
    <template v-for="(part, index) in parts" :key="index">
        <mark
            v-if="part.hit"
            class="rounded-[3px] bg-amber-300/70 px-0.5 text-inherit [box-decoration-break:clone] dark:bg-amber-400/35 dark:text-amber-50"
        >{{ part.text }}</mark>
        <template v-else>{{ part.text }}</template>
    </template>
</template>
