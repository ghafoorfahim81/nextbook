<script setup>
/**
 * Phase 10: tells a company when its subscription is about to end, has ended,
 * or is blocked. Driven by the shared `subscription` prop, which the server
 * sends only when something needs saying.
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { TriangleAlert } from 'lucide-vue-next'

const { t } = useI18n()
const page = usePage()

const subscription = computed(() => page.props?.subscription ?? null)

const message = computed(() => {
    const s = subscription.value
    if (!s) return ''
    if (s.state === 'blocked') return t('platform.banner.blocked')
    if (s.state === 'expired') return t('platform.banner.expired', { date: s.ends_on_display })
    return t('platform.banner.expiring', { date: s.ends_on_display, days: s.days_left })
})

// One string: whitespace between inline spans is condensed away by the compiler.
const text = computed(() => [
    message.value,
    subscription.value?.reason ? t('platform.banner.blocked_reason', { reason: subscription.value.reason }) : '',
    t('platform.banner.contact'),
].filter(Boolean).join(' '))

const severe = computed(() => ['blocked', 'expired'].includes(subscription.value?.state))
</script>

<template>
    <div
        v-if="subscription"
        role="alert"
        class="flex items-start gap-2 border-b px-4 py-2 text-sm"
        :class="severe
            ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
            : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200'"
    >
        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
        <div>{{ text }}</div>
    </div>
</template>
