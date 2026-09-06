<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/Layout.vue'
import { Button } from '@/Components/ui/button'
import {
    ArrowUp,
    Info,
    Lightbulb,
    TriangleAlert,
    List,
    X,
} from 'lucide-vue-next'
import { getManual, MANUAL_LOCALES } from './content'
import TocPanel from './TocPanel.vue'

const { t, locale } = useI18n()

const STORAGE_KEY = 'nextbook.user-manual.locale'
const query = ref('')
const activeId = ref('cover')
const tocOpen = ref(false)
const articleRef = ref(null)
let sectionObserver = null
let activateLockUntil = 0

function readStoredLocale() {
    try {
        const stored = sessionStorage.getItem(STORAGE_KEY)
        if (stored && MANUAL_LOCALES.some((item) => item.id === stored)) {
            return stored
        }
    } catch {
        // ignore
    }
    return MANUAL_LOCALES.some((item) => item.id === locale.value) ? locale.value : 'en'
}

const manualLocale = ref(readStoredLocale())

const currentLocaleMeta = computed(
    () => MANUAL_LOCALES.find((item) => item.id === manualLocale.value) || MANUAL_LOCALES[0],
)
const isRtl = computed(() => currentLocaleMeta.value.dir === 'rtl')
const manual = computed(() => getManual(manualLocale.value))

const sectionIds = computed(() => ['cover', ...manual.value.chapters.map((chapter) => chapter.id)])

const filteredChapters = computed(() => {
    const needle = query.value.trim().toLowerCase()
    if (!needle) {
        return manual.value.chapters
    }

    return manual.value.chapters.filter((chapter) => {
        const haystack = [
            chapter.title,
            chapter.number,
            ...chapter.blocks.flatMap((block) => {
                if (block.text) return [block.text]
                if (block.label) return [block.label]
                if (block.items) return block.items
                if (block.steps) return block.steps
                if (block.headers) return block.headers
                if (block.rows) return block.rows.flat()
                return []
            }),
        ]
            .join(' ')
            .toLowerCase()

        return haystack.includes(needle)
    })
})

function setManualLocale(id) {
    manualLocale.value = id
    try {
        sessionStorage.setItem(STORAGE_KEY, id)
    } catch {
        // ignore
    }
}

function scrollToId(id) {
    tocOpen.value = false
    activeId.value = id
    activateLockUntil = Date.now() + 700
    const el = document.getElementById(id)
    if (!el) return
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    if (id !== 'cover') {
        history.replaceState(null, '', `#${id}`)
    } else {
        history.replaceState(null, '', window.location.pathname + window.location.search)
    }
}

function updateActiveFromScroll() {
    if (Date.now() < activateLockUntil) {
        return
    }

    const root = articleRef.value
    if (!root) return

    const marker = root.getBoundingClientRect().top + 48
    let current = sectionIds.value[0] || 'cover'

    for (const id of sectionIds.value) {
        const node = document.getElementById(id)
        if (node && node.getBoundingClientRect().top <= marker) {
            current = id
        }
    }

    activeId.value = current
}

function observeSections() {
    sectionObserver?.disconnect()
    const root = articleRef.value
    if (!root) return

    sectionObserver = new IntersectionObserver(updateActiveFromScroll, {
        root,
        threshold: [0, 0.15, 0.35, 0.6, 1],
    })

    for (const id of sectionIds.value) {
        const node = document.getElementById(id)
        if (node) {
            sectionObserver.observe(node)
        }
    }

    updateActiveFromScroll()
}

watch(activeId, async (id) => {
    await nextTick()
    document.querySelector(`[data-toc-id="${id}"]`)?.scrollIntoView({ block: 'nearest' })
})

watch(manualLocale, async () => {
    query.value = ''
    await nextTick()
    observeSections()
})

watch(query, async () => {
    await nextTick()
    observeSections()
})

onMounted(async () => {
    articleRef.value?.addEventListener('scroll', updateActiveFromScroll, { passive: true })
    await nextTick()
    observeSections()
    const hash = window.location.hash.replace('#', '')
    if (hash && document.getElementById(hash)) {
        scrollToId(hash)
    }
})

onBeforeUnmount(() => {
    sectionObserver?.disconnect()
    articleRef.value?.removeEventListener('scroll', updateActiveFromScroll)
})
</script>

<template>
    <AppLayout :title="t('user_manual.page_title')" flush>
        <div
            class="user-manual flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-background"
            :dir="currentLocaleMeta.dir"
        >
            <header class="flex shrink-0 items-center justify-between gap-3 border-b border-border bg-background px-4 py-2.5">
                <p class="text-sm font-semibold text-foreground">{{ t('user_manual.toc') }}</p>
                <Button variant="outline" size="sm" class="gap-2 lg:hidden" @click="tocOpen = true">
                    <List class="size-4" />
                    {{ t('user_manual.chapters') }}
                </Button>
            </header>

            <div class="flex min-h-0 flex-1 overflow-hidden">
                <aside class="hidden h-full w-80 shrink-0 overflow-y-auto border-e border-border bg-muted/30 p-4 lg:block">
                    <TocPanel
                        :t="t"
                        :manual="manual"
                        :manual-locale="manualLocale"
                        :query="query"
                        :active-id="activeId"
                        :filtered-chapters="filteredChapters"
                        @update:query="query = $event"
                        @set-locale="setManualLocale"
                        @go="scrollToId"
                    />
                </aside>

                <article
                    ref="articleRef"
                    class="min-h-0 min-w-0 flex-1 space-y-6 overflow-y-auto overflow-x-hidden bg-background px-4 py-6 sm:px-8 lg:px-10"
                >
                    <section
                        id="cover"
                        class="relative overflow-hidden scroll-mt-6 rounded-3xl border border-primary/30 bg-gradient-to-br from-violet-700 via-violet-600 to-amber-500 p-8 text-white shadow-lg sm:p-12"
                    >
                        <div class="pointer-events-none absolute -end-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                        <div class="pointer-events-none absolute -bottom-20 -start-10 h-56 w-56 rounded-full bg-amber-300/20 blur-3xl" />
                        <div class="relative space-y-5 text-center">
                            <span class="inline-flex rounded-full bg-white/15 px-4 py-1.5 text-sm font-medium backdrop-blur">
                                {{ manual.badge }}
                            </span>
                            <h1 class="text-4xl font-bold leading-tight sm:text-5xl">{{ manual.title }}</h1>
                            <p class="text-lg text-violet-50 sm:text-xl">{{ manual.subtitle }}</p>
                            <div class="mx-auto h-1 w-24 rounded-full bg-amber-300" />
                            <p class="text-sm text-violet-100">{{ manual.version }}</p>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-sky-500/40 border-s-4 border-s-sky-500 bg-sky-500/15 px-5 py-4 text-base leading-8 text-foreground">
                        <p class="mb-1 font-semibold text-foreground">{{ t('user_manual.how_to_read') }}</p>
                        <p class="text-foreground">{{ manual.howToRead }}</p>
                    </section>

                    <p v-if="query && !filteredChapters.length" class="rounded-xl border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                        {{ t('user_manual.no_results') }}
                    </p>

                    <section
                        v-for="chapter in filteredChapters"
                        :id="chapter.id"
                        :key="chapter.id"
                        class="scroll-mt-6 overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                    >
                        <header class="border-b border-border bg-muted/50 px-6 py-4">
                            <p class="text-xs font-bold tracking-wide text-primary">{{ chapter.number }}</p>
                            <h2 class="text-2xl font-bold text-card-foreground">{{ chapter.title }}</h2>
                        </header>

                        <div class="space-y-5 px-6 py-6 text-base leading-8 text-card-foreground">
                            <template v-for="(block, index) in chapter.blocks" :key="index">
                                <h3
                                    v-if="block.type === 'h3'"
                                    class="pt-2 text-lg font-semibold text-primary"
                                >
                                    {{ block.text }}
                                </h3>

                                <p v-else-if="block.type === 'p'" class="text-card-foreground">
                                    {{ block.text }}
                                </p>

                                <ul v-else-if="block.type === 'list'" class="list-disc space-y-1.5 text-card-foreground marker:text-primary ltr:pl-5 rtl:pr-5">
                                    <li v-for="item in block.items" :key="item">{{ item }}</li>
                                </ul>

                                <ol v-else-if="block.type === 'ol'" class="list-decimal space-y-1.5 text-card-foreground marker:font-semibold marker:text-primary ltr:pl-5 rtl:pr-5">
                                    <li v-for="item in block.items" :key="item">{{ item }}</li>
                                </ol>

                                <div
                                    v-else-if="block.type === 'note'"
                                    class="rounded-xl border border-sky-500/40 border-s-4 border-s-sky-500 bg-sky-500/15 px-4 py-3 text-base leading-8 text-foreground"
                                >
                                    <p class="mb-1 inline-flex items-center gap-1.5 font-semibold text-foreground">
                                        <Info class="size-3.5 shrink-0 text-sky-500" />
                                        {{ block.label }}
                                    </p>
                                    <p class="text-foreground">{{ block.text }}</p>
                                </div>

                                <div
                                    v-else-if="block.type === 'tip'"
                                    class="rounded-xl border border-emerald-500/40 border-s-4 border-s-emerald-500 bg-emerald-500/15 px-4 py-3 text-base leading-8 text-foreground"
                                >
                                    <p class="mb-1 inline-flex items-center gap-1.5 font-semibold text-foreground">
                                        <Lightbulb class="size-3.5 shrink-0 text-emerald-500" />
                                        {{ block.label }}
                                    </p>
                                    <p class="text-foreground">{{ block.text }}</p>
                                </div>

                                <div
                                    v-else-if="block.type === 'warn'"
                                    class="rounded-xl border border-red-500/40 border-s-4 border-s-red-500 bg-red-500/15 px-4 py-3 text-base leading-8 text-foreground"
                                >
                                    <p class="mb-1 inline-flex items-center gap-1.5 font-semibold text-foreground">
                                        <TriangleAlert class="size-3.5 shrink-0 text-red-500" />
                                        {{ block.label }}
                                    </p>
                                    <p class="text-foreground">{{ block.text }}</p>
                                </div>

                                <div
                                    v-else-if="block.type === 'flow'"
                                    class="flex flex-wrap items-stretch gap-2"
                                >
                                    <template v-for="(step, stepIndex) in block.steps" :key="step">
                                        <div class="min-w-[8rem] flex-1 rounded-xl border border-primary/30 bg-primary/10 px-3 py-3 text-center text-sm font-medium text-foreground">
                                            {{ step }}
                                        </div>
                                        <div
                                            v-if="stepIndex < block.steps.length - 1"
                                            class="hidden items-center text-lg text-violet-400 sm:flex"
                                            aria-hidden="true"
                                        >
                                            {{ isRtl ? '←' : '→' }}
                                        </div>
                                    </template>
                                </div>

                                <div v-else-if="block.type === 'table'" class="overflow-x-auto rounded-xl border border-border">
                                    <table class="w-full min-w-[28rem] border-collapse text-sm">
                                        <thead>
                                            <tr class="bg-muted text-foreground">
                                                <th
                                                    v-for="header in block.headers"
                                                    :key="header"
                                                    class="border-b border-border px-3 py-2 font-semibold ltr:text-left rtl:text-right"
                                                >
                                                    {{ header }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(row, rowIndex) in block.rows"
                                                :key="rowIndex"
                                                class="odd:bg-card even:bg-muted/40"
                                            >
                                                <td
                                                    v-for="(cell, cellIndex) in row"
                                                    :key="cellIndex"
                                                    class="border-t border-border px-3 py-2 align-top text-card-foreground"
                                                    :class="cellIndex === 0 ? 'font-semibold text-foreground' : ''"
                                                >
                                                    {{ cell }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </div>
                    </section>

                    <div class="flex justify-center">
                        <Button variant="outline" class="gap-2" @click="scrollToId('cover')">
                            <ArrowUp class="size-4" />
                            {{ t('user_manual.back_to_top') }}
                        </Button>
                    </div>
                </article>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="tocOpen"
                class="fixed inset-0 z-[80] lg:hidden"
                :dir="currentLocaleMeta.dir"
            >
                <button
                    type="button"
                    class="absolute inset-0 bg-black/50"
                    :aria-label="t('user_manual.toc')"
                    @click="tocOpen = false"
                />
                <div class="absolute inset-y-0 start-0 flex w-80 max-w-[85vw] flex-col border-e border-violet-200 bg-background shadow-xl dark:border-violet-900">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <p class="font-semibold">{{ t('user_manual.toc') }}</p>
                        <Button variant="ghost" size="icon" @click="tocOpen = false">
                            <X class="size-4" />
                        </Button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4">
                        <TocPanel
                            :t="t"
                            :manual="manual"
                            :manual-locale="manualLocale"
                            :query="query"
                            :active-id="activeId"
                            :filtered-chapters="filteredChapters"
                            @update:query="query = $event"
                            @set-locale="setManualLocale"
                            @go="scrollToId"
                        />
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
