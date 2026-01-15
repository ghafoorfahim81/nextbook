<script setup lang="ts">
    import { Button } from '@/Components/ui/button'
    import { onBeforeUnmount, onMounted, ref } from 'vue'
    import {
      DropdownMenu,
      DropdownMenuContent,
      DropdownMenuGroup,
      DropdownMenuItem,
      DropdownMenuSeparator,
      DropdownMenuShortcut,
      DropdownMenuTrigger,
    } from '@/Components/ui/dropdown-menu'
    import { PlusCircle, ChartColumn, ShoppingBasket, Box, ShoppingCart, UserCog, ArrowLeftRight, Banknote, ReceiptIcon, CreditCard } from 'lucide-vue-next'
    import { useI18n } from 'vue-i18n'
    import { router } from '@inertiajs/vue3'
    import { Kbd } from '@/Components/ui/kbd'
    import { KbdGroup } from '@/Components/ui/kbd'
    const { t } = useI18n()
    const showStatusBar = ref(true)
    const showActivityBar = ref(false)
    const showPanel = ref(false)
    const navigateTo = (url) => {
      router.visit(url)
    }
    const shortcutRoutes = {
      a: '/chart-of-accounts/create',
      t: '/account-transfers/create',
      i: '/items/create',
      p: '/purchases/create',
      s: '/sales/create',
      c: '/customers/create',
      e: '/expenses/create',
      r: '/receipts/create',
    }
    const handleShortcuts = (event: KeyboardEvent) => {
      if (!event.altKey || event.ctrlKey || event.metaKey) {
        return
      }
      const target = event.target
      if (
        target instanceof HTMLElement &&
        ['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName)
      ) {
        return
      }
      const match = shortcutRoutes[event.key.toLowerCase()]
      if (!match) {
        return
      }
      event.preventDefault()
      navigateTo(match)
    }
    onMounted(() => window.addEventListener('keydown', handleShortcuts))
    onBeforeUnmount(() => window.removeEventListener('keydown', handleShortcuts))
    const position = ref('bottom')
    </script>

    <template>
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button variant="outline" size="icon" class="rounded-full bg-primary hover:bg-violet-600 hover:text-white cursor-pointer w-7 h-7">
            <PlusCircle class="text-white w-7 h-7" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-56" align="start">
          <DropdownMenuGroup>

            <DropdownMenuItem
              @click="navigateTo('/chart-of-accounts/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.account') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + A</Kbd>
                <!-- <span>+</span> -->
                <!-- <Kbd>A</Kbd> -->
              </KbdGroup>
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/account-transfers/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.transfer') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + T</Kbd>
              </KbdGroup>
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/items/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.inventory.item') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + I</Kbd>
              </KbdGroup>
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/purchases/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.purchase') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + P</Kbd>
              </KbdGroup>
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/sales/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.sale') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + S</Kbd>
              </KbdGroup>
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/customers/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.ledger.customer') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + C</Kbd>
              </KbdGroup>
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/suppliers/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.ledger.supplier') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + S</Kbd>
              </KbdGroup>
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/expenses/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.expense') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + E</Kbd>
              </KbdGroup>
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/receipts/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.receipt') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + R</Kbd>
              </KbdGroup>
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/payments/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.payment') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + P</Kbd>
              </KbdGroup>
            </DropdownMenuItem>
            <DropdownMenuItem
              @click="navigateTo('/item-transfers/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.inventory.item_transfer') }}</span>
              </div>
              <KbdGroup>
                <Kbd>Alt + I</Kbd>
              </KbdGroup>
            </DropdownMenuItem>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
        </DropdownMenuContent>
      </DropdownMenu>
    </template>
