<script setup>
import { computed, ref } from 'vue'
import AppLayout from '@/Layouts/Layout.vue'
import { Input } from '@/Components/ui/input'
import { Card, CardContent } from '@/Components/ui/card'
import { Landmark, Warehouse, ClipboardList, LineChart, Star } from 'lucide-vue-next'

const search = ref('')
const activeTab = ref('account')

const reportTabs = [
  {
    id: 'account',
    label: 'ACCOUNT',
    icon: Landmark,
    accent: 'bg-sky-500',
    text: 'text-sky-600',
  },
  {
    id: 'inventory',
    label: 'INVENTORY',
    icon: Warehouse,
    accent: 'bg-emerald-500',
    text: 'text-emerald-600',
  },
  {
    id: 'transaction',
    label: 'TRANSACTION REPORTS',
    icon: ClipboardList,
    accent: 'bg-orange-500',
    text: 'text-orange-600',
  },
  {
    id: 'management',
    label: 'MANAGEMENT REPORT',
    icon: LineChart,
    accent: 'bg-emerald-700',
    text: 'text-emerald-700',
  },
]

const reportData = {
  account: {
    summary: [
      'Leger',
      'Cash/Bank',
      'Day Book',
      'Group Summary',
    ],
    sections: [
      {
        title: 'Leger',
        items: [
          'Whole Standing',
          'Party Wise',
          'M.R Wise',
          'Salesman Wise',
          'Delivery Wise',
          'Route Wise',
          'Area Wise',
          'Station Wise',
          'Depreciation',
        ],
      },
      {
        title: 'Entry Book',
        items: [
          'Day Book',
          'Sales Book',
          'Purchase Book',
          'Receipt Book',
          'Payment Book',
          'Contra Book',
          'Journal Book',
          'Account',
          'Customer',
          'Supplier',
        ],
      },
      {
        title: 'Books Of Account',
        items: [
          'Receipt',
          'Payments',
          'Contra',
        ],
      },
    ],
  },
  inventory: {
    summary: [
      'Current Stock',
      'Filtered Stock',
      'Batch Stock',
      'Dump Stock',
      'Stack Valuation',
      'Negative Stock',
      'Minimum Level Stock',
      'Maximum Level Stock',
      'Near Expiry Stock',
      'Expiry Stock',
      'Fast Moving',
      'Slow Moving',
      'Stock & Sales Analysis',
      'Today Sales-Purchase & Closing Stock',
    ],
    sections: [],
  },
  transaction: {
    summary: [
      'Sales Reports',
      'Purchase Reports',
      'Stock',
      'Supplier',
    ],
    sections: [
      {
        title: 'Sales Reports',
        items: [
          'Sales - Register(All)',
          'Pending Orders',
          'Sale Quotation',
          'Sales - Party Wise',
          'Sales Book',
          'Sale Return',
          'Batch Wise Sales',
          'Sample Free',
          'Operator Sale',
          'Item Wise Discount',
          'Party Wise Discount',
          'Bill Wise Discount',
        ],
      },
      {
        title: 'Purchase Reports',
        items: [
          'Purchase - Register(All)',
          'Pending Orders',
          'Purchase Quotation',
          'Purchase - Party Wise',
          'Purchase Book',
          'Purchase Return',
          'Batch Wise Purchases',
          'Sample Free',
          'Operator Purchase',
          'Item Wise Discount',
          'Party Wise Discount',
          'Bill Wise Discount',
        ],
      },
      {
        title: '',
        items: [],
        empty: true,
      },
    ],
  },
  management: {
    summary: ['Utilities'],
    sections: [
      {
        title: 'Utilities',
        items: [
          'Deleted Sales',
          'Deleted Purchases',
          'Deleted Receipts',
          'Deleted Payments',
          'Deleted Contras',
        ],
      },
      {
        title: 'Final Reports',
        items: ['Expenses', 'Profit&Lost', 'Trial Balance'],
      },
      {
        title: '',
        items: [],
        empty: true,
      },
    ],
  },
}

const filteredSummary = computed(() => {
  const data = reportData[activeTab.value]
  const query = search.value.trim().toLowerCase()
  if (!query) return data.summary
  return data.summary.filter((item) => item.toLowerCase().includes(query))
})

const filteredSections = computed(() => {
  const data = reportData[activeTab.value]
  const query = search.value.trim().toLowerCase()
  if (!query) return data.sections

  return data.sections
    .map((section) => {
      const matchesTitle = section.title.toLowerCase().includes(query)
      const filteredItems = section.items.filter((item) =>
        item.toLowerCase().includes(query),
      )
      if (matchesTitle || filteredItems.length) {
        return { ...section, items: matchesTitle ? section.items : filteredItems }
      }
      return null
    })
    .filter(Boolean)
})
</script>

<template>
  <AppLayout title="Reports">
    <div class="container mx-auto py-6 space-y-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap items-center gap-4">
          <button
            v-for="tab in reportTabs"
            :key="tab.id"
            type="button"
            class="flex items-center gap-3 rounded-full px-3 py-2 transition"
            :class="activeTab === tab.id ? 'bg-white shadow' : 'text-muted-foreground'"
            @click="activeTab = tab.id"
          >
            <span
              class="inline-flex h-10 w-10 items-center justify-center rounded-full text-white"
              :class="tab.accent"
            >
              <component :is="tab.icon" class="h-5 w-5" />
            </span>
            <span class="text-sm font-semibold" :class="tab.text">{{ tab.label }}</span>
          </button>
        </div>
        <div class="w-full max-w-xs lg:max-w-sm">
          <Input v-model="search" placeholder="Search Here..." />
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <Card v-for="item in filteredSummary" :key="item">
          <CardContent class="flex items-center gap-3 py-4">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-white shadow">
              <Star class="h-4 w-4" />
            </span>
            <span class="text-base font-medium text-slate-700">{{ item }}</span>
          </CardContent>
        </Card>
      </div>

      <div
        v-if="filteredSections.length"
        class="grid gap-4 lg:grid-cols-3"
      >
        <Card v-for="section in filteredSections" :key="section.title">
          <CardContent class="space-y-4 py-4">
            <div class="flex items-center gap-3">
              <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-white shadow">
                <Star class="h-4 w-4" />
              </span>
              <span class="text-base font-medium text-slate-700">{{ section.title }}</span>
            </div>
            <ul v-if="section.items.length" class="space-y-3 text-sm text-muted-foreground">
              <li v-for="item in section.items" :key="item" class="flex items-center gap-3">
                <Star class="h-3 w-3 text-blue-500" />
                <span>{{ item }}</span>
              </li>
            </ul>
            <div v-else-if="section.empty" class="h-52" />
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
