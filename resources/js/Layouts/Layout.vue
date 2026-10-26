<script setup lang=ts>
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/Components/ui/avatar'
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/Components/ui/collapsible'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip'
import {
    Kbd,
    KbdGroup,
} from '@/Components/ui/kbd'
import { Separator } from '@/Components/ui/separator'
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger,
} from '@/Components/ui/sidebar'
import {
    AudioWaveform,
    BadgeCheck,
    Bell,
    BookOpen,
    Bot,
    CalendarClock,
    CalendarDays,
    ChevronRight,
    ChevronLeft,
    ChevronsUpDown,
    BriefcaseBusiness,
    Wallet,
    UserPlus,
    Command,
    CreditCard,
    GalleryVerticalEnd,
    LogOut,
    Plus,
    Sparkles,
    Moon, Sun,
    ChartColumn,
    Cog,
    Layers, UserCog, Database,
    User2Icon,
    ContactRound,
    BookUser,
    UserRound,
    Banknote,
    ShoppingBasket,
    ArrowLeftRight,
    PlusCircle,
    FileText,
    Search,
    X,
    FileChartLine,
    SlidersHorizontal,
    Tag,
    Trash2,
    Settings2,
    History,
    Rocket,
    ChartNoAxesCombined,
    Building2,
    Store
} from 'lucide-vue-next'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
// @ts-ignore - Vue SFC default export shim
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import { usePage, Link, router } from '@inertiajs/vue3'
// @ts-ignore - Vue SFC default export shim
import Toaster from '@/Components/ui/toast/Toaster.vue'
// @ts-ignore - Vue SFC default export shim
import Sonner from '@/Components/ui/sonner/Sonner.vue'
import { toast } from 'vue-sonner'
// @ts-ignore - Vue SFC default export shim
import PageLoadingOverlay from '@/Components/next/PageLoadingOverlay.vue'
import { HousePlug, ShoppingCart, Receipt as ReceiptIcon, Home, LayoutGrid } from 'lucide-vue-next'
// @ts-ignore - plain JS module
import { buildAppModules, visibleModules, moduleForUrl, moduleTone } from '@/lib/appModules'
// @ts-ignore - Vue SFC default export shim
import NotificationDropdown from '@/Components/notifications/NotificationDropdown.vue'
import ManualWelcomeDialog from '@/Components/Onboarding/ManualWelcomeDialog.vue'
import GlobalSearch from '@/Components/GlobalSearch.vue'
import QuickLinks from '@/Components/next/QuickLinks.vue'
const { t } = useI18n()
const { locale } = useI18n()
const { user } = useAuth()
// This is sample data.
const profilePhotoUrl = computed(() => (page.props.auth?.user as any)?.profile_photo_url || null)

const data = {
    user: {
        name: user.value?.name || '',
        email: user.value?.email || '',
        avatar: user.value?.avatar || null as string | null,
    },
    teams: [
        {
            name: 'Acme Inc',
            logo: GalleryVerticalEnd,
            plan: 'Enterprise',
        },
        {
            name: 'Acme Corp.',
            logo: AudioWaveform,
            plan: 'Startup',
        },
        {
            name: 'Evil Corp.',
            logo: Command,
            plan: 'Free',
        },
    ],
    navMain: [],
    // projects: [
    //     {
    //         name: 'Design Engineering',
    //         url: '#',
    //         icon: Frame,
    //     },
    //     {
    //         name: 'Sales & Marketing',
    //         url: '#',
    //         icon: PieChart,
    //     },
    //     {
    //         name: 'Travel',
    //         url: '#',
    //         icon: Map,
    //     },
    // ],
}

const activeTeam = ref(data.teams[0])
const page = usePage<any>()
const selectDir = computed(() => ((page.props as { direction?: string }).direction === 'rtl' ? 'rtl' : 'ltr'))

function setActiveTeam(team: typeof data.teams[number]) {
    activeTeam.value = team
}

// Normalize a URL to its pathname without query or trailing slash
function normalizePath(url: string): string {
    const path = (url || '').split('?')[0]
    if (path === '/') return '/'
    return path.replace(/\/+$/, '')
}

// Function to check if a menu item is active (ignore query params; match by prefix)
function isMenuItemActive(itemUrl: string): boolean {
    const currentPath = normalizePath(page.url)
    const targetPath = normalizePath(itemUrl)
    const matches = (path: string) => currentPath === path || currentPath.startsWith(`${path}/`)

    if (!matches(targetPath)) return false

    // A longer link that also matches is the real one: on /attendances/roster,
    // "Daily roster", not "Attendance register" (/attendances).
    return !allMenuPaths.value.some((path) => path.length > targetPath.length && matches(path))
}

// --- EXCLUSIVE COLLAPSIBLE LOGIC START ---

// Function to check if a parent menu should be expanded (has active child)
function shouldExpandParent(items: any[] | undefined): boolean {
    if (!items || !Array.isArray(items)) {
        return false
    }
    return items.some(item => isMenuItemActive(item.url))
}
import { useColorMode, useMediaQuery } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { applyAppearanceTheme, resolveDisplayColorMode } from '@/lib/theme'

const mode = useColorMode({
    emitAuto: true,
    modes: {
        contrast: 'dark contrast',
        cafe: 'cafe',
    },
})

// vue-sonner has its own theme system; keep it in sync with Tailwind dark mode.
const sonnerTheme = computed(() =>
    isDarkModeActive.value ? 'dark' : 'light',
)

const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))
const sidebarSide = computed(() => isRTL.value ? 'right' : 'left')
const chevronIcon = computed(() => isRTL.value ? ChevronLeft : ChevronRight)

// Allow parent pages to control initial sidebar state
// launcher: the module list's bare frame, like Odoo's home: no sidebar, search,
// switchers or quick links; only notifications, language and colour mode.
const props = withDefaults(defineProps<{ sidebarCollapsed?: boolean; flush?: boolean; launcher?: boolean }>(), {
    sidebarCollapsed: false,
    flush: false,
})

const { can, isSuperAdmin } = useAuth()

// Keep track of sidebar open/collapsed state so we can alter menu behavior.
// (We can't read sidebar context here because SidebarProvider is a child component.)
const sidebarOpen = ref(!props.sidebarCollapsed)
const isMobileViewport = useMediaQuery('(max-width: 767px)')
const prefersDarkScheme = useMediaQuery('(prefers-color-scheme: dark)')
const isSidebarCollapsed = computed(() => !sidebarOpen.value && !isMobileViewport.value)
const flyoutSide = computed(() => sidebarSide.value === 'right' ? 'left' : 'right')
const isDarkModeActive = computed(() => mode.value === 'dark' || (mode.value === 'auto' && prefersDarkScheme.value))

type ConnectionState = 'online' | 'slow' | 'offline'
const connectionState = ref<ConnectionState>('online')
const connectionLabel = computed(() => {
    if (connectionState.value === 'offline') {
        return t('layout.no_connection')
    }

    if (connectionState.value === 'slow') {
        return t('layout.slow_connection')
    }

    return t('layout.online')
})

const connectionDotClass = computed(() => {
    if (connectionState.value === 'offline') {
        return 'bg-red-500 shadow-[0_0_0_4px_rgba(239,68,68,0.15)]'
    }

    if (connectionState.value === 'slow') {
        return 'bg-amber-500 shadow-[0_0_0_4px_rgba(245,158,11,0.15)]'
    }

    return 'bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.15)]'
})

function getConnectionState(): ConnectionState {
    if (typeof navigator === 'undefined') {
        return 'online'
    }

    if (!navigator.onLine) {
        return 'offline'
    }

    const connection = (navigator as Navigator & {
        connection?: {
            effectiveType?: string
            saveData?: boolean
            addEventListener?: (type: string, listener: EventListener) => void
            removeEventListener?: (type: string, listener: EventListener) => void
        }
        mozConnection?: {
            effectiveType?: string
            saveData?: boolean
            addEventListener?: (type: string, listener: EventListener) => void
            removeEventListener?: (type: string, listener: EventListener) => void
        }
        webkitConnection?: {
            effectiveType?: string
            saveData?: boolean
            addEventListener?: (type: string, listener: EventListener) => void
            removeEventListener?: (type: string, listener: EventListener) => void
        }
    }).connection
        || (navigator as Navigator & {
            mozConnection?: {
                effectiveType?: string
                saveData?: boolean
                addEventListener?: (type: string, listener: EventListener) => void
                removeEventListener?: (type: string, listener: EventListener) => void
            }
        }).mozConnection
        || (navigator as Navigator & {
            webkitConnection?: {
                effectiveType?: string
                saveData?: boolean
                addEventListener?: (type: string, listener: EventListener) => void
                removeEventListener?: (type: string, listener: EventListener) => void
            }
        }).webkitConnection

    if (connection?.saveData) {
        return 'slow'
    }

    if (['slow-2g', '2g'].includes(connection?.effectiveType || '')) {
        return 'slow'
    }

    return 'online'
}

function syncConnectionState() {
    connectionState.value = getConnectionState()
}

const connectionListeners = {
    online: syncConnectionState,
    offline: syncConnectionState,
    change: syncConnectionState,
}

onMounted(() => {
    syncConnectionState()
    window.addEventListener('online', connectionListeners.online)
    window.addEventListener('offline', connectionListeners.offline)

    const connection = (navigator as Navigator & {
        connection?: {
            addEventListener?: (type: string, listener: EventListener) => void
            removeEventListener?: (type: string, listener: EventListener) => void
        }
        mozConnection?: {
            addEventListener?: (type: string, listener: EventListener) => void
            removeEventListener?: (type: string, listener: EventListener) => void
        }
        webkitConnection?: {
            addEventListener?: (type: string, listener: EventListener) => void
            removeEventListener?: (type: string, listener: EventListener) => void
        }
    }).connection
        || (navigator as Navigator & {
            mozConnection?: {
                addEventListener?: (type: string, listener: EventListener) => void
                removeEventListener?: (type: string, listener: EventListener) => void
            }
        }).mozConnection
        || (navigator as Navigator & {
            webkitConnection?: {
                addEventListener?: (type: string, listener: EventListener) => void
                removeEventListener?: (type: string, listener: EventListener) => void
            }
        }).webkitConnection

    connection?.addEventListener?.('change', connectionListeners.change)
})

onBeforeUnmount(() => {
    window.removeEventListener('online', connectionListeners.online)
    window.removeEventListener('offline', connectionListeners.offline)

    const connection = (navigator as Navigator & {
        connection?: {
            removeEventListener?: (type: string, listener: EventListener) => void
        }
        mozConnection?: {
            removeEventListener?: (type: string, listener: EventListener) => void
        }
        webkitConnection?: {
            removeEventListener?: (type: string, listener: EventListener) => void
        }
    }).connection
        || (navigator as Navigator & {
            mozConnection?: {
                removeEventListener?: (type: string, listener: EventListener) => void
            }
        }).mozConnection
        || (navigator as Navigator & {
            webkitConnection?: {
                removeEventListener?: (type: string, listener: EventListener) => void
            }
        }).webkitConnection

    connection?.removeEventListener?.('change', connectionListeners.change)
})

// Collapsed sidebar flyout submenu state (hover or click).
const flyoutOpenKey = ref<string | null>(null)
let flyoutCloseTimer: number | null = null

function getFlyoutKey(item: any): string {
    // Parent items use url: '#', so use a stable key based on title.
    return `flyout:${String(item?.title ?? '')}`
}

function clearFlyoutCloseTimer() {
    if (flyoutCloseTimer !== null) {
        window.clearTimeout(flyoutCloseTimer)
        flyoutCloseTimer = null
    }
}

function openFlyout(key: string) {
    clearFlyoutCloseTimer()
    flyoutOpenKey.value = key
}

// The flyout stays open for as long as the pointer is over the icon or the
// panel. Leaving either only starts a short grace period, long enough to cross
// the gap between them; entering either one cancels it.
const FLYOUT_CLOSE_DELAY_MS = 250

function scheduleCloseFlyout(key: string) {
    clearFlyoutCloseTimer()
    flyoutCloseTimer = window.setTimeout(() => {
        if (flyoutOpenKey.value === key) flyoutOpenKey.value = null
    }, FLYOUT_CLOSE_DELAY_MS)
}

function closeFlyout() {
    clearFlyoutCloseTimer()
    flyoutOpenKey.value = null
}

function toggleColorMode() {
    mode.value = isDarkModeActive.value ? 'light' : 'dark'
}

function setFlyoutOpen(key: string, open: boolean) {
    clearFlyoutCloseTimer()
    flyoutOpenKey.value = open ? key : (flyoutOpenKey.value === key ? null : flyoutOpenKey.value)
}

function isInsideFlyoutKey(target: EventTarget | null, key: string): boolean {
    const el = target as HTMLElement | null
    if (!el) return false
    return Boolean(el.closest?.(`[data-flyout-key="${key}"]`))
}

function onFlyoutTriggerLeave(key: string, event: MouseEvent) {
    // If we're moving into the flyout panel, keep it open.
    if (isInsideFlyoutKey(event.relatedTarget, key)) return
    if (flyoutOpenKey.value === key) flyoutOpenKey.value = null
}

function onFlyoutContentLeave(key: string, event: MouseEvent) {
    // If we're moving back to the trigger icon button, keep it open.
    if (isInsideFlyoutKey(event.relatedTarget, key)) return
    if (flyoutOpenKey.value === key) flyoutOpenKey.value = null
}

const activeBranchId = computed<string | null>(() => (page.props.auth.user.branch_id as string | null) || null)
const activeBranchName = computed<string | null>(() => (page.props.auth.user.branch_name as string | null) || null)
const activeCompanyId = computed<string | null>(() => (page.props.activeCompanyId as string | null) || null)

// What the top bar may offer (phase 5): the user's companies, and the branches
// they may use in the active one. A business with one of each never sees a
// switcher — the plan keeps "company" and "branch" out of a small shop's way.
const switcherCompanies = computed<{ id: string, name: string }[]>(() => (page.props.switcher as any)?.companies ?? [])
const switcherBranches = computed<{ id: string, name: string }[]>(() => (page.props.switcher as any)?.branches ?? [])
// The company and branch pages (show, edit, new) are reached from here, not from
// the Administration menu: offered when there is somewhere to switch to, and to
// anyone whose role lets them see the company or its branches.
const canOpenCompanies = computed(() => switcherCompanies.value.length > 1 || isSuperAdmin.value || can('companies.view'))
const canOpenBranches = computed(() => switcherBranches.value.length > 1 || can('branches.view_any') || can('branches.create'))
const companyInitials = computed(() => (activeCompanyName.value || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase())
const activeCompanyName = computed(() => switcherCompanies.value.find((c) => c.id === activeCompanyId.value)?.name ?? '')

// A refusal that has nothing to do with the page the user lands on — a save
// from a tab left open after switching company elsewhere. The server sends
// them home, which shows no flash of its own, so the layout says why.
// After mount, not during setup: the redirect remounts this layout, and a toast
// raised before its <Sonner> exists is dropped — which is what happened.
onMounted(() => {
    watch(
        () => (page.props.flash as any)?.context_notice,
        (message) => { if (message) toast.warning(message, { duration: 10000 }) },
        { immediate: true, flush: 'post' },
    )
})

// The menu lives in lib/appModules.js, one block per module; the sidebar
// shows the block of the module the current page belongs to, under links that
// are always there. A page that is in no module (home, dashboard, the modules
// page) lists the modules themselves.
const appModules = computed(() => buildAppModules(t))

const pinnedNav = computed(() => [
    { key: 'home', pinned: true, title: t('sidebar.main.home'), url: '/home', icon: Home },
    { key: 'dashboard', title: t('sidebar.main.dashboard'), url: '/dashboard', icon: ChartNoAxesCombined },
])

const shownModules = computed(() => visibleModules(appModules.value, {
    can,
    isOff: moduleOff,
    allowedMenus: selectedSidebarMenus.value,
}))

// From the page: a link, a search result or a bookmark into another module
// brings its menu with it. Home and Dashboard belong to every module, so on
// them the menu stays the one you came from (remembered in this browser).
const LAST_MODULE_KEY = 'nextbook.last_module'
const readLastModule = () => { try { return localStorage.getItem(LAST_MODULE_KEY) } catch { return null } }
const lastModuleId = ref<string | null>(readLastModule())
const pageModule = computed(() => moduleForUrl(appModules.value, page.url))
watch(pageModule, (module: any) => {
    if (!module) return
    lastModuleId.value = module.id
    try { localStorage.setItem(LAST_MODULE_KEY, module.id) } catch { /* private window */ }
}, { immediate: true })

const currentModule = computed(() => {
    const id = pageModule.value?.id ?? lastModuleId.value
    return id ? shownModules.value.find((shown) => shown.id === id) ?? null : null
})

const navMain = computed(() => [
    ...pinnedNav.value,
    ...(currentModule.value
        ? currentModule.value.groups
        : shownModules.value.map((module) => ({
            key: `module:${module.id}`,
            pinned: true,
            title: module.title,
            url: module.firstUrl,
            icon: module.icon,
        }))),
])

const allMenuPaths = computed<string[]>(() => appModules.value
    .flatMap((module: any) => module.groups)
    .flatMap((group: any) => (group.items ? group.items.map((item: any) => item.url) : [group.url]))
    .filter((url: string) => url && url !== '#')
    .map((url: string) => normalizePath(url)))

// The menu search looks through every module, not just the open one.
const allModulesNav = computed(() => [
    ...pinnedNav.value,
    ...shownModules.value.flatMap((module) => module.groups),
])

const selectedSidebarMenus = computed<Set<string> | null>(() => {
    const menus = page.props.user_preferences?.appearance?.sidebar_menus
    if (!Array.isArray(menus)) {
        return null
    }

    const normalized = new Set(menus)
    // Legacy: 'receipt' / 'payment' were merged into the 'cash_transactions' group.
    if (normalized.has('receipt') || normalized.has('payment')) {
        normalized.add('cash_transactions')
    }
    // Legacy: the standalone 'trash' menu moved into the 'system' group.
    if (normalized.has('trash')) {
        normalized.add('system')
    }

    return normalized
})

// Modules the company has switched off (config/modules.php): their links go,
// and a group left with none goes with them.
const moduleOff = (url: string) => {
    const paths: string[] = page.props.modules?.disabled_paths ?? []
    const path = String(url || '').split('?')[0]
    return paths.some((prefix) => path === prefix || path.startsWith(`${prefix}/`))
}

const filteredNavMain = computed(() => {
    const allowedMenus = selectedSidebarMenus.value
    const source = menuSearchQueryNormalized.value ? allModulesNav.value : navMain.value

    // Module groups arrive already cut to what this user may open
    // (visibleModules); only the dashboard link can still be hidden here.
    return source.filter((item: any) => item.pinned || !allowedMenus || allowedMenus.has(item.menuKey ?? item.key))
})

const menuSearchQuery = ref('')
const menuSearchQueryNormalized = computed(() => menuSearchQuery.value.trim().toLowerCase())

// Keep only one parent menu expanded at a time (accordion behavior)
const openParentKey = ref<string | null>(null)

function getParentKey(item: any): string {
    // Parent items use url: '#', so use a stable key based on title.
    return `parent:${String(item?.title ?? '')}`
}

const activeParentKey = computed<string | null>(() => {
    const items: any[] = filteredNavMain.value as any[]
    const active = items.find((it) => Array.isArray(it?.items) && shouldExpandParent(it.items))
    return active ? getParentKey(active) : null
})

function onParentOpenChange(item: any, nextOpen: boolean) {
    const key = getParentKey(item)
    openParentKey.value = nextOpen ? key : (openParentKey.value === key ? null : openParentKey.value)
}

const searchedNavMain = computed(() => {
    const q = menuSearchQueryNormalized.value
    if (!q) return filteredNavMain.value

    return filteredNavMain.value
        .map((item: any) => {
            const itemTitle = String(item?.title ?? '').toLowerCase()

            if (!item.items) {
                return itemTitle.includes(q) ? item : null
            }

            const children = Array.isArray(item.items) ? item.items : []
            const matchingChildren = children.filter((child: any) =>
                String(child?.title ?? '').toLowerCase().includes(q),
            )

            if (itemTitle.includes(q)) {
                return item
            }

            if (matchingChildren.length > 0) {
                return { ...item, items: matchingChildren }
            }

            return null
        })
        .filter(Boolean)
})

// Initialize / sync open parent with route changes
watch(
    () => normalizePath(page.url),
    () => {
        // Prefer opening the parent that matches the current route
        openParentKey.value = activeParentKey.value
    },
    { immediate: true },
)

// When searching, auto-open the first parent in results (still only one open)
watch(
    () => menuSearchQueryNormalized.value,
    (q) => {
        if (!q) {
            openParentKey.value = activeParentKey.value
            return
        }

        const firstParent = (searchedNavMain.value as any[]).find((it) => Array.isArray(it?.items) && it.items.length)
        openParentKey.value = firstParent ? getParentKey(firstParent) : null
    },
)

// When sidebar expands back, ensure any flyout is closed.
watch(
    () => sidebarOpen.value,
    (open) => {
        if (open) closeFlyout()
    },
)

watch(
    () => page.props.user_preferences,
    (preferences) => {
        applyAppearanceTheme(preferences)

        const preferredMode = resolveDisplayColorMode(preferences)
        if (mode.value !== preferredMode) {
            mode.value = preferredMode
        }
    },
    { immediate: true },
)

// assign to data after computed is available
data.navMain = navMain.value

// Add logout method for Inertia POST
function logout() {
    // Adapt to your backend's logout URL if different
    router.post('/logout');
}
</script>

<template>
    <Toaster />
    <Sonner
        :theme="sonnerTheme"
        :position="isRTL ? 'bottom-left' : 'bottom-right'"
    />
    <PageLoadingOverlay />
    <ManualWelcomeDialog />
    <SidebarProvider v-model:open="sidebarOpen" :default-open="!props.sidebarCollapsed">
        <Sidebar v-if="!launcher" collapsible="icon" :side="sidebarSide">
            <SidebarHeader>
                <!-- The company you are working in (phase 5). Clickable only when
                     there is somewhere to go: a second company, or a super-admin,
                     whose company page is where a new one starts. -->
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" as-child :tooltip="activeCompanyName" class="mt-2">
                            <component
                                :is="canOpenCompanies ? Link : 'div'"
                                v-bind="canOpenCompanies ? { href: route('workspace.companies') } : {}"
                                class="flex w-full items-center gap-2"
                            >
                                <div class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-violet-400 text-xs font-semibold text-white">
                                    {{ companyInitials }}
                                </div>
                                <div class="grid min-w-0 flex-1 text-start text-sm leading-tight">
                                    <span dir="auto" class="truncate text-start font-semibold">{{ activeCompanyName }}</span>
                                    <span v-if="canOpenCompanies" class="truncate text-xs text-muted-foreground">{{ t('layout.workspace') }}</span>
                                </div>
                                <ChevronsUpDown v-if="canOpenCompanies" class="ms-auto size-4 shrink-0 text-muted-foreground" />
                            </component>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup>
                        <SidebarGroupLabel>{{ t('sidebar.group.menu') }}</SidebarGroupLabel>
                        <div class="px-2 pb-2 group-data-[collapsible=icon]:hidden">
                            <div class="relative">
                                <Search
                                    class="absolute top-1/2 -translate-y-1/2 size-4 text-muted-foreground text-primary"
                                    :class="isRTL ? 'right-2' : 'left-2'"
                                />
                                <input
                                    v-model="menuSearchQuery"
                                    type="text"
                                    autocomplete="off"
                                    :placeholder="t('layout.search_menu') || 'Search menu...'"
                                    class="h-9 w-full rounded-md border border-input border-primary bg-background text-sm outline-none focus:ring-2 focus:ring-ring"
                                    :class="isRTL ? 'pr-8 pl-8' : 'pl-8 pr-8'"
                                />
                                <button
                                    v-if="menuSearchQueryNormalized"
                                    type="button"
                                    class="absolute top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:bg-muted"
                                    :class="isRTL ? 'left-1' : 'right-1'"
                                    @click="menuSearchQuery = ''"
                                >
                                    <X class="size-4" />
                                    <span class="sr-only">Clear</span>
                                </button>
                            </div>
                        </div>
                        <SidebarMenu>
                        <template v-for="item in searchedNavMain" :key="`${item.title}:${menuSearchQueryNormalized}`">
                            <!-- Simple menu item without sub-items (like Dashboard) -->
                            <SidebarMenuItem v-if="!item.items" >
                                <SidebarMenuButton
                                    :tooltip="item.title"
                                    :isActive="isMenuItemActive(item.url)"
                                    as-child
                                >
                                    <Link :href="item.url" prefetch cache-for="1m">
                                        <component :is="item.icon" />
                                        <span class="group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>

                            <template v-else>
                                <!-- Collapsed (icon-only) sidebar: show submenus as a flyout on hover/click -->
                                <!-- Not modal: a modal menu disables pointer events on
                                     everything behind it, so the icon could no longer
                                     report the pointer coming back to it. -->
                                <DropdownMenu
                                    v-if="isSidebarCollapsed"
                                    :modal="false"
                                    :open="flyoutOpenKey === getFlyoutKey(item)"
                                    @update:open="(v) => setFlyoutOpen(getFlyoutKey(item), v)"
                                >
                                    <DropdownMenuTrigger as-child>
                                        <SidebarMenuButton
                                            :tooltip="item.title"
                                            :isActive="shouldExpandParent(item.items)"
                                            class="cursor-pointer"
                                            @mouseenter="openFlyout(getFlyoutKey(item))"
                                            @mouseleave="scheduleCloseFlyout(getFlyoutKey(item))"
                                        >
                                            <component :is="item.icon" />
                                            <span class="sr-only">{{ item.title }}</span>
                                        </SidebarMenuButton>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        :side="flyoutSide"
                                        align="start"
                                        :side-offset="8"
                                        class="min-w-48 rounded-lg"
                                    >
                                        <!-- Hover listeners live on this div, not on
                                             DropdownMenuContent: that component's root is a
                                             portal with no element of its own, so listeners
                                             placed on it were silently dropped and only the
                                             icon's close timer ever ran. -->
                                        <div
                                            @mouseenter="openFlyout(getFlyoutKey(item))"
                                            @mouseleave="scheduleCloseFlyout(getFlyoutKey(item))"
                                        >
                                            <DropdownMenuLabel class="text-sm font-medium">
                                                {{ item.title }}
                                            </DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                v-for="subItem in item.items"
                                                :key="subItem.title"
                                                as-child
                                                class="cursor-pointer"
                                            >
                                                <Link :href="subItem.url" prefetch cache-for="1m" class="w-full text-sm text-muted-foreground" @click="closeFlyout()">
                                                    <span>{{ subItem.title }}</span>
                                                </Link>
                                            </DropdownMenuItem>
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <!-- Expanded sidebar: keep collapsible behavior -->
                                <Collapsible
                                    v-else
                                    as-child
                                    :open="openParentKey === getParentKey(item)"
                                    class="group/collapsible"
                                    @update:open="(v) => onParentOpenChange(item, v)"
                                >
                                    <SidebarMenuItem>
                                        <CollapsibleTrigger as-child>
                                            <SidebarMenuButton
                                                :tooltip="item.title"
                                                :isActive="shouldExpandParent(item.items)"
                                            >
                                                <component :is="item.icon" />
                                                <!-- No hover:text-primary here. The button already paints
                                                     an active group with bg-primary, so a primary-coloured
                                                     label on hover was the same colour as its own
                                                     background — the name of whichever module you were in
                                                     vanished the moment you pointed at it. The button's
                                                     own hover colours handle both states. -->
                                                <span class="font-medium">
                                                    {{ item.title }}
                                                </span>
                                                <component
                                                    :is="chevronIcon"
                                                    class="ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                                />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub>
                                                <SidebarMenuSubItem
                                                    v-for="subItem in item.items"
                                                    :key="subItem.title"
                                                >
                                                    <SidebarMenuSubButton
                                                        :isActive="isMenuItemActive(subItem.url)"
                                                        as-child
                                                        class="hover:text-primary focus:text-primary focus:outline-none focus:ring-ring data-[active=true]:bg-transparent data-[active=true]:font-medium data-[active=true]:text-primary"
                                                    >
                                                        <Link :href="subItem.url" prefetch cache-for="1m">
                                                            <span>{{ subItem.title }}</span>
                                                        </Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </SidebarMenuItem>
                                </Collapsible>
                            </template>
                        </template>
                    </SidebarMenu>
                </SidebarGroup>
                <!-- Projects section commented out as it's not being used -->
                <!-- <SidebarGroup class="group-data-[collapsible=icon]:hidden">
                    <SidebarGroupLabel>Projects</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem
                            v-for="item in data.projects"
                            :key="item.name"
                        >
                            <SidebarMenuButton as-child>
                                <a :href="item.url">
                                    <component :is="item.icon" />
                                    <span>{{ item.name }}</span>
                                </a>
                            </SidebarMenuButton>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <SidebarMenuAction show-on-hover>
                                        <MoreHorizontal />
                                        <span class="sr-only">More</span>
                                    </SidebarMenuAction>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent class="w-48 rounded-lg" side="bottom" align="end">
                                    <DropdownMenuItem>
                                        <Folder class="text-muted-foreground" />
                                        <span>View Project</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem>
                                        <Forward class="text-muted-foreground" />
                                        <span>Share Project</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>
                                        <Trash2 class="text-muted-foreground" />
                                        <span>Delete Project</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup> -->
            </SidebarContent>
            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <SidebarMenuButton
                                    size="lg"
                                    class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                >
                                    <Avatar class="h-8 w-8 rounded-lg bg-primary text-primary-foreground">
                                        <AvatarImage :src="profilePhotoUrl" :alt="data.user.name" />
                                        <AvatarFallback class="rounded-lg">
                                            {{ user?.name?.charAt(0) || 'NB' }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="grid flex-1 text-start text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ data.user.name }}</span>
                                        <span class="truncate text-xs">{{ data.user.email }}</span>
                                    </div>
                                    <ChevronsUpDown class="ms-auto size-4" />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg" side="bottom" align="end" :side-offset="4">
                                <DropdownMenuLabel class="p-0 font-normal">
                                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                        <Avatar class="h-8 w-8 rounded-lg bg-primary text-primary-foreground">
                                            <AvatarImage :src="profilePhotoUrl" :alt="data.user.name" />
                                            <AvatarFallback class="rounded-lg">
                                                {{ user?.name?.charAt(0) || 'NB' }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="grid flex-1 text-start text-sm leading-tight">
                                            <span class="truncate font-semibold">{{ data.user.name }}</span>
                                                <span class="truncate text-xs">{{ data.user.email }}</span>
                                        </div>
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem>
                                        <Sparkles class="text-primary hover:text-white" />
                                        {{ t('layout.upgrade_to_pro') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('whats-new')" class="flex w-full items-center">
                                            <Rocket class="text-primary hover:text-white" />
                                            {{ t('layout.whats_new') }}
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup v-if="canOpenCompanies || canOpenBranches">
                                    <DropdownMenuItem v-if="canOpenCompanies" as-child>
                                        <Link :href="route('workspace.companies')" class="flex w-full items-center gap-2">
                                            <Building2 class="text-primary hover:text-white" />
                                            <span class="flex-1">{{ t('layout.switch_company') }}</span>
                                            <span class="max-w-[7rem] truncate text-xs text-muted-foreground">{{ activeCompanyName }}</span>
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="canOpenBranches" as-child>
                                        <Link :href="route('workspace.branches')" class="flex w-full items-center gap-2">
                                            <Store class="text-primary hover:text-white" />
                                            <span class="flex-1">{{ t('layout.switch_branch') }}</span>
                                            <span class="max-w-[7rem] truncate text-xs text-muted-foreground">{{ activeBranchName }}</span>
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator v-if="canOpenCompanies || canOpenBranches" />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('preferences.index')" class="flex w-full items-center">
                                            <Cog class="text-primary hover:text-white" />
                                            {{ t('layout.preferences') }}
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link href="/profile" class="flex w-full items-center">
                                            <BadgeCheck class="text-primary hover:text-white" />
                                            {{ t('layout.account') }}
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem>
                                        <CreditCard class="text-primary hover:text-white" />
                                        {{ t('layout.billing') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link href="/notifications" class="flex w-full items-center">
                                            <Bell class="text-violet-500 hover:text-white" />
                                            {{ t('layout.notifications') }}
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('user-manual')" class="flex w-full items-center">
                                            <BookOpen class="text-primary hover:text-white" />
                                            {{ t('layout.user_manual') }}
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="logout" class="cursor-pointer">
                                    <LogOut class="text-primary hover:text-white" />
                                    {{ t('layout.logout') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
        <SidebarInset class="flex h-svh min-h-0 flex-col overflow-hidden">
            <header :class="launcher ? 'border-transparent !bg-transparent' : ''" class="relative z-20 flex min-h-16 shrink-0 flex-wrap items-center justify-between gap-x-2 gap-y-2 border-b bg-[hsl(var(--app-chrome-background))] text-[hsl(var(--app-chrome-foreground))] px-3 py-2 transition-[width,height] ease-linear lg:h-16 lg:flex-nowrap sm:px-4 lg:py-0 rtl:pr-3 sm:rtl:pr-4 group-has-[[data-collapsible=icon]]/sidebar-wrapper:lg:h-12">
                <!-- Left: Sidebar trigger -->
                <div v-if="!launcher" class="flex shrink-0 items-center gap-2">
                    <SidebarTrigger class="-ml-1"/>
                    <Separator orientation="vertical" class="mr-2 hidden h-4 sm:block" />
                    <QuickLinks />
                    <!-- The branch you are working in; opens the branch picker when there is a second one. -->
                    <component
                        :is="canOpenBranches ? Link : 'span'"
                        v-bind="canOpenBranches ? { href: route('workspace.branches') } : {}"
                        class="hidden max-w-[200px] items-center gap-1.5 rounded-lg border border-border/60 px-2.5 py-1 text-sm text-foreground sm:inline-flex"
                        :class="canOpenBranches ? 'transition hover:border-primary/50 hover:bg-primary/5' : ''"
                        :title="activeBranchName || ''"
                    >
                        <Store class="size-4 shrink-0 text-primary" />
                        <span dir="auto" class="truncate">{{ activeBranchName || '—' }}</span>
                    </component>
                </div>

                <!-- Center: connection status + global search side by side (no overlap) -->
                <div v-if="!launcher" class="flex min-w-0 flex-1 items-center justify-center gap-2 px-1 sm:gap-3 md:order-last md:basis-full md:flex-none lg:order-none lg:basis-auto lg:flex-1 lg:px-2">
                    <div
                        v-show="connectionState !== 'online'"
                        class="hidden shrink-0 items-center md:inline-flex rtl:flex-row-reverse"
                    >
                        <div
                            class="inline-flex max-w-[140px] items-center gap-2 rounded-full border border-border/70 bg-background/90 px-2.5 py-1 text-[11px] text-muted-foreground shadow-sm backdrop-blur sm:max-w-none sm:px-3 sm:py-1.5 sm:text-xs"
                            role="status"
                            :aria-label="connectionLabel"
                        >
                            <span class="size-2 shrink-0 rounded-full" :class="connectionDotClass" aria-hidden="true" />
                            <span class="truncate font-medium text-foreground">{{ connectionLabel }}</span>
                        </div>
                    </div>
                    <div class="flex min-w-0 justify-center">
                        <GlobalSearch />
                    </div>
                </div>

                <!-- Right: actions -->
                <div class="flex shrink-0 items-center gap-2 sm:gap-3 sm:pr-4" :class="launcher ? 'ms-auto' : ''">

                    <!-- The module you are in: plain text, before its switcher. -->
                    <span
                        v-if="!launcher && currentModule"
                        class="hidden items-center gap-1.5 text-sm font-medium text-foreground sm:inline-flex"
                    >
                        <span class="size-2 rounded-full bg-gradient-to-br" :class="moduleTone(currentModule.id)" aria-hidden="true" />
                        {{ currentModule.title }}
                    </span>
                    <!-- The module list, from anywhere. -->
                    <Link
                        v-if="!launcher"
                        :href="route('modules.index')"
                        :title="t('sidebar.all_modules')"
                        :aria-label="t('sidebar.all_modules')"
                        class="inline-flex size-9 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        :class="normalizePath(page.url) === '/modules' ? 'bg-primary/10 text-primary' : ''"
                    >
                        <LayoutGrid class="size-5" />
                    </Link>

                    <NotificationDropdown />
                    <LanguageSwitcher />
                    <button
                        @click="toggleColorMode"
                        class="p-2 rounded-full bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                    >
                        <Moon v-if="isDarkModeActive" class="size-5 text-gray-800 dark:text-gray-200" />
                        <Sun v-else class="size-5 text-gray-800 dark:text-gray-200" />
                    </button>
                </div>
<!--                </div>-->
            </header>
            <div
                data-scroll-region
                class="flex min-h-0 min-w-0 flex-1 flex-col"
                :class="flush ? 'overflow-hidden' : 'gap-4 overflow-y-auto p-4 pt-4'"
            >
                <slot/>
            </div>
            <footer v-if="!launcher" class="border-t border-border/60 bg-[hsl(var(--app-chrome-background))] px-4 py-2 text-center text-[11px] text-[hsl(var(--app-chrome-foreground))]">
                {{ t('layout.footer_copyright') || 'Copyright © 2026 App Forest. All rights reserved. Nextbook v1' }}
            </footer>
        </SidebarInset>
    </SidebarProvider>
</template>
