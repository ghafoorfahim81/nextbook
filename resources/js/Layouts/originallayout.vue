<script setup>
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
  ChevronRight,
  ChevronsUpDown,
  Command,
  CreditCard,
  GalleryVerticalEnd,
  LogOut,
  MonitorCog,
  Sparkles,
  ShoppingCart,
  ShoppingBasket,
  DatabaseZap,
BookOpenCheck,
Wallet,
HousePlug,
} from 'lucide-vue-next'
import { ref } from 'vue'
import { useColorMode } from '@vueuse/core'
import { router } from '@inertiajs/vue3'

const logout = () => {
    router.post(route('logout'));
};

// This is sample data.
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
const isActive = (url) => {
  return route().current(url.replace('/', ''));
};

const mode = useColorMode()

</script>

<template>
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
                <DropdownMenuItem @click="logout">
                  <LogOut/>
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
</template>
