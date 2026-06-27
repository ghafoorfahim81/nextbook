<script setup>
import { computed } from 'vue'
import { highlightSearchText, HIGHLIGHT_MARK_CLASS, HIGHLIGHT_MARK_ON_PRIMARY_CLASS, textHasSearchMatch } from '@/utils/highlightSearch'

const props = defineProps({
    text: { type: [String, Number], default: '' },
    query: { type: String, default: '' },
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'onPrimary'].includes(value),
    },
})

const displayText = computed(() => String(props.text ?? ''))
const normalizedQuery = computed(() => String(props.query ?? '').trim())

const markClass = computed(() => (
    props.variant === 'onPrimary' ? HIGHLIGHT_MARK_ON_PRIMARY_CLASS : HIGHLIGHT_MARK_CLASS
))

const highlightedHtml = computed(() => highlightSearchText(displayText.value, normalizedQuery.value, markClass.value))
const shouldHighlight = computed(() => textHasSearchMatch(displayText.value, normalizedQuery.value))
</script>

<template>
    <span v-if="shouldHighlight" v-html="highlightedHtml" />
    <span v-else>{{ displayText }}</span>
</template>
