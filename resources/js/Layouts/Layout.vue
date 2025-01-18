<script setup lang="ts">
import { Badge } from '@/components/ui/badge'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet'
import {
    Bell,
    CircleUser,
    Home,
    LineChart,
    Menu,
    Package,
    Package2,
    Search,
    ShoppingCart,
    Users,
    Briefcase,
    GalleryVerticalEnd,
    AudioWaveform,
    Command,
    HousePlug,
    MonitorCog,
    BookOpenCheck,
    ShoppingBasket,
    DatabaseZap,
    Wallet, ChevronRight, Sparkles, ChevronsUpDown, CreditCard, LogOut, BadgeCheck
} from 'lucide-vue-next'
import {Link} from '@inertiajs/vue3'
import {
    Sidebar, SidebarContent, SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel, SidebarHeader, SidebarInset, SidebarMenu, SidebarMenuButton,
    SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger
} from "@/Components/ui/sidebar";
const data = {
    user: {
        name: 'shadcn',
        email: 'm@example.com',
        avatar: '/avatars/shadcn.jpg',
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
    navMain: [
        {
            title: 'Dashboard',
            url: '/dashboard',
            icon: HousePlug,
        },
        {
            title: 'Control panel',
            url: '#',
            icon: MonitorCog,
            isActive: false,
            items: [
                {
                    title: 'Warehouse',
                    url: '#',
                },
                {
                    title: 'User',
                    url: '#',
                },
                {
                    title: 'Designation',
                    url: '/designations',
                },
                {
                    title: 'Department',
                    url: '#',
                },
                {
                    title: 'Financial Year',
                    url: '#',
                },
                {
                    title: 'Currency',
                    url: '#',
                },
                {
                    title: 'SalesMan',
                    url: '#',
                },
                {
                    title: 'Barcode',
                    url: '#',
                },
                {
                    title: 'Measure',
                    url: '#',
                },
            ],
        },
        {
            title: 'Master',
            url: '#',
            icon: BookOpenCheck,
            items: [
                {
                    title: 'Customer',
                    url: '#',
                },
                {
                    title: 'Supplier',
                    url: '#',
                },
                {
                    title: 'Account',
                    url: '#',
                },
                {
                    title: 'Inventory',
                    url: '#',
                },
            ],
        },
        {
            title: 'Sale',
            url: '#',
            icon: ShoppingCart,
            items: [
                {
                    title: 'Bill',
                    url: '#',
                },
                {
                    title: 'Order',
                    url: '#',
                },
                {
                    title: 'Return',
                    url: '#',
                },
                {
                    title: 'Quotation',
                    url: '#',
                },
            ],
        },
        {
            title: 'Purchase',
            url: '#',
            icon: ShoppingBasket,
            items: [
                {
                    title: 'Bill',
                    url: '#',
                },
                {
                    title: 'Order',
                    url: '#',
                },
                {
                    title: 'Return',
                    url: '#',
                },
                {
                    title: 'Quotation',
                    url: '#',
                },
            ],
        },
        {
            title: 'Stock Management',
            url: '#',
            icon: DatabaseZap,
            items: [
                {
                    title: 'Store Transfer',
                    url: '#',
                },
                {
                    title: 'Stock Recieve',
                    url: '#',
                },
                {
                    title: 'Stock Issue',
                    url: '#',
                },
            ],
        },
        {
            title: 'Account',
            url: '#',
            icon: Wallet,
            items: [
                {
                    title: 'Receipt',
                    url: '#',
                    icon: Wallet,
                },
                {
                    title: 'Payment',
                    url: '#',
                },
                {
                    title: 'Contra',
                    url: '#',
                },
            ],
        },
    ],

}
</script>

<template>
  <div class="grid min-h-screen w-full md:grid-cols-[220px_1fr] lg:grid-cols-[280px_1fr]">
    <div class="hidden border-r bg-muted/40 md:block">
      <div class="flex h-full max-h-screen flex-col gap-2">
        <div class="flex h-14 items-center border-b px-4 lg:h-[60px] lg:px-6">
          <a href="/" class="flex items-center gap-2 font-semibold">
            <Package2 class="h-6 w-6" />
            <span class="">Nextbook Workspace</span>
          </a>
          <Button variant="outline" size="icon" class="ml-auto h-8 w-8">
            <Bell class="h-4 w-4" />
            <span class="sr-only">Toggle notifications</span>
          </Button>
        </div>
<!--        <div class="flex-1">-->
<!--          <nav class="grid items-start px-2 text-sm font-medium lg:px-4">-->
<!--            <a-->
<!--              href="/"-->
<!--              class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"-->
<!--            >-->
<!--              <Home class="h-4 w-4" />-->
<!--              Dashboard-->
<!--            </a>-->
<!--            <Link-->
<!--              href="/designations"-->
<!--              :class="$page.url==='/designations' ?'bg-muted text-primary':'text-muted-foreground'"-->
<!--              class="flex items-center gap-3 rounded-lg px-3  py-2 text-muted-foreground transition-all hover:text-primary"-->
<!--            >-->
<!--              <Briefcase class="h-4 w-4" />-->
<!--              Designation-->
<!--              <Badge class="ml-auto flex h-6 w-6 shrink-0 items-center justify-center rounded-full">-->
<!--                6-->
<!--              </Badge>-->
<!--            </Link>-->
<!--            <Link-->
<!--              href="/departments"-->
<!--              :class="$page.url==='/departments' ?'bg-muted text-primary':'text-muted-foreground'"-->
<!--              class="flex items-center gap-3 rounded-lg  text-muted-foreground px-3 py-2 transition-all hover:text-primary"-->
<!--            >-->
<!--              <Package class="h-4 w-4" />-->
<!--              Department-->
<!--            </Link>-->
<!--            <a-->
<!--              href="#"-->
<!--              class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"-->
<!--            >-->
<!--              <Users class="h-4 w-4" />-->
<!--              Customers-->
<!--            </a>-->
<!--            <a-->
<!--              href="#"-->
<!--              class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"-->
<!--            >-->
<!--              <LineChart class="h-4 w-4" />-->
<!--              Analytics-->
<!--            </a>-->
<!--          </nav>-->
<!--        </div>-->
          <SidebarProvider>
              <Sidebar collapsible="icon">
                  <SidebarHeader>
                      <div class="p-4">
                          Nextbook workspace
                      </div>
                  </SidebarHeader>
                  <SidebarContent>
                      <SidebarGroup>
                          <SidebarGroupLabel>Platform</SidebarGroupLabel>
                          <SidebarMenu>
                              <Collapsible
                                  v-for="item in data.navMain"
                                  :key="item.title"
                                  as-child
                                  class="group/collapsible"
                              >
                                  <SidebarMenuItem>
                                      <CollapsibleTrigger as-child>
                                          <SidebarMenuButton :tooltip="item.title">
                                              <component :is="item.icon" />
                                              <span>{{ item.title }}</span>
                                              <ChevronRight
                                                  v-if="item.items"
                                                  class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                              />
                                          </SidebarMenuButton>
                                      </CollapsibleTrigger>
                                      <CollapsibleContent>
                                          <SidebarMenuSub>
                                              <SidebarMenuSubItem v-for="subItem in item.items" :key="subItem.title">
                                                  <SidebarMenuSubButton as-child>
                                                      <Link :href="subItem.url">
                                                          <span>{{ subItem.title }}</span>
                                                      </Link>
                                                  </SidebarMenuSubButton>
                                              </SidebarMenuSubItem>
                                          </SidebarMenuSub>
                                      </CollapsibleContent>
                                  </SidebarMenuItem>
                              </Collapsible>
                          </SidebarMenu>
                      </SidebarGroup>
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
                                      <form method="POST" @submit.prevent="logout">
                                          <DropdownMenuItem>
                                              <LogOut/>
                                              Log out
                                          </DropdownMenuItem>
                                      </form>
                                  </DropdownMenuContent>
                              </DropdownMenu>
                          </SidebarMenuItem>
                      </SidebarMenu>
                  </SidebarFooter>
                  <SidebarRail />
              </Sidebar>
              <SidebarInset>
                  <header class="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12">
                      <div class="flex items-center gap-2 px-4">
                          <SidebarTrigger class="-ml-1" />
                          <Separator orientation="vertical" class="mr-2 h-4" />
                          <Breadcrumb>
                              <BreadcrumbList>
                                  <BreadcrumbItem class="hidden md:block">
                                      <BreadcrumbLink href="#">
                                          Building Your Application
                                      </BreadcrumbLink>
                                  </BreadcrumbItem>
                                  <BreadcrumbSeparator class="hidden md:block" />
                                  <BreadcrumbItem>
                                      <BreadcrumbPage>Data Fetching</BreadcrumbPage>
                                  </BreadcrumbItem>
                              </BreadcrumbList>
                          </Breadcrumb>
                      </div>
                  </header>
                  <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
                      <article>
                          <slot />
                      </article>
                      <div class="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min" />
                  </div>
              </SidebarInset>
          </SidebarProvider>
        <div class="mt-auto p-4">
          <Card>
            <CardHeader class="p-2 pt-0 md:p-4">
              <CardTitle>Upgrade to Pro</CardTitle>
              <CardDescription>
                Unlock all features and get unlimited access to our support
                team.
              </CardDescription>
            </CardHeader>
            <CardContent class="p-2 pt-0 md:p-4 md:pt-0">
              <Button size="sm" class="w-full">
                Upgrade
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
    <div class="flex flex-col">
      <header class="flex h-14 items-center gap-4 border-b bg-muted/40 px-4 lg:h-[60px] lg:px-6">
        <Sheet>
          <SheetTrigger as-child>
            <Button
              variant="outline"
              size="icon"
              class="shrink-0 md:hidden"
            >
              <Menu class="h-5 w-5" />
              <span class="sr-only">Toggle navigation menu</span>
            </Button>
          </SheetTrigger>
          <SheetContent side="left" class="flex flex-col">
            <nav class="grid gap-2 text-lg font-medium">
              <a
                href="#"
                class="flex items-center gap-2 text-lg font-semibold"
              >
                <Package2 class="h-6 w-6" />
                <span class="sr-only">Acme Inc</span>
              </a>
              <a
                href="#"
                class="mx-[-0.65rem] flex items-center gap-4 rounded-xl px-3 py-2 text-muted-foreground hover:text-foreground"
              >
                <Home class="h-5 w-5" />
                Dashboard
              </a>
              <Link
                href="/designations"
                class="mx-[-0.65rem] flex items-center gap-4 rounded-xl bg-muted px-3 py-2 text-foreground hover:text-foreground"
              >
                <ShoppingCart class="h-5 w-5" />
                Designation
                <Badge class="ml-auto flex h-6 w-6 shrink-0 items-center justify-center rounded-full">
                  6
                </Badge>
              </Link>
              <Link
                href="#"
                class="mx-[-0.65rem] flex items-center gap-4 rounded-xl px-3 py-2 text-muted-foreground hover:text-foreground"
              >
                <Package class="h-5 w-5" />
                Department
              </Link>
              <a
                href="#"
                class="mx-[-0.65rem] flex items-center gap-4 rounded-xl px-3 py-2 text-muted-foreground hover:text-foreground"
              >
                <Users class="h-5 w-5" />
                Customers
              </a>
              <a
                href="#"
                class="mx-[-0.65rem] flex items-center gap-4 rounded-xl px-3 py-2 text-muted-foreground hover:text-foreground"
              >
                <LineChart class="h-5 w-5" />
                Analytics
              </a>
            </nav>
            <div class="mt-auto">
              <Card>
                <CardHeader>
                  <CardTitle>Upgrade to Pro</CardTitle>
                  <CardDescription>
                    Unlock all features and get unlimited access to our
                    support team.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <Button size="sm" class="w-full">
                    Upgrade
                  </Button>
                </CardContent>
              </Card>
            </div>
          </SheetContent>
        </Sheet>
        <div class="w-full flex-1">
          <form>
            <div class="relative">
              <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                type="search"
                placeholder="Search products..."
                class="w-full appearance-none bg-background pl-8 shadow-none md:w-2/3 lg:w-1/3"
              />
            </div>
          </form>
        </div>
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="secondary" size="icon" class="rounded-full">
              <CircleUser class="h-5 w-5" />
              <span class="sr-only">Toggle user menu</span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>My Account</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem>Settings</DropdownMenuItem>
            <DropdownMenuItem>Support</DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem>Logout</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </header>
      <main class="flex flex-1 flex-col gap-4 p-4 lg:gap-6 lg:p-6">
        <slot/>
      </main>
    </div>
  </div>
</template>
