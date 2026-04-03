<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import BarcodeLabel from '@/Components/inventory/BarcodeLabel.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { usePage } from '@inertiajs/vue3'
import { AlertCircle, Barcode, Eye, Printer, Trash2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'

const { t } = useI18n()
const page = usePage()
const MAX_TOTAL_LABELS = 100

const selectedItem = ref(null)
const printArea = ref(null)
const selectedItems = ref([])
const selectedSizeId = ref('36mm')

const sizeOptions = [
  {
    id: '36mm',
    nameKey: 'item.barcode_size_small',
    widthMm: 36,
    heightMm: 22,
    paddingMm: 2,
    barcodeHeightPx: 26,
    svgHeightPx: 28,
    barWidth: 1.05,
    titleFontPx: 10,
    textFontPx: 9,
  },
  {
    id: '48mm',
    nameKey: 'item.barcode_size_medium',
    widthMm: 48,
    heightMm: 28,
    paddingMm: 2.5,
    barcodeHeightPx: 34,
    svgHeightPx: 36,
    barWidth: 1.3,
    titleFontPx: 11,
    textFontPx: 10,
  },
  {
    id: '62mm',
    nameKey: 'item.barcode_size_large',
    widthMm: 62,
    heightMm: 34,
    paddingMm: 3,
    barcodeHeightPx: 42,
    svgHeightPx: 44,
    barWidth: 1.55,
    titleFontPx: 12,
    textFontPx: 11,
  },
]

const printOptions = ref({
  productName: true,
  code: true,
  price: true,
})

const selectedSize = computed(() =>
  sizeOptions.find((option) => option.id === selectedSizeId.value) ?? sizeOptions[0],
)

const decimalPlaces = computed(() =>
  Number(page.props?.user_preferences?.appearance?.decimal_places ?? 2),
)

const showCurrencySymbol = computed(() =>
  Boolean(page.props?.user_preferences?.display?.show_currency_symbol ?? true),
)

const currencySymbol = computed(() =>
  showCurrencySymbol.value ? String(page.props?.homeCurrency?.symbol ?? '') : '',
)

function sanitizeQuantity(value) {
  const numericValue = Number(value)
  return Number.isFinite(numericValue) && numericValue > 0 ? Math.round(numericValue) : 1
}

function getTotalQuantity(excludedItemId = null) {
  return selectedItems.value.reduce((total, item) => {
    if (item.id === excludedItemId) {
      return total
    }

    return total + sanitizeQuantity(item.quantity)
  }, 0)
}

const labelInstances = computed(() =>
  selectedItems.value.flatMap((item) => {
    const quantity = sanitizeQuantity(item.quantity)
    return Array.from({ length: quantity }, (_, index) => ({
      key: `${item.id}-${index}`,
      item,
    }))
  }),
)

const totalLabels = computed(() => getTotalQuantity())

const remainingLabels = computed(() => Math.max(0, MAX_TOTAL_LABELS - totalLabels.value))

const previewGridStyle = computed(() => ({
  display: 'grid',
  gridTemplateColumns: `repeat(auto-fill, minmax(${selectedSize.value.widthMm}mm, ${selectedSize.value.widthMm}mm))`,
  gap: '4mm',
  justifyContent: 'start',
  alignContent: 'start',
}))

function showLimitError() {
  toast.error(t('item.barcode_total_limit_error', { limit: MAX_TOTAL_LABELS }))
}

function getAllowedQuantity(itemId) {
  return Math.max(1, MAX_TOTAL_LABELS - getTotalQuantity(itemId))
}

function updateQuantity(item, value, { notify = false } = {}) {
  const requestedQuantity = sanitizeQuantity(value)
  const allowedQuantity = getAllowedQuantity(item.id)
  const nextQuantity = Math.min(requestedQuantity, allowedQuantity)

  item.quantity = nextQuantity

  if (notify && requestedQuantity > allowedQuantity) {
    showLimitError()
  }
}

function addItem(option) {
  if (!option) return

  const barcode = String(option.barcode ?? '').trim()
  if (!barcode) {
    toast.error(t('item.barcode_not_available'))
    selectedItem.value = null
    return
  }

  if (totalLabels.value >= MAX_TOTAL_LABELS) {
    showLimitError()
    selectedItem.value = null
    return
  }

  const existing = selectedItems.value.find((item) => item.id === option.id)
  if (existing) {
    updateQuantity(existing, sanitizeQuantity(existing.quantity) + 1, { notify: true })
  } else {
    selectedItems.value.push({
      id: option.id,
      name: option.name,
      code: option.code,
      barcode,
      sale_price: option.sale_price ?? 0,
      quantity: 1,
    })
  }

  selectedItem.value = null
}

function normalizeQuantity(item) {
  updateQuantity(item, item.quantity, { notify: true })
}

function removeItem(itemId) {
  selectedItems.value = selectedItems.value.filter((item) => item.id !== itemId)
}

function printLabels() {
  if (!labelInstances.value.length || !printArea.value) {
    toast.error(t('item.no_items_selected'))
    return
  }

  if (totalLabels.value > MAX_TOTAL_LABELS) {
    showLimitError()
    return
  }

  const popup = window.open('', '_blank', 'width=1000,height=800')
  if (!popup) {
    toast.error(t('general.error'))
    return
  }

  popup.document.write(`<!DOCTYPE html>
<html>
  <head>
    <title>${t('item.print_barcode')}</title>
    <style>
      @page {
        margin: 8mm;
      }
      * {
        box-sizing: border-box;
      }
      body {
        margin: 0;
        padding: 8mm;
        font-family: Poppins, Arial, sans-serif;
        background: white;
        color: black;
      }
      .barcode-print-sheet {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(${selectedSize.value.widthMm}mm, ${selectedSize.value.widthMm}mm));
        gap: 4mm;
        justify-content: start;
        align-content: start;
      }
      .barcode-label {
        page-break-inside: avoid;
        break-inside: avoid;
      }
      @media print {
        body {
          padding: 0;
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }
      }
    </style>
  </head>
  <body>${printArea.value.innerHTML}</body>
</html>`)
  popup.document.close()
  popup.focus()

  window.setTimeout(() => {
    popup.print()
    popup.close()
  }, 250)
}
</script>

<template>
  <AppLayout :title="t('item.print_barcode')">
    <div class="container mx-auto space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <Card>
        <CardHeader>
          <div class="flex items-start gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <Barcode class="size-5" />
            </div>
            <div>
              <CardTitle>{{ t('item.print_barcode') }}</CardTitle>
              <CardDescription>{{ t('item.print_barcode_description') }}</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="grid gap-6 lg:grid-cols-[1.25fr_0.75fr]">
            <div class="space-y-6">
              <div class="space-y-2">
                <Label>{{ t('item.add_product') }}</Label>
                <NextSelect
                  v-model="selectedItem"
                  :options="[]"
                  :reduce="(option) => option"
                  label-key="name"
                  value-key="id"
                  :searchable="true"
                  resource-type="items"
                  :search-fields="['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search']"
                  :search-options="{ limit: 25 }"
                  :floating-text="t('item.add_product')"
                  :placeholder="t('item.add_product_placeholder')"
                  :has-add-button="false"
                  append-to-body
                  @update:modelValue="addItem"
                />
                <p class="text-sm text-muted-foreground">
                  {{ t('item.no_barcode_items') }}
                </p>
              </div>

              <Alert
                :variant="remainingLabels === 0 ? 'destructive' : 'default'"
                class="flex items-start gap-3 [&>svg]:static [&>svg]:left-auto [&>svg]:top-auto [&>svg~*]:pl-0"
              >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                <div class="space-y-1">
                  <AlertTitle class="mb-0">
                    {{ t('item.barcode_total_limit_title', { limit: MAX_TOTAL_LABELS }) }}
                  </AlertTitle>
                  <AlertDescription>
                    {{ t('item.barcode_total_limit_description', { limit: MAX_TOTAL_LABELS, remaining: remainingLabels }) }}
                  </AlertDescription>
                </div>
              </Alert>

              <div class="overflow-hidden rounded-xl border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{{ t('general.name') }}</TableHead>
                      <TableHead>{{ t('item.code') }}</TableHead>
                      <TableHead>{{ t('item.quantity_per_item') }}</TableHead>
                      <TableHead class="w-[88px] text-right">{{ t('general.actions') }}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-if="!selectedItems.length">
                      <TableCell colspan="4" class="py-8 text-center text-muted-foreground">
                        {{ t('item.no_items_selected') }}
                      </TableCell>
                    </TableRow>
                    <TableRow v-for="item in selectedItems" :key="item.id">
                      <TableCell class="font-medium">{{ item.name }}</TableCell>
                      <TableCell>{{ item.code }}</TableCell>
                      <TableCell>
                        <Input
                          :model-value="item.quantity"
                          type="number"
                          min="1"
                          :max="getAllowedQuantity(item.id)"
                          step="1"
                          class="h-9 max-w-28"
                          @update:model-value="(value) => updateQuantity(item, value, { notify: true })"
                          @blur="normalizeQuantity(item)"
                        />
                      </TableCell>
                      <TableCell class="text-right">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          class="text-destructive hover:text-destructive"
                          @click="removeItem(item.id)"
                        >
                          <Trash2 class="size-4" />
                          <span class="sr-only">{{ t('item.remove_item') }}</span>
                        </Button>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
            </div>

            <div class="space-y-6">
              <Card class="border-dashed">
                <CardHeader>
                  <CardTitle class="text-base">{{ t('item.printable_fields') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                  <div class="flex items-center gap-3">
                    <Checkbox
                      id="barcode-print-name"
                      :checked="printOptions.productName"
                      @update:checked="(value) => (printOptions.productName = Boolean(value))"
                    />
                    <Label for="barcode-print-name">{{ t('item.include_product_name') }}</Label>
                  </div>
                  <div class="flex items-center gap-3">
                    <Checkbox
                      id="barcode-print-code"
                      :checked="printOptions.code"
                      @update:checked="(value) => (printOptions.code = Boolean(value))"
                    />
                    <Label for="barcode-print-code">{{ t('item.include_code') }}</Label>
                  </div>
                  <div class="flex items-center gap-3">
                    <Checkbox
                      id="barcode-print-price"
                      :checked="printOptions.price"
                      @update:checked="(value) => (printOptions.price = Boolean(value))"
                    />
                    <Label for="barcode-print-price">{{ t('item.include_price') }}</Label>
                  </div>
                </CardContent>
              </Card>

              <Card class="border-dashed">
                <CardHeader>
                  <CardTitle class="text-base">{{ t('item.barcode_size') }}</CardTitle>
                </CardHeader>
                <CardContent>
                  <Select v-model="selectedSizeId">
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="option in sizeOptions"
                        :key="option.id"
                        :value="option.id"
                      >
                        {{ t(option.nameKey) }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </CardContent>
              </Card>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-start gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <Eye class="size-5" />
            </div>
            <div>
              <CardTitle>{{ t('item.preview') }}</CardTitle>
              <CardDescription>{{ t('item.preview_description') }}</CardDescription>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-sm text-muted-foreground">
              {{ t('item.labels_ready_with_limit', { count: totalLabels, limit: MAX_TOTAL_LABELS }) }}
            </span>
            <Button type="button" :disabled="!labelInstances.length || totalLabels > MAX_TOTAL_LABELS" @click="printLabels">
              <Printer class="mr-2 size-4" />
              {{ t('general.print') }}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div
            v-if="labelInstances.length"
            ref="printArea"
            class="barcode-print-sheet rounded-xl border bg-muted/20 p-4"
            :style="previewGridStyle"
          >
            <BarcodeLabel
              v-for="label in labelInstances"
              :key="label.key"
              :item="label.item"
              :size="selectedSize"
              :show-name="printOptions.productName"
              :show-code="printOptions.code"
              :show-price="printOptions.price"
              :currency-symbol="currencySymbol"
              :decimal-places="decimalPlaces"
            />
          </div>
          <div
            v-else
            class="flex min-h-48 flex-col items-center justify-center rounded-xl border border-dashed bg-muted/20 px-6 text-center"
          >
            <Barcode class="mb-3 size-10 text-muted-foreground" />
            <p class="font-medium">{{ t('item.no_items_selected') }}</p>
            <p class="mt-2 max-w-md text-sm text-muted-foreground">
              {{ t('item.preview_description') }}
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
