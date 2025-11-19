<script setup lang=ts>
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/Components/ui/avatar'

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb'

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
    Layers, UserCog, Database
} from 'lucide-vue-next'
import { ref, computed } from 'vue'
// @ts-ignore - Vue SFC default export shim
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import { usePage } from '@inertiajs/vue3'
// @ts-ignore - Vue SFC default export shim
import Toaster from '@/Components/ui/toast/Toaster.vue'
import { HousePlug, ShoppingCart, Receipt as ReceiptIcon } from 'lucide-vue-next'
// This is sample data.
const data = {
    user: {
        name: 'shadcn',
        email: 'm@example.com',
        avatar: null as string | null,
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
const page = usePage()

function setActiveTeam(team: typeof data.teams[number]) {
    activeTeam.value = team
}

// Normalize a URL to its pathname without query or trailing slash
function normalizePath(url: string): string {
    // Inertia provides relative URLs like "/branches?search=x"; split at '?' and remove trailing '/'
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

// Function to check if a parent menu should be expanded (has active child)
function shouldExpandParent(items: any[] | undefined): boolean {
    if (!items || !Array.isArray(items)) {
        return false
    }
    return items.some(item => isMenuItemActive(item.url))
}
import { useColorMode, useCycleList } from '@vueuse/core'
import { useI18n } from 'vue-i18n'

const mode = useColorMode({
    emitAuto: true,
    modes: {
        contrast: 'dark contrast',
        cafe: 'cafe',
    },
})

const { t, locale } = useI18n()
const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))
const sidebarSide = computed(() => isRTL.value ? 'right' : 'left')
const chevronIcon = computed(() => isRTL.value ? ChevronLeft : ChevronRight)

// Allow parent pages to control initial sidebar state
const props = withDefaults(defineProps<{ sidebarCollapsed?: boolean }>(), { sidebarCollapsed: false })

const navMain = computed(() => [
    {
        title: t('sidebar.main.dashboard'),
        url: '/dashboard',
        icon: HousePlug,
    },
    {
        title: t('sidebar.main.administration'),
        url: '#',
        icon: Cog,
        items: [
            { title: t('sidebar.administration.category'), url: '/categories' },
            { title: t('sidebar.administration.currency'), url: '/currencies' },
            { title: t('sidebar.administration.unit_measure'), url: '/unit-measures' },
            { title: t('sidebar.administration.branch'), url: '/branches' },
            { title: t('sidebar.administration.brand'), url: '/brands' },
            { title: t('sidebar.administration.store'), url: '/stores' },
            { title: t('sidebar.administration.company'), url: '/company' },
        ],
    },
    {
        title: t('sidebar.main.inventory'),
        url: '#',
        icon: Database,
        items: [
            { title: t('sidebar.inventory.item'), url: '/items' },
            { title: t('sidebar.inventory.fast_entry'), url: '/item-fast-entry' },
            { title: t('sidebar.inventory.fast_opening'), url: '/item-fast-opening' },
        ],
    },
    {
        title: t('sidebar.main.ledger'),
        url: '#',
        icon: UserCog,
        items: [
            { title: t('sidebar.ledger.customer'), url: '/customers' },
            { title: t('sidebar.ledger.supplier'), url: '/suppliers' },
        ],
    },
    {
        title: t('sidebar.owners.owners'),
        url: '#',
        icon: UserCog,
        items: [
            { title: t('sidebar.owners.owners'), url: '/owners' },
            { title: t('sidebar.owners.drawing'), url: '/drawing' },
        ],
    },
    {
        title: t('sidebar.main.account'),
        url: '#',
        icon: ChartColumn,
        items: [
            { title: t('sidebar.account.chart_of_account'), url: '/chart-of-accounts' },
            { title: t('sidebar.account.account_type'), url: '/account-types' },
        ],
    },
    {
        title: t('sidebar.main.purchase'),
        url: '#',
        icon: ChartColumn,
        items: [
            { title: t('sidebar.purchase.purchase'), url: '/purchases' },
            // { title: t('sidebar.purchase.purchase_return'), url: '/purchase-returns' },
        ],
    },
    {
        title: t('sidebar.main.sale'),
        url: '#',
        icon: ShoppingCart,
        items: [
            { title: t('sidebar.sale.sale'), url: '/sales' },
            // { title: t('sidebar.sale.sale_return'), url: '/sale-returns' },
        ],
    },

    {
        title: t('sidebar.main.receipt'),
        url: '/receipts',
        icon: ReceiptIcon,
    },
    {
        title: t('sidebar.main.payment'),
        url: '/payments',
        icon: CreditCard,
    },
    {
        title: t('sidebar.main.transfer'),
        url: '/account-transfers',
        icon: CreditCard,
    },

    {
        title: t('sidebar.main.user_management'),
        url: '#',
        icon: UserCog,
        items: [
            { title: t('sidebar.user_management.user'), url: '/users' },
            { title: t('sidebar.user_management.role'), url: '/roles' }, 
        ],
    },
])

// assign to data after computed is available
data.navMain = navMain.value


</script>

<template>
    <Toaster />
    <SidebarProvider :default-open="!props.sidebarCollapsed">
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
                    <SidebarMenu>
                        <template v-for="item in navMain" :key="item.title">
                            <!-- Simple menu item without sub-items (like Dashboard) -->
                            <SidebarMenuItem v-if="!item.items" >
                                <SidebarMenuButton
                                    :isActive="isMenuItemActive(item.url)"
                                    as-child
                                >
                                    <Link :href="item.url" >
                                        <component :is="item.icon" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>

                            <!-- Collapsible menu item with sub-items -->
                            <Collapsible
                                v-else
                                as-child
                                :default-open="shouldExpandParent(item.items)"
                                class="group/collapsible"
                            >
                                <SidebarMenuItem>
                                    <CollapsibleTrigger as-child>
                                        <SidebarMenuButton :tooltip="item.title">
                                            <component :is="item.icon" />
                                            <span
                                            class="hover:text-violet-500 focus:text-violet-500 focus:outline-none focus:ring-violet-500"
                                            >{{ item.title }}</span>
                                            <component :is="chevronIcon" :class="isRTL ? 'mr-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90' : 'ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90'" />
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
                                                    class="hover:text-violet-500 focus:text-violet-500 focus:outline-none focus:ring-violet-500"
                                                >
                                                    <Link :href="subItem.url">
                                                        <span>{{ subItem.title }}</span>
                                                    </Link>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
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
                                    <Avatar class="h-8 w-8 rounded-lg">
                                        <AvatarImage :src="data.user.avatar" :alt="data.user.name" />
                                        <AvatarFallback class="rounded-lg">
                                            CN
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
                                        <Avatar class="h-8 w-8 rounded-lg">
                                            <AvatarImage :src="data.user.avatar" :alt="data.user.name" />
                                            <AvatarFallback class="rounded-lg">
                                                CN
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
                                        <Sparkles />
                                        Upgrade to Pro
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem>
                                        <BadgeCheck />
                                        Account
                                    </DropdownMenuItem>
                                    <DropdownMenuItem>
                                        <CreditCard />
                                        Billing
                                    </DropdownMenuItem>
                                    <DropdownMenuItem>
                                        <Bell />
                                        Notifications
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem>
                                    <LogOut />
                                    Log out
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
        <SidebarInset>
            <header class="flex h-16 shrink-0 items-center justify-between gap-2 px-4 rtl:pr-4 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 border-b bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <!-- Left side: Sidebar trigger and breadcrumb -->
                <div class="flex items-center gap-2">
                    <SidebarTrigger class="-ml-1"/>
                    <Separator orientation="vertical" class="mr-2 h-4" />
                </div>
                    <div class="flex items-center gap-2 pr-4">
                        <LanguageSwitcher />
                        <button
                            @click="mode = mode === 'dark' ? 'light' : 'dark'"
                            class="p-2 rounded-full bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        >
                            <Moon v-if="mode === 'dark'" class="size-5 text-gray-800 dark:text-gray-200" />
                            <Sun v-else class="size-5 text-gray-800 dark:text-gray-200" />
                        </button>
                    </div>
<!--                </div>-->
            </header>
            <div class="flex flex-1 flex-col gap-4 p-4 pt-4 min-w-0">
                <slot/>
            </div>
        </SidebarInset>
    </SidebarProvider>
</template>
