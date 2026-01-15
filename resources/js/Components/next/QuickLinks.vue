<script setup lang="ts">
    import { Button } from '@/Components/ui/button'
    import { ref } from 'vue'
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
    const { t } = useI18n()
    const showStatusBar = ref(true)
    const showActivityBar = ref(false)
    const showPanel = ref(false)
    const navigateTo = (url) => {
      router.visit(url)
    }
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
              <ChartColumn class="w-4 h-4" />
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/account-transfers/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.transfer') }}</span>
              </div>
              <ArrowLeftRight class="w-4 h-4" />
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/items/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.inventory.item') }}</span>
              </div>
              <Box class="w-4 h-4" />
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/purchases/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.purchase') }}</span>
              </div>
              <ShoppingBasket class="w-4 h-4" />
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/sales/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.sale') }}</span>
              </div>
              <ShoppingCart class="w-4 h-4" />
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/customers/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.ledger.customer') }}</span>
              </div>
              <UserCog class="w-4 h-4" />
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/suppliers/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.ledger.supplier') }}</span>
              </div>
              <UserCog class="w-4 h-4" />
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/expenses/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.expense') }}</span>
              </div>
              <Banknote class="w-4 h-4" />
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/receipts/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.receipt') }}</span>
              </div>
              <ReceiptIcon class="w-4 h-4" />
            </DropdownMenuItem>

            <DropdownMenuItem
              @click="navigateTo('/payments/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.main.payment') }}</span>
              </div>
              <CreditCard class="w-4 h-4" />
            </DropdownMenuItem>
            <DropdownMenuItem
              @click="navigateTo('/item-transfers/create')"
              class="cursor-pointer flex items-center justify-between rtl:flex-row-reverse"
            >
              <div class="flex items-center gap-2 rtl:flex-row-reverse">
                <PlusCircle class="w-4 h-4" />
                <span>{{ t('sidebar.inventory.item_transfer') }}</span>
              </div>
              <Box class="w-4 h-4" />
            </DropdownMenuItem>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
        </DropdownMenuContent>
      </DropdownMenu>
    </template>
