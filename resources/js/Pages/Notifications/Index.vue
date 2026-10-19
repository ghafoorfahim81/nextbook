<script setup>
import axios from 'axios'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import {
    Archive,
    ArchiveRestore,
    Bell,
    BellDot,
    CalendarDays,
    CalendarRange,
    Check,
    CheckCheck,
    Inbox,
    Loader2,
    Mail,
    MailOpen,
    Search,
    Star,
    Trash2,
    X,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/Layout.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Checkbox } from '@/Components/ui/checkbox'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import {
    useNotificationPresentation,
    NOTIFICATION_CATEGORIES,
} from '@/composables/useNotificationPresentation'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    notifications: { type: Object, required: true },
    summary: { type: Object, required: true },
    filters: { type: Object, required: true },
})

const { t, locale } = useI18n()
const { present, category, relativeTime } = useNotificationPresentation()

const isRtl = computed(() => ['fa', 'ps'].includes(locale.value))
const rows = computed(() => props.notifications?.data || [])
const meta = computed(() => props.notifications?.meta || {})
const paginationLinks = computed(() => (Array.isArray(meta.value?.links) ? meta.value.links : []))

const state = reactive({
    tab: props.filters?.tab || 'all',
    category: props.filters?.category || null,
    search: props.filters?.search || '',
})

const tabCounts = computed(() => props.summary.tabs || {})
const tabs = computed(() => [
    { key: 'all', label: t('notifications.all'), count: tabCounts.value.all ?? 0 },
    { key: 'unread', label: t('notifications.unread'), count: tabCounts.value.unread ?? 0 },
    { key: 'read', label: t('notifications.read'), count: tabCounts.value.read ?? 0 },
    { key: 'favorites', label: t('notifications.favorites'), count: tabCounts.value.favorites ?? 0 },
    { key: 'archived', label: t('notifications.archived'), count: tabCounts.value.archived ?? 0 },
])

const stats = computed(() => [
    { key: 'total', label: t('notifications.stats.total'), value: props.summary.total, icon: Bell, class: 'bg-primary/10 text-primary' },
    { key: 'unread', label: t('notifications.stats.unread'), value: props.summary.unread, icon: BellDot, class: 'bg-rose-100 text-rose-600 dark:bg-rose-950/50 dark:text-rose-300' },
    { key: 'today', label: t('notifications.stats.today'), value: props.summary.today, icon: CalendarDays, class: 'bg-sky-100 text-sky-600 dark:bg-sky-950/50 dark:text-sky-300' },
    { key: 'week', label: t('notifications.stats.week'), value: props.summary.week, icon: CalendarRange, class: 'bg-violet-100 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300' },
])

const modules = computed(() => NOTIFICATION_CATEGORIES.map((key) => {
    const cat = category({ category: key })
    const counts = props.summary.categories?.[key] || { total: 0, unread: 0 }

    return { key, label: cat.label, icon: cat.icon, class: cat.class, ...counts }
}))

const isArchiveTab = computed(() => state.tab === 'archived')

// --- Selection -------------------------------------------------------------
const selected = ref([])
const pageIds = computed(() => rows.value.map(row => row.id))
const allOnPageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every(id => selected.value.includes(id)))

function isSelected(id) {
    return selected.value.includes(id)
}

function toggleRow(id) {
    selected.value = isSelected(id)
        ? selected.value.filter(current => current !== id)
        : [...selected.value, id]
}

function toggleAll() {
    selected.value = allOnPageSelected.value ? [] : [...pageIds.value]
}

function clearSelection() {
    selected.value = []
}

// Any server round-trip invalidates the current selection.
watch(() => props.notifications, clearSelection)

// --- Navigation ----------------------------------------------------------
// Every server round-trip on this page is a background partial reload
// (`only`). The `X-DataTable-Refresh` header tells PageLoadingOverlay to skip
// the full-screen BookLoader for these in-place refreshes; `busy` drives a
// light spinner over the table instead.
const busy = ref(false)
const PARTIAL = ['notifications', 'summary', 'notification_center']
const IN_PLACE = { headers: { 'X-DataTable-Refresh': '1' } }

function partialReload(extra = {}) {
    return new Promise((resolve) => {
        router.reload({
            ...IN_PLACE,
            only: PARTIAL,
            preserveScroll: true,
            preserveState: true,
            onStart: () => { busy.value = true },
            onFinish: () => { busy.value = false; resolve() },
            ...extra,
        })
    })
}

function applyFilters(page = 1) {
    router.get(route('notifications.index'), {
        page,
        tab: state.tab !== 'all' ? state.tab : undefined,
        category: state.category || undefined,
        search: state.search || undefined,
        perPage: props.filters?.perPage || undefined,
    }, {
        ...IN_PLACE,
        only: PARTIAL,
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { busy.value = true },
        onFinish: () => { busy.value = false },
    })
}

function setTab(tab) {
    state.tab = tab
    applyFilters(1)
}

function setCategory(key) {
    state.category = state.category === key ? null : key
    applyFilters(1)
}

function visitPage(url) {
    if (!url) return
    router.visit(url, {
        ...IN_PLACE,
        only: PARTIAL,
        preserveState: true,
        preserveScroll: true,
        onStart: () => { busy.value = true },
        onFinish: () => { busy.value = false },
    })
}

let searchTimer = null
watch(() => state.search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => applyFilters(1), 350)
})

// --- Mutations ---------------------------------------------------------
async function runAction(notification, action) {
    const endpoints = {
        read: ['post', route('notifications.read', notification.id)],
        unread: ['post', route('notifications.unread', notification.id)],
        favorite: ['post', route('notifications.favorite', notification.id)],
        archive: ['post', route('notifications.archive', notification.id)],
        unarchive: ['post', route('notifications.unarchive', notification.id)],
        delete: ['delete', route('notifications.destroy', notification.id)],
    }

    if (action === 'delete' && !window.confirm(t('notifications.confirm_delete'))) return

    const [method, url] = endpoints[action]
    busy.value = true
    try {
        await axios[method](url)
        await partialReload()
    } finally {
        busy.value = false
    }
}

async function bulk(action) {
    if (!selected.value.length) return

    if (action === 'delete'
        && !window.confirm(t('notifications.confirm_delete_selected', { count: selected.value.length }))) {
        return
    }

    busy.value = true
    try {
        await axios.post(route('notifications.bulk'), { action, ids: selected.value })
        clearSelection()
        await partialReload()
    } finally {
        busy.value = false
    }
}

async function markAllAsRead() {
    busy.value = true
    try {
        await axios.post(route('notifications.read-all'))
        await partialReload()
    } finally {
        busy.value = false
    }
}

function absoluteTime(value) {
    return value ? new Date(value).toLocaleString(locale.value) : ''
}
</script>

<template>
    <AppLayout>
        <Head :title="t('notifications.title')" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">{{ t('notifications.title') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ t('notifications.subtitle') }}</p>
                </div>
                <Button v-if="summary.unread" variant="outline" size="sm" @click="markAllAsRead">
                    <CheckCheck class="me-2 size-4" />
                    {{ t('notifications.mark_all_as_read') }}
                </Button>
            </div>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Card v-for="stat in stats" :key="stat.key">
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl" :class="stat.class">
                            <component :is="stat.icon" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-2xl font-semibold leading-none">{{ stat.value }}</div>
                            <div class="mt-1 truncate text-xs text-muted-foreground">{{ stat.label }}</div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-[220px_1fr]">
                <!-- Module rail -->
                <Card class="h-fit lg:sticky lg:top-4">
                    <CardContent class="p-2">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm transition"
                            :class="!state.category ? 'bg-accent font-medium text-accent-foreground' : 'text-muted-foreground hover:bg-muted'"
                            @click="setCategory(null)"
                        >
                            <Inbox class="size-4 shrink-0" />
                            <span class="flex-1 text-start">{{ t('notifications.all_modules') }}</span>
                            <span class="text-xs tabular-nums">{{ summary.total }}</span>
                        </button>

                        <div class="my-1 h-px bg-border" />

                        <button
                            v-for="module in modules"
                            :key="module.key"
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm transition"
                            :class="state.category === module.key ? 'bg-accent font-medium text-accent-foreground' : 'text-muted-foreground hover:bg-muted'"
                            @click="setCategory(module.key)"
                        >
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-md" :class="module.class">
                                <component :is="module.icon" class="size-3.5" />
                            </span>
                            <span class="flex-1 truncate text-start">{{ module.label }}</span>
                            <span
                                v-if="module.unread"
                                class="inline-flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-4 text-primary-foreground"
                            >{{ module.unread }}</span>
                            <span v-else class="text-xs tabular-nums text-muted-foreground">{{ module.total }}</span>
                        </button>
                    </CardContent>
                </Card>

                <!-- Table panel -->
                <Card class="min-w-0">
                    <CardContent class="space-y-4 p-4">
                        <!-- Tabs + search -->
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex flex-wrap gap-1 rounded-lg bg-muted p-1">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.key"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition"
                                    :class="state.tab === tab.key ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                    @click="setTab(tab.key)"
                                >
                                    {{ tab.label }}
                                    <span
                                        class="inline-flex min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-4"
                                        :class="state.tab === tab.key ? 'bg-primary/10 text-primary' : 'bg-background/60 text-muted-foreground'"
                                    >{{ tab.count }}</span>
                                </button>
                            </div>

                            <label class="relative block lg:w-64">
                                <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <input
                                    v-model="state.search"
                                    type="search"
                                    :placeholder="t('notifications.search')"
                                    class="h-9 w-full rounded-full border bg-background ps-9 pe-3 text-sm outline-none ring-offset-background transition placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                                >
                            </label>
                        </div>

                        <!-- Bulk toolbar -->
                        <div
                            v-if="selected.length"
                            class="flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2"
                        >
                            <span class="text-sm font-medium">
                                {{ t('notifications.selected_count', { count: selected.length }) }}
                            </span>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <Button variant="outline" size="sm" class="h-7" @click="bulk('read')">
                                    <MailOpen class="me-1.5 size-3.5" />
                                    {{ t('notifications.mark_selected_read') }}
                                </Button>
                                <Button v-if="!isArchiveTab" variant="outline" size="sm" class="h-7" @click="bulk('archive')">
                                    <Archive class="me-1.5 size-3.5" />
                                    {{ t('notifications.archive_selected') }}
                                </Button>
                                <Button v-else variant="outline" size="sm" class="h-7" @click="bulk('unarchive')">
                                    <ArchiveRestore class="me-1.5 size-3.5" />
                                    {{ t('notifications.unarchive') }}
                                </Button>
                                <Button variant="outline" size="sm" class="h-7 text-destructive hover:text-destructive" @click="bulk('delete')">
                                    <Trash2 class="me-1.5 size-3.5" />
                                    {{ t('notifications.delete_selected') }}
                                </Button>
                                <Button variant="ghost" size="sm" class="h-7" @click="clearSelection">
                                    <X class="me-1.5 size-3.5" />
                                    {{ t('notifications.clear_selection') }}
                                </Button>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="relative overflow-x-auto rounded-md border">
                            <div
                                v-if="busy"
                                class="pointer-events-none absolute inset-0 z-10 flex items-start justify-center bg-background/40 pt-12"
                            >
                                <Loader2 class="size-5 animate-spin text-muted-foreground" />
                            </div>
                            <Table :class="busy ? 'opacity-60 transition-opacity' : 'transition-opacity'">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-12 !px-3 text-start">
                                            <div class="flex items-center [&_input]:hidden">
                                                <Checkbox
                                                    :checked="allOnPageSelected"
                                                    :disabled="!rows.length"
                                                    @update:checked="toggleAll"
                                                />
                                            </div>
                                        </TableHead>
                                        <TableHead class="text-start">{{ t('notifications.notification') }}</TableHead>
                                        <TableHead class="w-40 text-start">{{ t('notifications.module') }}</TableHead>
                                        <TableHead class="w-32 text-start">{{ t('notifications.received') }}</TableHead>
                                        <TableHead class="w-24 text-start">{{ t('notifications.status') }}</TableHead>
                                        <TableHead class="w-px whitespace-nowrap text-end">{{ t('notifications.actions') }}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="!rows.length">
                                        <TableCell colspan="6" class="py-14 text-center">
                                            <div class="mx-auto flex max-w-xs flex-col items-center gap-3 text-muted-foreground">
                                                <div class="flex size-14 items-center justify-center rounded-full bg-muted">
                                                    <Bell class="size-6" />
                                                </div>
                                                <p class="text-sm">
                                                    {{ state.search || state.category || state.tab !== 'all'
                                                        ? t('notifications.no_results')
                                                        : t('notifications.nothing_here') }}
                                                </p>
                                            </div>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow
                                        v-for="notification in rows"
                                        :key="notification.id"
                                        :data-state="isSelected(notification.id) ? 'selected' : undefined"
                                        :class="notification.is_read ? '' : 'bg-primary/[0.03]'"
                                    >
                                        <TableCell class="!px-3 align-top">
                                            <div class="flex pt-0.5 [&_input]:hidden">
                                                <Checkbox
                                                    :checked="isSelected(notification.id)"
                                                    @update:checked="toggleRow(notification.id)"
                                                />
                                            </div>
                                        </TableCell>

                                        <TableCell>
                                            <div class="flex items-start gap-3">
                                                <span
                                                    class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full"
                                                    :class="present(notification).class"
                                                >
                                                    <component :is="present(notification).icon" class="size-4" />
                                                </span>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5">
                                                        <Star
                                                            v-if="notification.is_favorite"
                                                            class="size-3.5 shrink-0 fill-amber-400 text-amber-400"
                                                        />
                                                        <span
                                                            class="truncate text-sm"
                                                            :class="notification.is_read ? 'font-medium text-foreground/80' : 'font-semibold'"
                                                        >{{ present(notification).title }}</span>
                                                    </div>
                                                    <p class="mt-0.5 line-clamp-2 max-w-md text-xs text-muted-foreground">
                                                        {{ present(notification).message }}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>

                                        <TableCell>
                                            <Badge variant="secondary" class="gap-1.5 font-normal">
                                                <span class="size-1.5 rounded-full" :class="category(notification).dot" />
                                                {{ category(notification).label }}
                                            </Badge>
                                        </TableCell>

                                        <TableCell>
                                            <span class="whitespace-nowrap text-xs text-muted-foreground" :title="absoluteTime(notification.created_at)">
                                                {{ relativeTime(notification.created_at) }}
                                            </span>
                                        </TableCell>

                                        <TableCell>
                                            <span
                                                v-if="!notification.is_read"
                                                class="inline-flex items-center gap-1.5 text-xs font-medium text-primary"
                                            >
                                                <span class="size-2 rounded-full bg-primary" />
                                                {{ t('notifications.unread') }}
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <Check class="size-3.5" />
                                                {{ t('notifications.read') }}
                                            </span>
                                        </TableCell>

                                        <TableCell>
                                            <div class="flex items-center justify-end gap-0.5">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-8"
                                                    :title="notification.is_favorite ? t('notifications.remove_favorite') : t('notifications.add_favorite')"
                                                    @click="runAction(notification, 'favorite')"
                                                >
                                                    <Star class="size-4" :class="notification.is_favorite ? 'fill-amber-400 text-amber-400' : ''" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-8"
                                                    :title="notification.is_read ? t('notifications.mark_as_unread') : t('notifications.mark_as_read')"
                                                    @click="runAction(notification, notification.is_read ? 'unread' : 'read')"
                                                >
                                                    <Mail v-if="notification.is_read" class="size-4" />
                                                    <MailOpen v-else class="size-4" />
                                                </Button>
                                                <Button
                                                    v-if="!notification.is_archived"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-8"
                                                    :title="t('notifications.archive')"
                                                    @click="runAction(notification, 'archive')"
                                                >
                                                    <Archive class="size-4" />
                                                </Button>
                                                <Button
                                                    v-else
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-8"
                                                    :title="t('notifications.unarchive')"
                                                    @click="runAction(notification, 'unarchive')"
                                                >
                                                    <ArchiveRestore class="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-8 text-muted-foreground hover:text-destructive"
                                                    :title="t('notifications.delete')"
                                                    @click="runAction(notification, 'delete')"
                                                >
                                                    <Trash2 class="size-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="rows.length" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm text-muted-foreground">
                                {{ t('datatable.showing', {
                                    from: meta.from || 0,
                                    to: meta.to || 0,
                                    total: meta.total || 0,
                                }) }}
                            </div>
                            <div v-if="paginationLinks.length > 3" class="flex flex-wrap items-center gap-1">
                                <Button
                                    v-for="link in paginationLinks"
                                    :key="`${link.label}-${link.url}`"
                                    :variant="link.active ? 'default' : 'outline'"
                                    size="sm"
                                    class="h-8 min-w-8 px-2"
                                    :disabled="!link.url"
                                    @click="visitPage(link.url)"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
