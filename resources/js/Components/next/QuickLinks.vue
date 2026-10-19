<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { Button } from '@/Components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { PlusCircle } from 'lucide-vue-next'
import { Kbd, KbdGroup } from '@/Components/ui/kbd'

const { t } = useI18n()
const { can } = useAuth()

/**
 * Quick-create targets, in the order the menu shows them.
 *
 * `key` is the *physical* key — KeyboardEvent.code without the "Key" prefix — not
 * the character it types. On a Persian / Pashto layout the S key reports
 * event.key as a Persian letter, so matching on the character only ever worked
 * in English. Entries without a `key` are click-only and render no badge.
 *
 * Both the badge and the keyboard handler are derived from this one list, so a
 * shortcut can never be advertised without being wired up, or wired to two rows.
 */
const groups = computed(() => [
  {
    label: t('sidebar.main.sale'),
    items: [
      { label: t('sidebar.sale.sale'), url: '/sales/create', key: 'S', permission: 'sales.create' },
      { label: t('sidebar.sale.sale_order'), url: '/sale-orders/create', key: 'O', permission: 'sale_orders.create' },
      { label: t('sidebar.sale.sale_quotation'), url: '/sale-quotations/create', key: 'Q', permission: 'sale_quotations.create' },
      { label: t('sidebar.sale.sale_return'), url: '/sale-returns/create', key: 'U', permission: 'sale_returns.create' },
    ],
  },
  {
    label: t('sidebar.main.purchase'),
    items: [
      { label: t('sidebar.purchase.purchase'), url: '/purchases/create', key: 'P', permission: 'purchases.create' },
      { label: t('sidebar.purchase.purchase_order'), url: '/purchase-orders/create', permission: 'purchase_orders.create' },
      { label: t('sidebar.purchase.purchase_quotation'), url: '/purchase-quotations/create', permission: 'purchase_quotations.create' },
      { label: t('sidebar.purchase.purchase_return'), url: '/purchase-returns/create', permission: 'purchase_returns.create' },
    ],
  },
  {
    label: t('sidebar.main.ledger'),
    items: [
      { label: t('sidebar.ledger.customer'), url: '/customers/create', key: 'C', permission: 'customers.create' },
      { label: t('sidebar.ledger.supplier'), url: '/suppliers/create', key: 'V', permission: 'suppliers.create' },
    ],
  },
  {
    label: t('sidebar.main.inventory'),
    items: [
      { label: t('sidebar.inventory.item'), url: '/items/create', key: 'I', permission: 'items.create' },
      { label: t('sidebar.inventory.item_transfer'), url: '/item-transfers/create', key: 'M', permission: 'item_transfers.create' },
      { label: t('sidebar.inventory.stock_adjustment'), url: '/stock-adjustments/create', permission: 'stock_adjustment.create' },
      { label: t('sidebar.inventory.landed_costs'), url: '/landed-costs/create', permission: 'landed_costs.create' },
    ],
  },
  {
    label: t('sidebar.main.cash_transactions'),
    items: [
      { label: t('sidebar.main.receipt'), url: '/receipts/create', key: 'R', permission: 'receipts.create' },
      { label: t('sidebar.main.payment'), url: '/payments/create', key: 'Y', permission: 'payments.create' },
      { label: t('sidebar.main.expense'), url: '/expenses/create', key: 'E', permission: 'expenses.create' },
    ],
  },
  {
    label: t('sidebar.main.account'),
    items: [
      { label: t('sidebar.account.chart_of_account'), url: '/chart-of-accounts/create', key: 'A', permission: 'accounts.create' },
      { label: t('sidebar.main.transfer'), url: '/account-transfers/create', key: 'T', permission: 'account_transfers.create' },
      { label: t('sidebar.journal_entry.journal_entry'), url: '/journal-entries/create', key: 'J', permission: 'journal_entries.create' },
    ],
  },
  {
    label: t('sidebar.main.hr'),
    items: [
      { label: t('sidebar.hr.employee'), url: '/employees/create', permission: 'employees.create' },
    ],
  },
  {
    label: t('sidebar.owners.owners'),
    items: [
      { label: t('sidebar.owners.owners'), url: '/owners/create', permission: 'owners.create' },
      { label: t('sidebar.owners.drawing'), url: '/drawings/create', permission: 'drawings.create' },
    ],
  },
])

// Hide what the user cannot create rather than sending them into a 403.
const visibleGroups = computed(() =>
  groups.value
    .map((group) => ({ ...group, items: group.items.filter((item) => can(item.permission)) }))
    .filter((group) => group.items.length > 0),
)

const navigateTo = (url: string) => router.visit(url)

const shortcutRoutes = computed<Record<string, string>>(() => {
  const map: Record<string, string> = {}
  for (const group of visibleGroups.value) {
    for (const item of group.items) {
      if (item.key) map[item.key] = item.url
    }
  }
  return map
})

const handleShortcuts = (event: KeyboardEvent) => {
  if (!event.altKey || event.ctrlKey || event.metaKey) {
    return
  }

  const target = event.target
  if (
    target instanceof HTMLElement &&
    (['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName) || target.isContentEditable)
  ) {
    return
  }

  // Physical key first; event.key is the fallback for layouts that report no code.
  const physical = event.code?.startsWith('Key')
    ? event.code.slice(3)
    : String(event.key).toUpperCase()

  const match = shortcutRoutes.value[physical]
  if (!match) {
    return
  }

  event.preventDefault()
  navigateTo(match)
}

onMounted(() => window.addEventListener('keydown', handleShortcuts))
onBeforeUnmount(() => window.removeEventListener('keydown', handleShortcuts))
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="outline"
        size="icon"
        class="rounded-full bg-primary hover:bg-violet-600 hover:text-white cursor-pointer w-7 h-7"
      >
        <PlusCircle class="text-white w-7 h-7" />
      </Button>
    </DropdownMenuTrigger>

    <DropdownMenuContent class="w-64 max-h-[70vh] overflow-y-auto" align="start">
      <template v-for="(group, index) in visibleGroups" :key="group.label">
        <DropdownMenuSeparator v-if="index > 0" />
        <DropdownMenuLabel class="text-xs font-semibold text-muted-foreground">
          {{ group.label }}
        </DropdownMenuLabel>
        <DropdownMenuGroup>
          <!--
            No rtl:flex-row-reverse here. Radix stamps dir="rtl" on the portalled
            content, so `flex` already lays the row out right-to-left; reversing
            it again flipped every row back to LTR, which is why the icon and
            label sat on the left in Persian.
          -->
          <DropdownMenuItem
            v-for="item in group.items"
            :key="item.url"
            class="cursor-pointer flex items-center justify-between gap-3"
            @click="navigateTo(item.url)"
          >
            <div class="flex items-center gap-2 min-w-0">
              <PlusCircle class="w-4 h-4 shrink-0" />
              <span class="truncate">{{ item.label }}</span>
            </div>
            <KbdGroup v-if="item.key">
              <!-- Latin shortcut text stays LTR inside an RTL menu. -->
              <Kbd dir="ltr">Alt + {{ item.key }}</Kbd>
            </KbdGroup>
          </DropdownMenuItem>
        </DropdownMenuGroup>
      </template>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
