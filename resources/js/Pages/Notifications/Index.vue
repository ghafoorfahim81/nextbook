<script setup>
import axios from 'axios'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Check, CheckCheck } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Layout.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    notifications: { type: Object, required: true },
    filters: { type: Object, required: true },
})

const { t } = useI18n()

const items = computed(() => props.notifications?.data || [])
const links = computed(() => props.notifications?.meta?.links || props.notifications?.links || [])
const activeFilter = computed(() => props.filters?.filter || 'all')

function applyFilter(filter) {
    router.get('/notifications', {
        filter,
        perPage: props.filters?.perPage || 15,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

async function markAsRead(notificationId) {
    await axios.post(`/notifications/${notificationId}/read`)
    router.reload({ only: ['notifications', 'notification_center'] })
}

async function markAllAsRead() {
    await axios.post('/notifications/read-all')
    router.reload({ only: ['notifications', 'notification_center'] })
}
</script>

<template>
    <AppLayout>
        <Head :title="t('notifications.title')" />

        <div class="space-y-5">
            <section class="flex flex-col gap-4 rounded-3xl border bg-gradient-to-br from-card via-card to-muted/30 p-6 shadow-sm md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ t('notifications.title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('notifications.subtitle') }}</p>
                </div>

                <Button variant="outline" class="w-full md:w-auto" @click="markAllAsRead">
                    <CheckCheck class="mr-2 size-4" />
                    {{ t('notifications.mark_all_as_read') }}
                </Button>
            </section>

            <section class="flex flex-wrap gap-2">
                <Button :variant="activeFilter === 'all' ? 'default' : 'outline'" @click="applyFilter('all')">
                    {{ t('notifications.all') }}
                </Button>
                <Button :variant="activeFilter === 'unread' ? 'default' : 'outline'" @click="applyFilter('unread')">
                    {{ t('notifications.unread') }}
                </Button>
                <Button :variant="activeFilter === 'read' ? 'default' : 'outline'" @click="applyFilter('read')">
                    {{ t('notifications.read') }}
                </Button>
            </section>

            <div v-if="items.length" class="space-y-4">
                <Card v-for="notification in items" :key="notification.id" class="rounded-2xl border shadow-sm">
                    <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <CardTitle class="text-base">{{ notification.title }}</CardTitle>
                                <Badge :variant="notification.is_read ? 'outline' : 'default'">
                                    {{ notification.is_read ? t('notifications.read') : t('notifications.unread') }}
                                </Badge>
                            </div>
                            <CardDescription>{{ notification.created_at_human }}</CardDescription>
                        </div>

                        <Button
                            v-if="!notification.is_read"
                            variant="ghost"
                            size="sm"
                            class="w-full sm:w-auto"
                            @click="markAsRead(notification.id)"
                        >
                            <Check class="mr-2 size-4" />
                            {{ t('notifications.mark_as_read') }}
                        </Button>
                    </CardHeader>

                    <CardContent>
                        <p class="text-sm text-muted-foreground">{{ notification.message }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card v-else class="rounded-2xl border shadow-sm">
                <CardContent class="py-10 text-center text-sm text-muted-foreground">
                    {{ t('notifications.empty') }}
                </CardContent>
            </Card>

            <section v-if="links.length" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in links"
                    :key="`${link.label}-${link.url}`"
                    :href="link.url || '#'"
                    class="rounded-md border px-3 py-2 text-sm transition"
                    :class="[
                        link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background hover:bg-accent',
                        !link.url ? 'pointer-events-none opacity-50' : '',
                    ]"
                    v-html="link.label"
                />
            </section>
        </div>
    </AppLayout>
</template>
