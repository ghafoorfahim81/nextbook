<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Bell, Search } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { useNotificationPresentation } from '@/composables/useNotificationPresentation'
import { useI18n } from 'vue-i18n'

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'

const page = usePage()
const { t, locale } = useI18n()
const { present, relativeTime } = useNotificationPresentation()

const POLL_INTERVAL = 60000
const MAX_POLL_INTERVAL = 15 * 60 * 1000

const isOpen = ref(false)
const searchQuery = ref('')
const activeFilter = ref('all')
const notificationCenter = ref(page.props.notification_center || { unread_count: 0, items: [] })
let pollTimer = null
let inFlight = false
let currentInterval = POLL_INTERVAL

const isRtl = computed(() => page.props.direction === 'rtl' || ['fa', 'ps'].includes(locale.value))
const unreadCount = computed(() => Number(notificationCenter.value?.unread_count || 0))
const items = computed(() => notificationCenter.value?.items || [])
const unreadItemsCount = computed(() => items.value.filter(notification => !notification.is_read).length)

const filteredItems = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase()

    return items.value.filter((notification) => {
        if (activeFilter.value === 'unread' && notification.is_read) return false

        if (!query) return true

        const display = present(notification)
        return `${display.title} ${display.message}`.toLocaleLowerCase().includes(query)
    })
})

const groupedItems = computed(() => {
    const now = new Date()
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
    const yesterday = today - 86_400_000
    const groups = new Map()

    filteredItems.value.forEach((notification) => {
        const date = new Date(notification.created_at)
        const day = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()
        const label = day === today
            ? t('notifications.today')
            : day === yesterday
                ? t('notifications.yesterday')
                : new Intl.DateTimeFormat(locale.value, { dateStyle: 'long' }).format(date)

        if (!groups.has(label)) groups.set(label, [])
        groups.get(label).push(notification)
    })

    return Array.from(groups, ([label, notifications]) => ({ label, notifications }))
})

watch(
    () => page.props.notification_center,
    (value) => {
        if (value) {
            notificationCenter.value = value
        }
    },
    { deep: true },
)

watch(isOpen, (open) => {
    if (open) refreshFeed()
    else searchQuery.value = ''
})

async function refreshFeed() {
    // Overlapping polls pile up when the server is slow; one at a time is enough.
    if (inFlight) return

    inFlight = true

    try {
        const { data } = await axios.get('/api/notifications/feed')
        notificationCenter.value = data
        resetBackoff()
    } catch (error) {
        // A 401/419 means the session is gone. Polling on regardless just
        // hammered the endpoint forever with the badge silently frozen, so back
        // off instead and let the next Inertia visit re-authenticate.
        const status = error?.response?.status

        if (status === 401 || status === 419 || status === 403) {
            stopPolling()
            return
        }

        currentInterval = Math.min(currentInterval * 2, MAX_POLL_INTERVAL)
        schedulePoll()
    } finally {
        inFlight = false
    }
}

function resetBackoff() {
    if (currentInterval !== POLL_INTERVAL) {
        currentInterval = POLL_INTERVAL
        schedulePoll()
    }
}

function schedulePoll() {
    stopPolling()

    // Background tabs do not need a live badge, and every poll is a full
    // session-authenticated request. Resume on focus instead.
    if (document.hidden) return

    pollTimer = window.setInterval(refreshFeed, currentInterval)
}

function stopPolling() {
    if (pollTimer) {
        window.clearInterval(pollTimer)
        pollTimer = null
    }
}

function handleVisibilityChange() {
    if (document.hidden) {
        stopPolling()
        return
    }

    refreshFeed()
    schedulePoll()
}

async function markAsRead(notificationId) {
    try {
        const { data } = await axios.post(`/api/notifications/${notificationId}/read`)
        notificationCenter.value = data.notification_center
    } catch (error) {
        console.error('Failed to mark notification as read', error)
    }
}

async function markAllAsRead() {
    try {
        const { data } = await axios.post('/api/notifications/read-all')
        notificationCenter.value = data
    } catch (error) {
        console.error('Failed to mark notifications as read', error)
    }
}

onMounted(() => {
    // No fetch on mount: `notification_center` already arrived as a shared
    // Inertia prop with this page load, so an immediate poll only duplicated it.
    schedulePoll()
    document.addEventListener('visibilitychange', handleVisibilityChange)
})

onBeforeUnmount(() => {
    stopPolling()
    document.removeEventListener('visibilitychange', handleVisibilityChange)
})
</script>

<template>
    <DropdownMenu v-model:open="isOpen">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative rounded-full">
                <Bell class="size-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[11px] font-semibold leading-none text-white"
                    :class="isRtl ? '-left-1' : '-right-1'"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
                <span class="sr-only">{{ t('notifications.title') }}</span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            :dir="isRtl ? 'rtl' : 'ltr'"
            class="w-[calc(100vw-2rem)] max-w-[400px] overflow-hidden rounded-2xl p-0 shadow-xl sm:w-[400px]"
        >
          <div :dir="isRtl ? 'rtl' : 'ltr'">
            <!-- Header -->
            <div class="px-5 pb-3 pt-4">
                <div class="text-center text-base font-semibold">{{ t('notifications.title') }}</div>
            </div>

            <!-- Search -->
            <div class="px-4 pb-3">
                <label class="relative block">
                    <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        :placeholder="t('notifications.search')"
                        class="h-10 w-full rounded-full border-0 bg-muted/70 ps-10 pe-4 text-sm outline-none ring-offset-background transition placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    >
                </label>
            </div>

            <!-- Filter pills -->
            <div class="flex items-center justify-between gap-2 px-4 pb-3">
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="inline-flex h-7 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition"
                        :class="activeFilter === 'all'
                            ? 'border-transparent bg-primary/10 text-primary'
                            : 'border-border text-muted-foreground hover:bg-muted'"
                        @click="activeFilter = 'all'"
                    >
                        {{ t('notifications.all') }}
                        <span
                            class="inline-flex min-w-5 items-center justify-center rounded-full px-1 py-0.5 text-[10px] font-semibold"
                            :class="activeFilter === 'all' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                        >{{ items.length }}</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-7 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition"
                        :class="activeFilter === 'unread'
                            ? 'border-transparent bg-primary/10 text-primary'
                            : 'border-border text-muted-foreground hover:bg-muted'"
                        @click="activeFilter = 'unread'"
                    >
                        {{ t('notifications.unread') }}
                        <span
                            class="inline-flex min-w-5 items-center justify-center rounded-full px-1 py-0.5 text-[10px] font-semibold"
                            :class="activeFilter === 'unread' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                        >{{ unreadItemsCount }}</span>
                    </button>
                </div>
                <button
                    v-if="unreadItemsCount"
                    type="button"
                    class="shrink-0 text-xs font-medium text-primary hover:underline"
                    @click="markAllAsRead"
                >
                    {{ t('notifications.mark_all_as_read') }}
                </button>
            </div>

            <!-- List -->
            <div v-if="groupedItems.length" class="max-h-[26rem] overflow-y-auto border-t">
                <section v-for="group in groupedItems" :key="group.label">
                    <h3 class="bg-muted/40 px-5 py-2 text-xs font-semibold text-foreground">{{ group.label }}</h3>
                    <button
                        v-for="notification in group.notifications"
                        :key="notification.id"
                        type="button"
                        class="flex w-full items-start gap-3 border-b px-5 py-3.5 text-start transition last:border-b-0 hover:bg-muted/50"
                        :class="notification.is_read ? 'opacity-55' : ''"
                        @click="!notification.is_read && markAsRead(notification.id)"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full"
                            :class="present(notification).class"
                        >
                            <component :is="present(notification).icon" class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">{{ present(notification).title }}</div>
                            <p class="mt-0.5 truncate text-xs text-muted-foreground">{{ present(notification).message }}</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1.5">
                            <span
                                v-if="!notification.is_read"
                                class="size-2 rounded-full bg-primary"
                            />
                            <span v-else class="size-2" />
                            <span class="whitespace-nowrap text-[11px] text-muted-foreground">
                                {{ relativeTime(notification.created_at) }}
                            </span>
                        </div>
                    </button>
                </section>
            </div>

            <!-- Empty state -->
            <div v-else class="flex flex-col items-center justify-center border-t px-6 py-12 text-center">
                <svg
                    class="mb-4 h-28 w-28 text-primary"
                    viewBox="0 0 120 120"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                >
                    <path d="M18 74l42-14 42 14-42 16z" fill="currentColor" fill-opacity="0.12" />
                    <path d="M60 60L20 73v18l40 13V60z" fill="currentColor" fill-opacity="0.18" />
                    <path d="M60 60l40 13v18L60 104V60z" fill="currentColor" fill-opacity="0.28" />
                    <path d="M60 60L20 73M60 60l40 13M60 60v44" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                    <circle cx="60" cy="34" r="8" fill="currentColor" />
                    <path d="M52 58c0-8 4-13 8-13s8 5 8 13" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                    <path d="M60 12v-6M44 20l-4-5M76 20l4-5M40 34h-7M87 34h-7" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <p class="text-sm text-muted-foreground">
                    {{ searchQuery || activeFilter === 'unread' ? t('notifications.empty') : t('notifications.nothing_here') }}
                </p>
            </div>

            <!-- Footer -->
            <div class="border-t px-4 py-2">
                <Link
                    href="/notifications"
                    class="block rounded-xl px-3 py-2.5 text-center text-sm font-medium text-primary transition hover:bg-accent"
                >
                    {{ t('notifications.view_all') }}
                </Link>
            </div>
          </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
