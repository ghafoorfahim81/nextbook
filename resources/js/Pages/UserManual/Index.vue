<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/Layout.vue'
import { Button } from '@/Components/ui/button'
import {
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
    Languages,
    Lightbulb,
    List,
    Palmtree,
    ReceiptText,
    Search,
    SearchX,
    Sigma,
    ShoppingCart,
    TriangleAlert,
    Truck,
    Users,
    UsersRound,
    UserPlus,
    X,
} from 'lucide-vue-next'
import { getMeta, getGuides, getGuide, getGuidesByLocale, MANUAL_LOCALES } from './content'
import { buildSearchIndex, chapterMatches, normalize, searchIndex } from './content/search'
import TocPanel from './TocPanel.vue'
import ManualHighlight from './ManualHighlight.vue'

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
// Direction of the interface language (what the search bar's own text is in),
// which can differ from the guide language's direction.
const uiDir = computed(() => (['fa', 'ps'].includes(String(locale.value)) ? 'rtl' : 'ltr'))
const backIcon = computed(() => (isRtl.value ? ChevronRight : ChevronLeft))

const meta = computed(() => getMeta(manualLocale.value))
const guides = computed(() => getGuides(manualLocale.value))
const activeGuide = computed(() =>
    activeGuideId.value ? getGuide(manualLocale.value, activeGuideId.value) : null,
)

const filteredChapters = computed(() => {
    const guide = activeGuide.value
    if (!guide) return []
    if (!query.value.trim()) return guide.chapters
    return guide.chapters.filter((chapter) => chapterMatches(chapter, query.value))
})

/* ---------------------------------------------------------------------------
   Global search — every guide, every chapter, every language
   --------------------------------------------------------------------------- */

// The content is static, so the index is built once per page load.
const searchIndexData = buildSearchIndex(getGuidesByLocale())
const MIN_QUERY = 2

const globalQuery = ref('')
const globalScope = ref('all')
const activeResult = ref(0)
const globalInputRef = ref(null)
const resultsRef = ref(null)

const globalQueryReady = computed(() => normalize(globalQuery.value).length >= MIN_QUERY)

const globalSearch = computed(() => {
    if (!globalQueryReady.value) return null
    return searchIndex(searchIndexData, globalQuery.value, {
        locales: globalScope.value === 'all' ? null : [globalScope.value],
        preferLocale: manualLocale.value,
        limit: 60,
    })
})

const localeMetaById = Object.fromEntries(MANUAL_LOCALES.map((item) => [item.id, item]))

watch([globalQuery, globalScope], () => {
    activeResult.value = 0
    resultsRef.value?.scrollTo?.({ top: 0 })
})

function clearGlobalSearch() {
    globalQuery.value = ''
    globalInputRef.value?.focus()
}

function moveActiveResult(step) {
    const count = globalSearch.value?.items.length ?? 0
    if (!count) return
    activeResult.value = (activeResult.value + step + count) % count
    nextTick(() => {
        document
            .querySelector(`[data-result-index="${activeResult.value}"]`)
            ?.scrollIntoView({ block: 'nearest' })
    })
}

function onGlobalKeydown(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault()
        moveActiveResult(1)
    } else if (event.key === 'ArrowUp') {
        event.preventDefault()
        moveActiveResult(-1)
    } else if (event.key === 'Enter') {
        const result = globalSearch.value?.items[activeResult.value]
        if (result) {
            event.preventDefault()
            openResult(result)
        }
    } else if (event.key === 'Escape' && globalQuery.value) {
        event.preventDefault()
        globalQuery.value = ''
    }
}

/**
 * Open a hit in its own language, at its chapter, with the search terms
 * carried into the reader so they stay highlighted there.
 */
async function openResult(result) {
    const carry = globalQuery.value
    if (result.locale !== manualLocale.value) {
        setManualLocale(result.locale)
        // Let the locale watcher run (it resets the in-guide query) first.
        await nextTick()
    }
    openGuide(result.guide.id, result.chapter?.id ?? null, carry)
}

// "/" focuses the global search from anywhere on the guide picker.
function onWindowKeydown(event) {
    if (event.key !== '/' || activeGuideId.value) return
    const target = event.target
    const typing = target instanceof HTMLElement
        && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
    if (typing) return
    event.preventDefault()
    globalInputRef.value?.focus()
}

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

function openGuide(id, chapterId = null, carryQuery = '') {
    activeGuideId.value = id
    activeChapterId.value = chapterId ? `ch-${chapterId}` : 'guide-top'
    // Carry a global search into the reader only when it would leave the
    // target visible: the opened chapter must match it, or — for a hit on the
    // guide's own title/summary — at least one chapter must. Otherwise the
    // reader's filter would hide exactly what the user clicked.
    const guide = getGuide(manualLocale.value, id)
    let keep = false
    if (carryQuery && guide) {
        keep = chapterId
            ? guide.chapters.some((c) => c.id === chapterId && chapterMatches(c, carryQuery))
            : guide.chapters.some((c) => chapterMatches(c, carryQuery))
    }
    query.value = keep ? carryQuery : ''
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

function jumpToFirstMatch() {
    const first = filteredChapters.value[0]
    if (first) scrollToChapter(`ch-${first.id}`)
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
    // Reaching the manual by any route counts as onboarding done — stop the
    // first-login prompt from appearing again.
    if (usePage().props.auth?.user?.show_manual_prompt) {
        window.axios?.post(route('onboarding.manual-prompt.dismiss')).catch(() => {})
    }

    articleRef.value?.addEventListener('scroll', updateActiveFromScroll, { passive: true })
    window.addEventListener('hashchange', onHashChange)
    window.addEventListener('keydown', onWindowKeydown)
    await nextTick()
    readHash()
})

onBeforeUnmount(() => {
    sectionObserver?.disconnect()
    articleRef.value?.removeEventListener('scroll', updateActiveFromScroll)
    window.removeEventListener('hashchange', onHashChange)
    window.removeEventListener('keydown', onWindowKeydown)
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
                class="min-h-0 flex-1 overflow-y-auto bg-background px-3 py-3 sm:px-4"
            >
                <section
                    class="relative flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5 overflow-hidden rounded-xl border border-primary/30 bg-gradient-to-br from-violet-700 via-violet-600 to-amber-500 px-4 py-3 text-white shadow-sm"
                >
                    <div class="pointer-events-none absolute -end-10 -top-16 h-40 w-40 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative min-w-0">
                        <h1 class="truncate text-lg font-bold leading-tight sm:text-xl">{{ meta.title }}</h1>
                        <p class="truncate text-xs text-violet-50 sm:text-sm">{{ meta.subtitle }}</p>
                    </div>
                    <div class="relative flex shrink-0 items-center gap-2">
                        <span class="hidden rounded-full bg-white/15 px-2.5 py-1 text-xs font-medium backdrop-blur sm:inline-flex">
                            {{ meta.badge }}
                        </span>
                        <span class="text-xs text-violet-100">{{ meta.version }}</span>
                    </div>
                </section>

                <!-- ======================== GLOBAL SEARCH ======================== -->
                <section class="mt-4 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Same anatomy as the header's global search (GlobalSearch.vue):
                             one row, thin primary outline, translucent fill, primary icon,
                             muted kbd badge. It follows the interface language's direction,
                             not the guide's, so the placeholder reads correctly. A <label>
                             so a click anywhere on the bar focuses the input. -->
                        <label
                            :dir="uiDir"
                            class="flex h-10 min-w-0 flex-1 basis-[22rem] cursor-text items-center gap-2 rounded-md border border-primary bg-background/60 px-3 text-muted-foreground transition-colors focus-within:ring-2 focus-within:ring-primary/25 hover:text-foreground"
                        >
                            <Search class="size-4 shrink-0 text-primary opacity-80" />
                            <input
                                ref="globalInputRef"
                                v-model="globalQuery"
                                type="search"
                                autocomplete="off"
                                spellcheck="false"
                                :dir="globalQuery ? 'auto' : uiDir"
                                :placeholder="t('user_manual.global_search_placeholder')"
                                class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-start text-sm text-foreground shadow-none outline-none placeholder:text-muted-foreground focus:outline-none focus:ring-0 [&::-webkit-search-cancel-button]:hidden"
                                :aria-label="t('user_manual.global_search_placeholder')"
                                @keydown="onGlobalKeydown"
                            />
                            <span
                                v-if="globalQuery"
                                role="button"
                                tabindex="0"
                                class="flex shrink-0 cursor-pointer items-center text-muted-foreground hover:text-foreground"
                                :aria-label="t('user_manual.clear_search')"
                                @click.prevent="clearGlobalSearch"
                                @keydown.enter.prevent="clearGlobalSearch"
                            >
                                <X class="size-4" />
                            </span>
                            <kbd
                                v-else
                                dir="ltr"
                                class="hidden h-5 shrink-0 select-none items-center rounded border border-border bg-muted px-1.5 font-mono text-[10px] opacity-60 sm:inline-flex"
                            >/</kbd>
                        </label>

                        <!-- Which languages to search — separate from the language
                             the guides are shown in. Wraps instead of scrolling, so a
                             chip is never clipped. -->
                        <div class="flex flex-wrap items-center gap-1">
                            <Languages class="me-1 size-4 shrink-0 text-muted-foreground" />
                            <span
                                v-for="scope in [{ id: 'all', label: t('user_manual.search_scope_all') }, ...MANUAL_LOCALES]"
                                :key="scope.id"
                                role="button"
                                tabindex="0"
                                class="cursor-pointer whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                :class="globalScope === scope.id
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-muted text-foreground hover:bg-accent'"
                                @click="globalScope = scope.id"
                                @keydown.enter.prevent="globalScope = scope.id"
                            >
                                {{ scope.label }}
                            </span>
                        </div>
                    </div>

                    <p
                        v-if="globalQuery.trim() && !globalQueryReady"
                        dir="auto"
                        class="px-1 text-start text-xs text-muted-foreground"
                    >
                        {{ t('user_manual.search_min_chars', { count: MIN_QUERY }) }}
                    </p>
                </section>

                <!-- Results replace the guide grid while a search is active. -->
                <section v-if="globalSearch" class="mt-3 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                        <p class="text-sm text-muted-foreground" dir="auto">
                            <template v-if="globalSearch.total">
                                {{ t('user_manual.results_summary', { total: globalSearch.total, guides: globalSearch.guides }) }}
                                <template v-if="globalSearch.total > globalSearch.items.length">
                                    · {{ t('user_manual.results_capped', { shown: globalSearch.items.length }) }}
                                </template>
                            </template>
                        </p>
                        <p v-if="globalSearch.total" dir="auto" class="hidden text-xs text-muted-foreground md:block">
                            {{ t('user_manual.search_hint_keys') }}
                        </p>
                    </div>

                    <div
                        v-if="!globalSearch.total"
                        class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-card px-4 py-10 text-center"
                    >
                        <SearchX class="size-8 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">{{ t('user_manual.no_results') }}</p>
                        <Button
                            v-if="globalScope !== 'all'"
                            variant="outline"
                            size="sm"
                            @click="globalScope = 'all'"
                        >
                            {{ t('user_manual.search_all_instead') }}
                        </Button>
                    </div>

                    <!-- role="list"/"button", not listbox/option: glass.css paints every
                         [role='listbox'] as a blurred popover pane. -->
                    <div v-else ref="resultsRef" class="space-y-2" role="list" :aria-label="t('user_manual.global_search_placeholder')">
                        <div
                            v-for="(result, index) in globalSearch.items"
                            :key="result.key"
                            :data-result-index="index"
                            role="button"
                            tabindex="-1"
                            :aria-current="index === activeResult ? 'true' : undefined"
                            :dir="localeMetaById[result.locale]?.dir"
                            class="group flex cursor-pointer items-start gap-3 rounded-xl border bg-card px-3.5 py-3 text-start shadow-sm transition"
                            :class="index === activeResult
                                ? 'border-primary/60 ring-2 ring-primary/20'
                                : 'border-border hover:border-primary/40'"
                            @click="openResult(result)"
                            @mousemove="activeResult = index"
                        >
                            <!-- mousemove, not mouseenter: a result list re-rendering under a
                                 resting cursor fires mouseenter and stole the keyboard
                                 selection; mousemove only fires on real movement. -->
                            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg" :class="accentFor(result.guide)">
                                <component :is="iconFor(result.guide)" class="size-4.5" />
                            </span>
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-sm">
                                    <span class="font-semibold text-card-foreground">
                                        <ManualHighlight :text="result.chapter ? result.chapter.title : result.guide.title" :query="globalQuery" />
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        ·
                                        <ManualHighlight
                                            :text="result.chapter ? result.guide.title : t('user_manual.guide_overview')"
                                            :query="result.chapter ? globalQuery : ''"
                                        />
                                    </span>
                                    <span
                                        v-if="globalScope === 'all'"
                                        class="rounded-full border border-border bg-muted px-1.5 py-px text-[10px] font-medium leading-4 text-muted-foreground"
                                    >
                                        {{ localeMetaById[result.locale]?.label }}
                                    </span>
                                </div>
                                <p class="line-clamp-2 text-[13px] leading-6 text-muted-foreground">
                                    <ManualHighlight :text="result.snippet" :query="globalQuery" />
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-else class="mt-4 space-y-3">
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

                    <!-- A plain div, not a <button> — glass.css forces every <button>
                         into a 9999px capsule, which is right for real buttons but
                         wrong for a card this size. .bg-card already picks up the
                         glass tint, sheen and specular edge on its own, so a div gets
                         the "liquid glass" look without the pill shape. -->
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="guide in guides"
                            :key="guide.id"
                            role="button"
                            tabindex="0"
                            class="group flex cursor-pointer flex-col gap-2 rounded-2xl border border-border bg-card p-4 text-start shadow-sm transition hover:border-primary/50 hover:shadow-md"
                            @click="openGuide(guide.id)"
                            @keydown.enter.prevent="openGuide(guide.id)"
                            @keydown.space.prevent="openGuide(guide.id)"
                        >
                            <div class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg" :class="accentFor(guide)">
                                    <component :is="iconFor(guide)" class="size-4.5" />
                                </span>
                                <h3 class="min-w-0 flex-1 truncate text-base font-semibold text-card-foreground">{{ guide.title }}</h3>
                            </div>
                            <p class="line-clamp-2 text-sm leading-6 text-muted-foreground">{{ guide.summary }}</p>
                        </div>
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
                        @submit="jumpToFirstMatch"
                    />
                </aside>

                <article
                    ref="articleRef"
                    class="min-h-0 min-w-0 flex-1 space-y-4 overflow-y-auto overflow-x-hidden bg-background px-3 py-3 sm:px-4"
                >
                    <section id="guide-top" class="scroll-mt-6 space-y-2 rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
                        <div class="flex items-center gap-2.5">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg" :class="accentFor(activeGuide)">
                                <component :is="iconFor(activeGuide)" class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-primary">{{ activeGuide.number }}</p>
                                <h2 class="text-lg font-bold text-card-foreground">{{ activeGuide.title }}</h2>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ activeGuide.subtitle }}</p>
                        <p class="text-sm leading-6 text-card-foreground">{{ activeGuide.summary }}</p>
                    </section>

                    <div
                        v-if="query.trim()"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm text-foreground"
                    >
                        <span class="inline-flex min-w-0 items-center gap-2">
                            <Search class="size-4 shrink-0 text-amber-500" />
                            <span class="truncate" dir="auto">
                                {{ t('user_manual.filtered_banner', { query: query.trim() }) }}
                                · {{ t('user_manual.chapters_matching', { count: filteredChapters.length, total: activeGuide.chapters.length }) }}
                            </span>
                        </span>
                        <Button variant="ghost" size="sm" class="h-7 gap-1" @click="query = ''">
                            <X class="size-3.5" />
                            {{ t('user_manual.clear_search') }}
                        </Button>
                    </div>

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
                            <h3 class="text-xl font-bold text-card-foreground"><ManualHighlight :text="chapter.title" :query="query" /></h3>
                        </header>

                        <div class="space-y-5 px-6 py-6 text-base leading-8 text-card-foreground">
                            <template v-for="(block, index) in chapter.blocks" :key="index">
                                <h4
                                    v-if="block.type === 'h3'"
                                    class="pt-2 text-lg font-semibold text-primary"
                                >
                                    <ManualHighlight :text="block.text" :query="query" />
                                </h4>

                                <p
                                    v-else-if="block.type === 'h4'"
                                    class="pt-1 text-base font-semibold text-card-foreground"
                                >
                                    <ManualHighlight :text="block.text" :query="query" />
                                </p>

                                <p v-else-if="block.type === 'p'" class="text-card-foreground">
                                    <ManualHighlight :text="block.text" :query="query" />
                                </p>

                                <ul
                                    v-else-if="block.type === 'list'"
                                    class="list-disc space-y-1.5 text-card-foreground marker:text-primary ltr:pl-5 rtl:pr-5"
                                >
                                    <li v-for="item in block.items" :key="item"><ManualHighlight :text="item" :query="query" /></li>
                                </ul>

                                <ol
                                    v-else-if="block.type === 'ol'"
                                    class="list-decimal space-y-1.5 text-card-foreground marker:font-semibold marker:text-primary ltr:pl-5 rtl:pr-5"
                                >
                                    <li v-for="item in block.items" :key="item"><ManualHighlight :text="item" :query="query" /></li>
                                </ol>

                                <div
                                    v-else-if="block.type === 'note'"
                                    class="rounded-xl border border-sky-500/40 border-s-4 border-s-sky-500 bg-sky-500/15 px-4 py-3 text-base leading-8 text-foreground"
                                >
                                    <p class="mb-1 inline-flex items-center gap-1.5 font-semibold text-foreground">
                                        <Info class="size-3.5 shrink-0 text-sky-500" />
                                        <ManualHighlight :text="block.label" :query="query" />
                                    </p>
                                    <p class="text-foreground"><ManualHighlight :text="block.text" :query="query" /></p>
                                </div>

                                <div
                                    v-else-if="block.type === 'tip'"
                                    class="rounded-xl border border-emerald-500/40 border-s-4 border-s-emerald-500 bg-emerald-500/15 px-4 py-3 text-base leading-8 text-foreground"
                                >
                                    <p class="mb-1 inline-flex items-center gap-1.5 font-semibold text-foreground">
                                        <Lightbulb class="size-3.5 shrink-0 text-emerald-500" />
                                        <ManualHighlight :text="block.label" :query="query" />
                                    </p>
                                    <p class="text-foreground"><ManualHighlight :text="block.text" :query="query" /></p>
                                </div>

                                <div
                                    v-else-if="block.type === 'warn'"
                                    class="rounded-xl border border-red-500/40 border-s-4 border-s-red-500 bg-red-500/15 px-4 py-3 text-base leading-8 text-foreground"
                                >
                                    <p class="mb-1 inline-flex items-center gap-1.5 font-semibold text-foreground">
                                        <TriangleAlert class="size-3.5 shrink-0 text-red-500" />
                                        <ManualHighlight :text="block.label" :query="query" />
                                    </p>
                                    <p class="text-foreground"><ManualHighlight :text="block.text" :query="query" /></p>
                                </div>

                                <p
                                    v-else-if="block.type === 'formula'"
                                    class="flex items-center justify-center gap-2 rounded-xl border border-amber-500/40 border-s-4 border-s-amber-500 bg-amber-500/10 px-4 py-3 text-center font-medium text-foreground"
                                >
                                    <Sigma class="size-4 shrink-0 text-amber-500" />
                                    <span><ManualHighlight :text="block.text" :query="query" /></span>
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
                                        <ManualHighlight :text="block.caption" :query="query" />
                                    </figcaption>
                                </figure>

                                <div
                                    v-else-if="block.type === 'flow'"
                                    class="flex flex-wrap items-stretch gap-2"
                                >
                                    <template v-for="(step, stepIndex) in block.steps" :key="step">
                                        <div class="min-w-[8rem] flex-1 rounded-xl border border-primary/30 bg-primary/10 px-3 py-3 text-center text-sm font-medium text-foreground">
                                            <ManualHighlight :text="step" :query="query" />
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
                                                    <ManualHighlight :text="header" :query="query" />
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
                                                    <ManualHighlight :text="cell" :query="query" />
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
                            @submit="jumpToFirstMatch"
                        />
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
