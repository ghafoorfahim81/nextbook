<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Bell, Check, CheckCheck } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { useI18n } from 'vue-i18n'

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'

const page = usePage()
const { t } = useI18n()

const isOpen = ref(false)
const notificationCenter = ref(page.props.notification_center || { unread_count: 0, items: [] })
let pollTimer = null

const unreadCount = computed(() => Number(notificationCenter.value?.unread_count || 0))
const items = computed(() => notificationCenter.value?.items || [])

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
})

async function refreshFeed() {
    try {
        const { data } = await axios.get('/api/notifications/feed')
        notificationCenter.value = data
    } catch (error) {
        console.error('Failed to refresh notifications', error)
    }
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
    refreshFeed()
    pollTimer = window.setInterval(refreshFeed, 60000)
})

onBeforeUnmount(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer)
    }
})
</script>

<template>
    <DropdownMenu v-model:open="isOpen">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative rounded-full">
                <Bell class="size-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[11px] font-semibold text-white"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
                <span class="sr-only">{{ t('notifications.title') }}</span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-[360px] rounded-xl p-0">
            <div class="border-b px-4 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold">{{ t('notifications.latest') }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ unreadCount }} {{ t('notifications.unread_count') }}
                        </div>
                    </div>
                    <Button
                        v-if="unreadCount > 0"
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-xs"
                        @click="markAllAsRead"
                    >
                        <CheckCheck class="mr-1 size-4" />
                        {{ t('notifications.mark_all_as_read') }}
                    </Button>
                </div>
            </div>

            <div v-if="items.length" class="max-h-96 overflow-y-auto">
                <div
                    v-for="notification in items"
                    :key="notification.id"
                    class="border-b px-4 py-3 last:border-b-0"
                    :class="notification.is_read ? 'bg-background' : 'bg-muted/40'"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">{{ notification.title }}</div>
                            <div class="mt-1 text-sm text-muted-foreground">{{ notification.message }}</div>
                            <div class="mt-2 text-xs text-muted-foreground">{{ notification.created_at_human }}</div>
                        </div>

                        <button
                            v-if="!notification.is_read"
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition hover:bg-accent hover:text-foreground"
                            @click="markAsRead(notification.id)"
                        >
                            <Check class="size-4" />
                            <span class="sr-only">{{ t('notifications.mark_as_read') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="px-4 py-8 text-center text-sm text-muted-foreground">
                {{ t('notifications.empty') }}
            </div>

            <div class="border-t px-3 py-2">
                <Link
                    href="/notifications"
                    class="block rounded-md px-3 py-2 text-center text-sm font-medium text-primary transition hover:bg-accent"
                >
                    {{ t('notifications.view_all') }}
                </Link>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
