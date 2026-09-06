<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/Layout.vue'
import { Button } from '@/Components/ui/button'
import {
    ArrowLeft,
    ArrowUp,
    Banknote,
    BarChart3,
    Boxes,
    Building2,
    Calculator,
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Compass,
    ArrowLeftRight,
    Image as ImageIcon,
    Info,
    Lightbulb,
    List,
    Palmtree,
    ReceiptText,
    Sigma,
    ShoppingCart,
    TriangleAlert,
    Truck,
    Users,
    UsersRound,
    UserPlus,
    X,
} from 'lucide-vue-next'
import { getMeta, getGuides, getGuide, MANUAL_LOCALES } from './content'
import TocPanel from './TocPanel.vue'

const { t, locale } = useI18n()

const STORAGE_KEY = 'nextbook.user-manual.locale'

const ICONS = {
    Compass,
    ShoppingCart,
    Truck,
    Boxes,
    Calculator,
    ArrowLeftRight,
    ReceiptText,
    Users,
    UsersRound,
    CalendarClock,
    Palmtree,
    Banknote,
    UserPlus,
    BarChart3,
    Building2,
}

const ACCENTS = {
    violet: 'text-violet-600 bg-violet-500/10 dark:text-violet-300',
    emerald: 'text-emerald-600 bg-emerald-500/10 dark:text-emerald-300',
    sky: 'text-sky-600 bg-sky-500/10 dark:text-sky-300',
    amber: 'text-amber-600 bg-amber-500/10 dark:text-amber-300',
    rose: 'text-rose-600 bg-rose-500/10 dark:text-rose-300',
    teal: 'text-teal-600 bg-teal-500/10 dark:text-teal-300',
    orange: 'text-orange-600 bg-orange-500/10 dark:text-orange-300',
    indigo: 'text-indigo-600 bg-indigo-500/10 dark:text-indigo-300',
    cyan: 'text-cyan-600 bg-cyan-500/10 dark:text-cyan-300',
    slate: 'text-slate-600 bg-slate-500/10 dark:text-slate-300',
}

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
// Figures whose image file 404s fall back to the placeholder box, so every
// `src` can be wired ahead of the screenshot actually being added.
const missingFigures = ref(new Set())
const activeGuideId = ref(null)
const activeChapterId = ref(null)
const query = ref('')
const tocOpen = ref(false)
const articleRef = ref(null)
let sectionObserver = null
let activateLockUntil = 0

const currentLocaleMeta = computed(
    () => MANUAL_LOCALES.find((item) => item.id === manualLocale.value) || MANUAL_LOCALES[0],
)
const isRtl = computed(() => currentLocaleMeta.value.dir === 'rtl')
const backIcon = computed(() => (isRtl.value ? ChevronRight : ChevronLeft))

const meta = computed(() => getMeta(manualLocale.value))
const guides = computed(() => getGuides(manualLocale.value))
const activeGuide = computed(() =>
    activeGuideId.value ? getGuide(manualLocale.value, activeGuideId.value) : null,
)

const guideQuery = computed(() => (activeGuideId.value ? '' : query.value))

const filteredGuides = computed(() => {
    const needle = guideQuery.value.trim().toLowerCase()
    if (!needle) return guides.value
    return guides.value.filter((guide) =>
        [guide.title, guide.subtitle, guide.summary].join(' ').toLowerCase().includes(needle),
    )
})

const filteredChapters = computed(() => {
    const guide = activeGuide.value
    if (!guide) return []
    const needle = query.value.trim().toLowerCase()
    if (!needle) return guide.chapters

    return guide.chapters.filter((chapter) => {
        const haystack = [
            chapter.title,
            chapter.number,
            ...chapter.blocks.flatMap((block) => {
                if (block.text) return [block.text]
                if (block.label) return [block.label]
                if (block.caption) return [block.caption]
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

const chapterIds = computed(() => ['guide-top', ...filteredChapters.value.map((c) => `ch-${c.id}`)])

function iconFor(guide) {
    return ICONS[guide.icon] || Compass
}
function accentFor(guide) {
    return ACCENTS[guide.accent] || ACCENTS.violet
}

function setManualLocale(id) {
    manualLocale.value = id
    try {
        sessionStorage.setItem(STORAGE_KEY, id)
    } catch {
        // ignore
    }
}

function syncHash() {
    if (!activeGuideId.value) {
        history.replaceState(null, '', window.location.pathname + window.location.search)
        return
    }
    const chapterPart =
        activeChapterId.value && activeChapterId.value !== 'guide-top'
            ? `/${activeChapterId.value}`
            : ''
    history.replaceState(null, '', `#${activeGuideId.value}${chapterPart}`)
}

function openGuide(id, chapterId = null) {
    activeGuideId.value = id
    activeChapterId.value = chapterId ? `ch-${chapterId}` : 'guide-top'
    query.value = ''
    tocOpen.value = false
    nextTick(() => {
        const target = chapterId ? document.getElementById(`ch-${chapterId}`) : articleRef.value
        target?.scrollIntoView({ behavior: chapterId ? 'smooth' : 'auto', block: 'start' })
        observeSections()
        syncHash()
    })
}

function backToGuides() {
    activeGuideId.value = null
    activeChapterId.value = null
    query.value = ''
    tocOpen.value = false
    sectionObserver?.disconnect()
    syncHash()
}

function scrollToChapter(chapterId) {
    tocOpen.value = false
    activeChapterId.value = chapterId
    activateLockUntil = Date.now() + 700
    const el = document.getElementById(chapterId)
    if (!el) return
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    syncHash()
}

function updateActiveFromScroll() {
    if (Date.now() < activateLockUntil) return
    const root = articleRef.value
    if (!root) return
    const marker = root.getBoundingClientRect().top + 96
    let current = chapterIds.value[0] || 'guide-top'
    for (const id of chapterIds.value) {
        const node = document.getElementById(id)
        if (node && node.getBoundingClientRect().top <= marker) {
            current = id
        }
    }
    activeChapterId.value = current
    syncHash()
}

function observeSections() {
    sectionObserver?.disconnect()
    const root = articleRef.value
    if (!root) return
    sectionObserver = new IntersectionObserver(updateActiveFromScroll, {
        root,
        threshold: [0, 0.15, 0.4, 0.8],
    })
    for (const id of chapterIds.value) {
        const node = document.getElementById(id)
        if (node) sectionObserver.observe(node)
    }
    updateActiveFromScroll()
}

function readHash() {
    const raw = window.location.hash.replace('#', '')
    if (!raw) return
    const [guideId, chapterId] = raw.split('/')
    if (guides.value.some((g) => g.id === guideId)) {
        openGuide(guideId, chapterId || null)
    }
}

watch(manualLocale, async () => {
    query.value = ''
    await nextTick()
    if (activeGuideId.value && !guides.value.some((g) => g.id === activeGuideId.value)) {
        backToGuides()
        return
    }
    if (activeGuideId.value) observeSections()
})

watch(query, async () => {
    if (!activeGuideId.value) return
    await nextTick()
    observeSections()
})

function onHashChange() {
    const raw = window.location.hash.replace('#', '')
    const [guideId, chapterId] = raw.split('/')
    if (!raw && activeGuideId.value) {
        backToGuides()
    } else if (guideId && guideId !== activeGuideId.value && guides.value.some((g) => g.id === guideId)) {
        openGuide(guideId, chapterId || null)
    }
}

onMounted(async () => {
    articleRef.value?.addEventListener('scroll', updateActiveFromScroll, { passive: true })
    window.addEventListener('hashchange', onHashChange)
    await nextTick()
    readHash()
})

onBeforeUnmount(() => {
    sectionObserver?.disconnect()
    articleRef.value?.removeEventListener('scroll', updateActiveFromScroll)
    window.removeEventListener('hashchange', onHashChange)
})
</script>

<template>
    <AppLayout :title="t('user_manual.page_title')" flush>
        <div
            class="user-manual flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-background"
            :dir="currentLocaleMeta.dir"
        >
            <header class="flex shrink-0 items-center justify-between gap-3 border-b border-border bg-background px-4 py-2.5">
                <div class="flex min-w-0 items-center gap-2">
                    <Button
                        v-if="activeGuide"
                        variant="ghost"
                        size="sm"
                        class="gap-1.5 shrink-0"
                        @click="backToGuides"
                    >
                        <component :is="backIcon" class="size-4" />
                        {{ t('user_manual.all_guides') }}
                    </Button>
                    <p class="truncate text-sm font-semibold text-foreground">
                        {{ activeGuide ? activeGuide.title : meta.title }}
                    </p>
                </div>
                <Button
                    v-if="activeGuide"
                    variant="outline"
                    size="sm"
                    class="gap-2 lg:hidden"
                    @click="tocOpen = true"
                >
                    <List class="size-4" />
                    {{ t('user_manual.chapters') }}
                </Button>
            </header>

            <!-- ============================ GUIDE PICKER ============================ -->
            <div
                v-if="!activeGuide"
                class="min-h-0 flex-1 overflow-y-auto bg-background px-4 py-6 sm:px-8 lg:px-10"
            >
                <section
                    class="relative overflow-hidden rounded-3xl border border-primary/30 bg-gradient-to-br from-violet-700 via-violet-600 to-amber-500 p-8 text-white shadow-lg sm:p-12"
                >
                    <div class="pointer-events-none absolute -end-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="pointer-events-none absolute -bottom-20 -start-10 h-56 w-56 rounded-full bg-amber-300/20 blur-3xl" />
                    <div class="relative space-y-4">
                        <span class="inline-flex rounded-full bg-white/15 px-4 py-1.5 text-sm font-medium backdrop-blur">
                            {{ meta.badge }}
                        </span>
                        <h1 class="text-3xl font-bold leading-tight sm:text-4xl">{{ meta.title }}</h1>
                        <p class="text-base text-violet-50 sm:text-lg">{{ meta.subtitle }}</p>
                        <p class="text-sm text-violet-100">{{ meta.version }}</p>
                    </div>
                </section>

                <section class="mx-auto mt-6 max-w-5xl space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">{{ meta.howToRead }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="item in MANUAL_LOCALES"
                                :key="item.id"
                                type="button"
                                class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                :class="manualLocale === item.id
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-muted text-foreground hover:bg-accent'"
                                @click="setManualLocale(item.id)"
                            >
                                {{ item.label }}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <button
                            v-for="guide in filteredGuides"
                            :key="guide.id"
                            type="button"
                            class="group flex flex-col gap-3 rounded-2xl border border-border bg-card p-5 text-start shadow-sm transition hover:border-primary/50 hover:shadow-md"
                            @click="openGuide(guide.id)"
                        >
                            <div class="flex items-center gap-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl" :class="accentFor(guide)">
                                    <component :is="iconFor(guide)" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-primary">{{ guide.number }}</p>
                                    <h3 class="truncate text-base font-semibold text-card-foreground">{{ guide.title }}</h3>
                                </div>
                            </div>
                            <p class="text-sm leading-6 text-muted-foreground">{{ guide.summary }}</p>
                            <p class="mt-auto flex items-center gap-1 text-xs font-medium text-primary opacity-0 transition group-hover:opacity-100">
                                {{ t('user_manual.open_chapter') }}
                                <component :is="isRtl ? ArrowLeft : ChevronRight" class="size-3.5" />
                            </p>
                        </button>
                    </div>
                </section>
            </div>

            <!-- ============================ GUIDE READER ============================ -->
            <div v-else class="flex min-h-0 flex-1 overflow-hidden">
                <aside class="hidden h-full w-80 shrink-0 overflow-y-auto border-e border-border bg-muted/30 p-4 lg:block">
                    <TocPanel
                        :t="t"
                        :guide="activeGuide"
                        :manual-locale="manualLocale"
                        :query="query"
                        :active-id="activeChapterId"
                        :filtered-chapters="filteredChapters"
                        @update:query="query = $event"
                        @set-locale="setManualLocale"
                        @go="scrollToChapter"
                    />
                </aside>

                <article
                    ref="articleRef"
                    class="min-h-0 min-w-0 flex-1 space-y-6 overflow-y-auto overflow-x-hidden bg-background px-4 py-6 sm:px-8 lg:px-10"
                >
                    <section id="guide-top" class="scroll-mt-6 space-y-3 rounded-2xl border border-border bg-card p-6 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl" :class="accentFor(activeGuide)">
                                <component :is="iconFor(activeGuide)" class="size-6" />
                            </span>
                            <div>
                                <p class="text-xs font-bold text-primary">{{ activeGuide.number }}</p>
                                <h2 class="text-2xl font-bold text-card-foreground">{{ activeGuide.title }}</h2>
                            </div>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ activeGuide.subtitle }}</p>
                        <p class="text-base leading-7 text-card-foreground">{{ activeGuide.summary }}</p>
                    </section>

                    <p
                        v-if="query && !filteredChapters.length"
                        class="rounded-xl border border-dashed px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        {{ t('user_manual.no_results') }}
                    </p>

                    <section
                        v-for="chapter in filteredChapters"
                        :id="`ch-${chapter.id}`"
                        :key="chapter.id"
                        class="scroll-mt-6 overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                    >
                        <header class="border-b border-border bg-muted/50 px-6 py-4">
                            <p class="text-xs font-bold tracking-wide text-primary">{{ chapter.number }}</p>
                            <h3 class="text-xl font-bold text-card-foreground">{{ chapter.title }}</h3>
                        </header>

                        <div class="space-y-5 px-6 py-6 text-base leading-8 text-card-foreground">
                            <template v-for="(block, index) in chapter.blocks" :key="index">
                                <h4
                                    v-if="block.type === 'h3'"
                                    class="pt-2 text-lg font-semibold text-primary"
                                >
                                    {{ block.text }}
                                </h4>

                                <p
                                    v-else-if="block.type === 'h4'"
                                    class="pt-1 text-base font-semibold text-card-foreground"
                                >
                                    {{ block.text }}
                                </p>

                                <p v-else-if="block.type === 'p'" class="text-card-foreground">
                                    {{ block.text }}
                                </p>

                                <ul
                                    v-else-if="block.type === 'list'"
                                    class="list-disc space-y-1.5 text-card-foreground marker:text-primary ltr:pl-5 rtl:pr-5"
                                >
                                    <li v-for="item in block.items" :key="item">{{ item }}</li>
                                </ul>

                                <ol
                                    v-else-if="block.type === 'ol'"
                                    class="list-decimal space-y-1.5 text-card-foreground marker:font-semibold marker:text-primary ltr:pl-5 rtl:pr-5"
                                >
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

                                <p
                                    v-else-if="block.type === 'formula'"
                                    class="flex items-center justify-center gap-2 rounded-xl border border-amber-500/40 border-s-4 border-s-amber-500 bg-amber-500/10 px-4 py-3 text-center font-medium text-foreground"
                                >
                                    <Sigma class="size-4 shrink-0 text-amber-500" />
                                    <span>{{ block.text }}</span>
                                </p>

                                <figure
                                    v-else-if="block.type === 'figure'"
                                    class="overflow-hidden rounded-xl border border-dashed border-border bg-muted/40"
                                >
                                    <img
                                        v-if="block.src && !missingFigures.has(block.src)"
                                        :src="block.src"
                                        :alt="block.caption"
                                        class="w-full"
                                        loading="lazy"
                                        @error="missingFigures.add(block.src)"
                                    />
                                    <div
                                        v-else
                                        class="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center text-muted-foreground"
                                    >
                                        <ImageIcon class="size-8" />
                                        <span class="text-xs">{{ block.hint || t('user_manual.figure_placeholder') }}</span>
                                    </div>
                                    <figcaption class="border-t border-border bg-card px-4 py-2 text-xs text-muted-foreground">
                                        {{ block.caption }}
                                    </figcaption>
                                </figure>

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

                    <div class="flex justify-center gap-2">
                        <Button variant="outline" class="gap-2" @click="backToGuides">
                            <component :is="backIcon" class="size-4" />
                            {{ t('user_manual.all_guides') }}
                        </Button>
                        <Button variant="outline" class="gap-2" @click="scrollToChapter('guide-top')">
                            <ArrowUp class="size-4" />
                            {{ t('user_manual.back_to_top') }}
                        </Button>
                    </div>
                </article>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="tocOpen && activeGuide"
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
                        <p class="font-semibold">{{ t('user_manual.chapters') }}</p>
                        <Button variant="ghost" size="icon" @click="tocOpen = false">
                            <X class="size-4" />
                        </Button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4">
                        <TocPanel
                            :t="t"
                            :guide="activeGuide"
                            :manual-locale="manualLocale"
                            :query="query"
                            :active-id="activeChapterId"
                            :filtered-chapters="filteredChapters"
                            @update:query="query = $event"
                            @set-locale="setManualLocale"
                            @go="scrollToChapter"
                        />
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
