<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import {
    Search, Package, FileText, ShoppingCart, Banknote,
    TrendingDown, BookOpen, User, Building2, ReceiptText,
} from 'lucide-vue-next'
import { useGlobalSearch, searchIndex, loadIndex } from '@/composables/useGlobalSearch'

const { query, results, people, items, finance, counts, isLoading } = useGlobalSearch()
const { t, locale } = useI18n()

// ── open / close ──────────────────────────────────────────────────────────────
const isOpen    = ref(false)
const inputRef  = ref<HTMLInputElement | null>(null)
const activeTab = ref<'all' | 'people' | 'items' | 'finance'>('all')
const activeIdx = ref(-1)

function openSearch() {
    isOpen.value    = true
    activeIdx.value = -1
    const forceReload = true
    console.log('[GlobalSearch] Opening, index size:', searchIndex.value.length, '— force reload:', forceReload)
    loadIndex(forceReload)
    nextTick(() => inputRef.value?.focus())
}

function closeSearch() {
    isOpen.value   = false
    query.value    = ''
    activeIdx.value = -1
}

// ── Ctrl/Cmd + K ─────────────────────────────────────────────────────────────
function onGlobalKey(e: KeyboardEvent) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault()
        isOpen.value ? closeSearch() : openSearch()
    }
}
onMounted(()  => document.addEventListener('keydown', onGlobalKey))
onUnmounted(() => document.removeEventListener('keydown', onGlobalKey))

// ── tabs ──────────────────────────────────────────────────────────────────────
const TABS = [
    { key: 'all',     labelKey: 'global_search.tabs.all' },
    { key: 'items',   labelKey: 'global_search.tabs.items' },
    { key: 'people',  labelKey: 'global_search.tabs.people' },
    { key: 'finance', labelKey: 'global_search.tabs.finance' },
] as const

const tabLabel = (key: string) => t(`global_search.tabs.${key}`)

watch(query, () => { activeIdx.value = -1 })

// ── display groups (indexed for keyboard nav) ─────────────────────────────────
const displayGroups = computed(() => {
    const tab = activeTab.value

    if (tab !== 'all') {
        const map: Record<string, typeof results.value> = {
            people:  people.value,
            items:   items.value,
            finance: finance.value,
        }
        return [{ key: tab, label: tabLabel(tab), items: (map[tab] ?? []).map((r, i) => ({ ...r, _idx: i })) }]
    }

    const groups = []
    let offset = 0
    if (people.value.length)  { groups.push({ key: 'people',  label: tabLabel('people'),  items: people.value.map( (r, i) => ({ ...r, _idx: offset + i })) }); offset += people.value.length  }
    if (items.value.length)   { groups.push({ key: 'items',   label: tabLabel('items'),   items: items.value.map(  (r, i) => ({ ...r, _idx: offset + i })) }); offset += items.value.length   }
    if (finance.value.length) { groups.push({ key: 'finance', label: tabLabel('finance'), items: finance.value.map((r, i) => ({ ...r, _idx: offset + i })) }); offset += finance.value.length }
    return groups
})

const flatLen = computed(() =>
    displayGroups.value.reduce((n, g) => n + g.items.length, 0)
)

// ── keyboard navigation ───────────────────────────────────────────────────────
function onInputKey(e: KeyboardEvent) {
    if (e.key === 'ArrowDown')  { e.preventDefault(); activeIdx.value = Math.min(activeIdx.value + 1, flatLen.value - 1); scrollToActive() }
    if (e.key === 'ArrowUp')    { e.preventDefault(); activeIdx.value = Math.max(activeIdx.value - 1, -1); scrollToActive() }
    if (e.key === 'Enter')      { e.preventDefault(); openActive(e) }
    if (e.key === 'Escape')     { closeSearch() }
}

const itemEls = ref<Record<number, HTMLElement>>({})

function scrollToActive() {
    nextTick(() => itemEls.value[activeIdx.value]?.scrollIntoView({ block: 'nearest' }))
}

function openActive(event?: KeyboardEvent) {
    for (const g of displayGroups.value) {
        const match = g.items.find(r => r._idx === activeIdx.value)
        if (match) { navigate(match.item, event); return }
    }
}

function navigate(item: any, event?: MouseEvent | KeyboardEvent) {
    if (event && ('ctrlKey' in event) && (event.ctrlKey || event.metaKey)) {
        window.open(item.url, '_blank')
        closeSearch()
        return
    }
    router.visit(item.url)
    closeSearch()
}

const isRtlLocale = computed(() => ['fa', 'ps', 'ar'].includes(String(locale.value).toLowerCase()))

function getResultName(result: any): string {
    const item = result?.item ?? {}
    if (item.type === 'report' && isRtlLocale.value && item.local_name) {
        return String(item.local_name)
    }
    return String(item.name ?? '')
}

// ── highlight ─────────────────────────────────────────────────────────────────
function esc(s: string) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
}

function highlight(result: any, key = 'name', isActive = false): string {
    const useLocalReportName = key === 'name'
        && result?.item?.type === 'report'
        && isRtlLocale.value
        && result?.item?.local_name
    const displayKey = useLocalReportName ? 'local_name' : key
    const text  = key === 'name' ? getResultName(result) : String(result.item?.[key] ?? '')
    const match = result.matches?.find((m: any) => m.key === displayKey)
    if (!match?.indices?.length) return esc(text)

    const markCls = isActive
        ? 'bg-white/30 text-white font-bold rounded-[2px] not-italic'
        : 'bg-primary/25 text-primary rounded-[2px] not-italic font-medium'

    let out = '', last = 0
    for (const [s, e] of match.indices as [number, number][]) {
        out += esc(text.slice(last, s))
        out += `<mark class="${markCls}">${esc(text.slice(s, e + 1))}</mark>`
        last = e + 1
    }
    return out + esc(text.slice(last))
}

// ── type styling ──────────────────────────────────────────────────────────────
const PERSON_TYPES = new Set(['customer', 'supplier', 'owner', 'user'])
const ICON_MAP: Record<string, any> = {
    item: Package, sale: FileText, purchase: ShoppingCart,
    receipt: ReceiptText, payment: Banknote, expense: TrendingDown,
    account: BookOpen, report: FileText, user: User, owner: Building2,
}
const AVATAR_BG: Record<string, string> = {
    customer: 'bg-blue-500',   supplier: 'bg-orange-500',
    owner:    'bg-indigo-500', user:     'bg-violet-500',
    item:     'bg-teal-600',   sale:     'bg-emerald-500',
    purchase: 'bg-amber-500',  receipt:  'bg-sky-500',
    payment:  'bg-purple-500', expense:  'bg-rose-500',
    account:  'bg-slate-500',  report:   'bg-cyan-600',
}
const TYPE_BADGE_CLS: Record<string, string> = {
    customer: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    supplier: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
    owner:    'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
    user:     'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
    item:     'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300',
    sale:     'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    purchase: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    receipt:  'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    payment:  'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    expense:  'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    account:  'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    report:   'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300',
}
const TYPE_LABELS: Record<string, string> = {
    customer: 'Customer', supplier: 'Supplier', owner: 'Owner', user: 'User',
    item: 'Product', sale: 'Invoice', purchase: 'Purchase',
    receipt: 'Receipt', payment: 'Payment', expense: 'Expense',
    account: 'Account', report: 'Report',
}
const STATUS_CLS: Record<string, string> = {
    active:          'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    inactive:        'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
    paid:            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    unpaid:          'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
    partially_paid:  'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-500',
    in_stock:        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    low_stock:       'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-500',
}

const avatarBg   = (t: string) => AVATAR_BG[t]  ?? 'bg-gray-500'
const typeBadgeCls = (t: string) => TYPE_BADGE_CLS[t] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
const typeLabel  = (t: string) => TYPE_LABELS[t] ?? t
const statusCls  = (s: string | null) => STATUS_CLS[s ?? ''] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-800'
const typeIcon   = (t: string) => ICON_MAP[t] ?? null
const isPerson   = (t: string) => PERSON_TYPES.has(t)
const initials   = (n: string) => n.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
</script>

<template>
    <!-- ── Header trigger button ──────────────────────────────────────────── -->
    <button
        @click="openSearch"
        class="hidden md:flex items-center gap-2 h-8 w-[260px] lg:w-[360px] rounded-md border border-primary bg-background/60 px-3 text-xs text-muted-foreground hover:border-primary hover:text-foreground transition-colors shrink-0"
        :aria-label="t('global_search.open_aria')"
    >
        <Search class="size-3.5 shrink-0 opacity-60 text-primary" />
        <span class="flex-1 text-start">{{ t('global_search.trigger') }}</span>
        <kbd dir="ltr" class="hidden sm:inline-flex h-5 select-none items-center gap-0.5 rounded border border-border bg-muted px-1.5 font-mono text-[10px] opacity-60">
            <span class="text-xs">⌘</span>K
        </kbd>
    </button>

    <!-- Mobile icon-only trigger -->
    <button
        @click="openSearch"
        class="md:hidden flex items-center justify-center size-8 rounded-md text-muted-foreground hover:bg-primary hover:text-foreground transition-colors"
        :aria-label="t('general.search')"
    >
        <Search class="size-4" />
    </button>

    <!-- ── Modal (teleported to body) ────────────────────────────────────── -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[999] flex items-start justify-center pt-[8vh] px-4"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeSearch" />

                <!-- Panel -->
                <Transition
                    appear
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 scale-[0.97] -translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-[0.97]"
                >
                    <div
                        v-if="isOpen"
                        class="relative z-10 w-full max-w-[540px] rounded-xl border border-border bg-card shadow-2xl overflow-hidden"
                    >
                        <!-- Input row -->
                        <div class="flex items-center gap-2.5 border-b border-border px-4 py-3">
                            <Search class="size-4 shrink-0 text-primary" />
                            <input
                                ref="inputRef"
                                v-model="query"
                                type="text"
                                :placeholder="t('global_search.placeholder')"
                                class="flex-1 bg-transparent text-start text-sm text-foreground placeholder:text-muted-foreground outline-none rounded-md border-primary focus:border-none focus:ring-2 focus:ring-ring"
                                autocomplete="off"
                                spellcheck="false"
                                @keydown="onInputKey"
                            />
                            <button
                                v-if="query"
                                @click="query = ''"
                                class="shrink-0 rounded border border-input bg-background px-2 py-0.5 text-[10px] text-muted-foreground hover:text-foreground transition-colors"
                            >
                                {{ t('general.clear') }}
                            </button>
                            <kbd
                                v-else
                                dir="ltr"
                                class="hidden sm:inline-flex h-5 select-none items-center rounded border border-border bg-muted px-1.5 font-mono text-[10px] opacity-60 shrink-0"
                            >Esc</kbd>
                        </div>

                        <!-- Tabs -->
                        <div class="flex items-center gap-0.5 border-b border-border px-3 py-1.5">
                            <button
                                v-for="tab in TABS"
                                :key="tab.key"
                                @click="activeTab = tab.key; activeIdx = -1"
                                :class="[
                                    'flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] font-medium transition-colors',
                                    activeTab === tab.key
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-primary'
                                ]"
                            >
                                {{ t(tab.labelKey) }}
                                <span
                                    v-if="counts[tab.key] > 0"
                                    :class="['text-[9px]', activeTab === tab.key ? 'opacity-80' : 'opacity-55']"
                                >{{ counts[tab.key] }}</span>
                            </button>
                        </div>

                        <!-- Results -->
                        <div class="max-h-[400px] overflow-y-auto py-1.5">

                            <!-- Waiting for input -->
                            <div v-if="query.trim().length < 2" class="flex flex-col items-center py-10 text-center">
                                <Search class="mb-2 size-8 text-muted-foreground/25" />
                                <p class="text-sm text-muted-foreground">{{ t('global_search.empty_title') }}</p>
                                <p class="mt-1 text-xs text-muted-foreground/50">{{ t('global_search.empty_description') }}</p>
                            </div>

                            <!-- Loading -->
                            <div v-else-if="isLoading" class="py-10 text-center text-sm text-muted-foreground">
                                {{ t('global_search.loading_index') }}
                            </div>

                            <!-- No results -->
                            <div v-else-if="displayGroups.every(g => g.items.length === 0)" class="py-10 text-center">
                                <p class="text-sm text-muted-foreground">
                                    {{ t('global_search.no_results_for', { query }) }}
                                </p>
                            </div>

                            <!-- Result groups -->
                            <template v-else>
                                <template v-for="group in displayGroups" :key="group.key">
                                    <div v-if="group.items.length">

                                        <!-- Section label (All tab only) -->
                                        <div
                                            v-if="activeTab === 'all'"
                                            class="px-4 pt-2.5 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground/55"
                                        >{{ group.label }}</div>

                                        <button
                                            v-for="result in group.items"
                                            :key="result.item.id"
                                            :ref="(el) => { if (el) itemEls[result._idx] = el as HTMLElement }"
                                            @click="(e) => navigate(result.item, e)"
                                            @mouseenter="activeIdx = result._idx"
                                            :class="[
                                                'w-full flex items-center gap-3 px-4 py-2.5 text-start transition-colors',
                                                result._idx === activeIdx
                                                    ? 'bg-primary'
                                                    : 'hover:bg-primary/15',
                                            ]"
                                        >
                                            <!-- Avatar / Icon -->
                                            <div :class="['flex size-8 shrink-0 items-center justify-center rounded-md text-white text-[11px] font-semibold', avatarBg(result.item.type)]">
                                                <component
                                                    v-if="typeIcon(result.item.type) && !isPerson(result.item.type)"
                                                    :is="typeIcon(result.item.type)"
                                                    class="size-4"
                                                />
                                                <span v-else>{{ initials(result.item.name) }}</span>
                                            </div>

                                            <!-- Name + subtitle -->
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                    <div
                                                        :class="['text-sm font-medium leading-tight truncate', result._idx === activeIdx ? 'text-white' : 'text-foreground']"
                                                        v-html="highlight(result, 'name', result._idx === activeIdx)"
                                                    />
                                                    <!-- Type badge -->
                                                    <span :class="[
                                                        'shrink-0 rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide leading-none',
                                                        result._idx === activeIdx
                                                            ? 'bg-white/20 text-white'
                                                            : typeBadgeCls(result.item.type)
                                                    ]">{{ typeLabel(result.item.type) }}</span>
                                                </div>
                                                <div :class="['text-[11px] truncate mt-0.5', result._idx === activeIdx ? 'text-white/70' : 'text-muted-foreground']">
                                                    {{ result.item.subtitle }}
                                                </div>
                                            </div>

                                            <!-- Status badge -->
                                            <span
                                                v-if="result.item.status_label"
                                                :class="[
                                                    'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                    result._idx === activeIdx
                                                        ? 'bg-white/20 text-white'
                                                        : statusCls(result.item.status)
                                                ]"
                                            >{{ result.item.status_label }}</span>
                                        </button>
                                    </div>
                                </template>
                            </template>
                        </div>

                        <!-- Footer hints -->
                        <div class="flex items-center gap-4 border-t border-border px-4 py-2 text-[10px] text-muted-foreground/70">
                            <span class="flex items-center gap-1">
                                <kbd dir="ltr" class="inline-flex h-4 items-center rounded border border-border/80 bg-muted px-1 font-mono text-[9px]">↑</kbd>
                                <kbd dir="ltr" class="inline-flex h-4 items-center rounded border border-border/80 bg-muted px-1 font-mono text-[9px]">↓</kbd>
                                {{ t('global_search.navigate') }}
                            </span>
                            <span class="flex items-center gap-1">
                                <kbd dir="ltr" class="inline-flex h-4 items-center rounded border border-border/80 bg-muted px-1 font-mono text-[9px]">↵</kbd>
                                {{ t('general.open') }}
                            </span>
                            <span class="flex items-center gap-1">
                                <kbd dir="ltr" class="inline-flex h-4 items-center rounded border border-border/80 bg-muted px-1 font-mono text-[9px]">Ctrl</kbd>
                                <span>+</span>
                                <kbd dir="ltr" class="inline-flex h-4 items-center rounded border border-border/80 bg-muted px-1 font-mono text-[9px]">↵</kbd>
                                {{ t('global_search.open_new_tab') }}
                            </span>
                            <span class="flex items-center gap-1">
                                <kbd dir="ltr" class="inline-flex h-4 items-center rounded border border-border/80 bg-muted px-1 font-mono text-[9px]">Esc</kbd>
                                {{ t('general.close') }}
                            </span>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
