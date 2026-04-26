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
    ChevronRight,
    ChevronLeft,
    ChevronsUpDown,
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
    FileChartLine
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
import { HousePlug, ShoppingCart, Receipt as ReceiptIcon, Home } from 'lucide-vue-next'
// @ts-ignore - Vue SFC default export shim
import NotificationDropdown from '@/Components/notifications/NotificationDropdown.vue'
import QuickLinks from '@/Components/next/QuickLinks.vue'
const { t } = useI18n()
const { locale } = useI18n()
const { user } = useAuth()
// This is sample data.
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
    return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`)
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
const props = withDefaults(defineProps<{ sidebarCollapsed?: boolean }>(), { sidebarCollapsed: false })

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
        return 'No connection'
    }

    if (connectionState.value === 'slow') {
        return 'Slow connection'
    }

    return 'Online'
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

function scheduleCloseFlyout(key: string) {
    clearFlyoutCloseTimer()
    flyoutCloseTimer = window.setTimeout(() => {
        if (flyoutOpenKey.value === key) flyoutOpenKey.value = null
    }, 2000)
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

const branches = computed(() => {
    const raw: any = page.props.branches
    if (!raw) return []

    // Support both plain arrays and Laravel resource collections ({ data: [...] }).
    if (Array.isArray(raw)) {
        return raw
    }

    if (Array.isArray(raw.data)) {
        return raw.data
    }

    return []
})
const activeBranchId = computed<string | null>(() => (page.props.auth.user.branch_id as string | null) || null)
const activeBranchName = computed<string | null>(() => (page.props.auth.user.branch_name as string | null) || null)

const selectedBranchId = ref<string | null>(activeBranchId.value)

function switchBranch() {
    if (!isSuperAdmin.value || !selectedBranchId.value || selectedBranchId.value === activeBranchId.value) {
        return
    }

    router.post('/switch-branch', { branch_id: selectedBranchId.value }, {
        // Wait until the server has actually switched the branch
        // before reloading, so the refreshed page shows the new branch.
        onSuccess: () => {
            console.log('switched branch to 111', selectedBranchId.value);
        },
    });
}

const navMain = computed(() => [
    {
        key: 'home',
        title: t('sidebar.main.home'),
        url: '/home',
        icon: Home,
    },
    {
        key: 'dashboard',
        title: t('sidebar.main.dashboard'),
        url: '/dashboard',
        icon: HousePlug,
    },
    {
        key: 'account',
        title: t('sidebar.main.account'),
        url: '#',
        icon: ChartColumn,
        items: [
            { title: t('sidebar.account.chart_of_account'), url: '/chart-of-accounts', permission: 'accounts.view_any' },
            // { title: t('sidebar.account.account_type'), url: '/account-types', permission: 'account_types.view_any' },
            { title: t('sidebar.main.transfer'), url: '/account-transfers', icon: ArrowLeftRight, permission: 'account_transfers.view_any' },
            { title: t('sidebar.journal_entry.journal_class'), url: '/journal-classes', icon: FileText, permission: 'journal_classes.view_any' },
            { title: t('sidebar.journal_entry.journal_entry'), url: '/journal-entries', icon: FileText, permission: 'journal_entries.view_any' },
        ],
    },
    {
        key: 'administration',
        title: t('sidebar.main.administration'),
        url: '#',
        icon: Cog,
        items: [
            { title: t('sidebar.administration.category'), url: '/categories', permission: 'categories.view_any' },
            { title: t('sidebar.administration.currency'), url: '/currencies', permission: 'currencies.view_any' },
            { title: t('sidebar.administration.currency_rate_update'), url: '/currency-rate-updates', permission: 'currencies.view_any' },
            { title: t('sidebar.administration.unit_measure'), url: '/unit-measures', permission: 'unit_measures.view_any' },
            { title: t('sidebar.administration.size'), url: '/sizes', permission: 'sizes.view_any' },
            { title: t('sidebar.administration.branch'), url: '/branches', permission: 'branches.view_any' },
            { title: t('sidebar.administration.brand'), url: '/brands', permission: 'brands.view_any' },
            { title: t('sidebar.administration.warehouse'), url: '/warehouses', permission: 'warehouses.view_any' },
            { title: t('sidebar.administration.company'), url: '/company', permission: 'companies.view_any' },
        ],
    },
    {
        key: 'inventory',
        title: t('sidebar.main.inventory'),
        url: '#',
        icon: Database,
        items: [
            { title: t('sidebar.inventory.item'), url: '/items', permission: 'items.view_any' },
            { title: t('sidebar.inventory.fast_entry'), url: '/item-fast-entry', permission: ['items.view_any', 'items.create'] },
            { title: t('sidebar.inventory.fast_opening'), url: '/item-fast-opening', permission: ['items.view_any', 'items.create'] },
            { title: t('sidebar.inventory.barcode_print'), url: '/item-barcode-print', permission: 'items.view_any' },
            { title: t('sidebar.inventory.item_transfer'), url: '/item-transfers', permission: 'item_transfers.view_any' },
        ],
    },
    {
        key: 'ledger',
        title: t('sidebar.main.ledger'),
        url: '#',
        icon: UserCog,
        items: [
            { title: t('sidebar.ledger.customer'), url: '/customers', permission: 'customers.view_any' },
            { title: t('sidebar.ledger.supplier'), url: '/suppliers', permission: 'suppliers.view_any' },
        ],
    },
    {
        key: 'owners',
        title: t('sidebar.owners.owners'),
        url: '#',
        icon: BookUser,
        items: [
            { title: t('sidebar.owners.owners'), url: '/owners', permission: 'owners.view_any' },
            { title: t('sidebar.owners.drawing'), url: '/drawings', permission: 'drawings.view_any' },
        ],
    },
    {
        key: 'expense',
        title: t('sidebar.main.expense'),
        url: '#',
        icon: Banknote,
        items: [
            { title: t('sidebar.expense.expense_category'), url: '/expense-categories', permission: 'expense_categories.view_any' },
            { title: t('sidebar.expense.expense'), url: '/expenses', permission: 'expenses.view_any' },
        ],
    },
    {
        key: 'purchase',
        title: t('sidebar.main.purchase'),
        url: '#',
        icon: ShoppingBasket,
        items: [
            { title: t('sidebar.purchase.purchase'), url: '/purchases', permission: 'purchases.view_any' },
            // { title: t('sidebar.purchase.purchase_return'), url: '/purchase-returns' },
        ],
    },
    {
        key: 'sale',
        title: t('sidebar.main.sale'),
        url: '#',
        icon: ShoppingCart,
        items: [
            { title: t('sidebar.sale.sale'), url: '/sales', permission: 'sales.view_any' },
            // { title: t('sidebar.sale.sale_return'), url: '/sale-returns' },
        ],
    },

    {
        key: 'receipt',
        title: t('sidebar.main.receipt'),
        url: '/receipts',
        icon: ReceiptIcon,
        permission: 'receipts.view_any',
    },
    {
        key: 'payment',
        title: t('sidebar.main.payment'),
        url: '/payments',
        icon: CreditCard,
        permission: 'payments.view_any',
    },

    {
        key: 'user_management',
        title: t('sidebar.main.user_management'),
        url: '#',
        icon: UserRound,
        items: [
            { title: t('sidebar.user_management.user'), url: '/users', permission: 'users.view_any' },
            { title: t('sidebar.user_management.role'), url: '/roles', permission: 'roles.view_any' },
        ],
    },
    {
        key: 'preferences',
        title: t('sidebar.main.preferences'),
        url: '/preferences',
        icon: Cog,
    },
    {
        key: 'reports',
        title: t('sidebar.main.reports'),
        url: '/reports',
        icon: FileChartLine,
    },
])

const selectedSidebarMenus = computed<Set<string> | null>(() => {
    const menus = page.props.user_preferences?.appearance?.sidebar_menus
    if (!Array.isArray(menus)) {
        return null
    }

    return new Set(menus)
})

const filteredNavMain = computed(() => {
    const allowedMenus = selectedSidebarMenus.value

    return navMain.value
        .map((item) => {
            // Home menu has no permissions, always show
            if (item.key === 'home') {
                return item
            }

            if (allowedMenus && item.key && !allowedMenus.has(item.key)) {
                return null
            }

            // Leaf item
            if (!item.items) {
                if (!item.permission || can(item.permission)) {
                    return item
                }
                return null
            }

            // Parent with children
            const visibleChildren = item.items.filter(
                (child) => !child.permission || can(child.permission),
            )

            if (visibleChildren.length === 0) {
                return null
            }

            return {
                ...item,
                items: visibleChildren,
            }
        })
        .filter(Boolean)
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
    <SidebarProvider v-model:open="sidebarOpen" :default-open="!props.sidebarCollapsed">
        <Sidebar collapsible="icon" :side="sidebarSide">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <!-- <SidebarMenuButton
                                    size="lg"
                                    class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                >
                                    <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                        <component :is="activeTeam.logo" class="size-4" />
                                    </div>
                                    <div class="grid flex-1 text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ activeTeam.name }}</span>
                                        <span class="truncate text-xs">{{ activeTeam.plan }}</span>
                                    </div>
                                    <ChevronsUpDown class="ml-auto" />
                                </SidebarMenuButton> -->
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                class="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                                align="start"
                                side="bottom"
                                :side-offset="4"
                            >
                                <DropdownMenuLabel class="text-xs text-muted-foreground">
                                    Teams
                                </DropdownMenuLabel>
                                <DropdownMenuItem
                                    v-for="(team, index) in data.teams"
                                    :key="team.name"
                                    class="gap-2 p-2"
                                    @click="setActiveTeam(team)"
                                >
                                    <div class="flex size-6 items-center justify-center rounded-sm border">
                                        <component :is="team.logo" class="size-4 shrink-0" />
                                    </div>
                                    {{ team.name }}
                                    <DropdownMenuShortcut>⌘{{ index + 1 }}</DropdownMenuShortcut>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem class="gap-2 p-2">
                                    <div class="flex size-6 items-center justify-center rounded-md border bg-background">
                                        <Plus class="size-4" />
                                    </div>
                                    <div class="font-medium text-muted-foreground">
                                        Add team
                                    </div>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
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
                                <DropdownMenu
                                    v-if="isSidebarCollapsed"
                                    :open="flyoutOpenKey === getFlyoutKey(item)"
                                    @update:open="(v) => setFlyoutOpen(getFlyoutKey(item), v)"
                                >
                                    <DropdownMenuTrigger as-child>
                                        <SidebarMenuButton
                                            :tooltip="item.title"
                                            :isActive="shouldExpandParent(item.items)"
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
                                        @mouseenter="openFlyout(getFlyoutKey(item))"
                                        @mouseleave="closeFlyout()"
                                    >
                                        <DropdownMenuLabel class="text-xs text-muted-foreground">
                                            {{ item.title }}
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            v-for="subItem in item.items"
                                            :key="subItem.title"
                                            as-child
                                        >
                                            <Link :href="subItem.url" prefetch cache-for="1m" class="w-full">
                                                <span>{{ subItem.title }}</span>
                                            </Link>
                                        </DropdownMenuItem>
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
                                                <span
                                                    class="hover:text-primary focus:text-primary focus:outline-none focus:ring-ring"
                                                >
                                                    {{ item.title }}
                                                </span>
                                                <component
                                                    :is="chevronIcon"
                                                    :class="isRTL ? 'mr-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90' : 'ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90'"
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
                                                        class="hover:text-primary focus:text-primary focus:outline-none focus:ring-ring data-[active=true]:bg-transparent data-[active=true]:text-primary"
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
                                        <AvatarImage :src="data.user.avatar" :alt="data.user.name" />
                                        <AvatarFallback class="rounded-lg">
                                            {{ user?.name?.charAt(0) || 'NB' }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="grid flex-1 text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ data.user.name }}</span>
                                        <span class="truncate text-xs">{{ data.user.email }}</span>
                                    </div>
                                    <ChevronsUpDown class="ml-auto size-4" />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg" side="bottom" align="end" :side-offset="4">
                                <DropdownMenuLabel class="p-0 font-normal">
                                    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                        <Avatar class="h-8 w-8 rounded-lg bg-primary text-primary-foreground">
                                            <AvatarImage :src="data.user.avatar" :alt="data.user.name" />
                                            <AvatarFallback class="rounded-lg">
                                                {{ user?.name?.charAt(0) || 'NB' }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="grid flex-1 text-left text-sm leading-tight">
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
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem>
                                        <BadgeCheck class="text-primary hover:text-white" />
                                        {{ t('layout.account') }}
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
        <SidebarInset class="flex min-h-screen flex-col">
            <header class="relative flex min-h-16 shrink-0 flex-wrap items-center justify-between gap-2 border-b bg-background/80 px-3 py-2 transition-[width,height] ease-linear supports-[backdrop-filter]:bg-background/60 sm:h-16 sm:flex-nowrap sm:px-4 sm:py-0 rtl:pr-3 sm:rtl:pr-4 group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12">
                <!-- Left side: Sidebar trigger and breadcrumb -->
                <div class="flex items-center gap-2">
                    <SidebarTrigger class="-ml-1"/>
                    <Separator orientation="vertical" class="mr-2 hidden h-4 sm:block" />
                </div>
                <div v-show="connectionLabel !='Online'" class="pointer-events-none absolute inset-x-0 top-1/2 hidden -translate-y-1/2 justify-center md:flex">
                    <div class="inline-flex items-center gap-2 rounded-full border border-border/70 bg-background/80 px-3 py-1.5 text-xs text-muted-foreground shadow-sm backdrop-blur">
                        <span
                            class="size-2 rounded-full"
                            :class="connectionDotClass"
                            aria-hidden="true"
                        />
                        <span class="font-medium text-foreground">
                            {{ connectionLabel }}
                        </span>
                    </div>
                </div>
                <div class="flex min-w-0 flex-1 items-center justify-end gap-2 sm:gap-3 sm:pr-4">

                    <QuickLinks />
                    <div v-if="isSuperAdmin" class="hidden items-center max-start gap-2 sm:flex">
                        <label class="hidden text-xs text-muted-foreground md:block" for="branch-switcher">
                            {{ t('layout.branch') || 'Branch' }}
                        </label>

                        <Select v-model="selectedBranchId" @update:modelValue="switchBranch">
                            <SelectTrigger class="h-7 w-[110px] text-xs border-input md:w-[130px]">
                                <SelectValue :placeholder="t('layout.branch')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    :value="branch.id"
                                    class="px-5 py-2 text-xs data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[highlighted]:bg-primary data-[highlighted]:text-primary-foreground rtl:pe-10 rtl:ps-3 rtl:bg-left rtl:pr-8"
                                >
                                    {{ branch.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div v-else class="hidden text-xs text-muted-foreground sm:block">
                        {{ activeBranchName || '—' }}
                    </div>
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
            <div class="flex flex-1 min-h-0 flex-col gap-4 p-4 pt-4 min-w-0">
                <slot/>
            </div>
            <footer class="border-t border-border/60 px-4 py-2 text-center text-[11px] text-muted-foreground/80">
                {{ t('layout.footer_copyright') || 'Copyright © 2026 App Forest. All rights reserved. Nextbook v1' }}
            </footer>
        </SidebarInset>
    </SidebarProvider>
</template>
