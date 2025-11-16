<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import {
  Package, Hash, Pill, Box, Tag, Layers, TrendingUp, TrendingDown,
  DollarSign, Palette, Ruler, Image, MapPin, Barcode, Search,
  Building, Target
} from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, required: true },
})
console.log('this is item data', props.item)
const emit = defineEmits(['update:modelValue'])

const activeTab = ref('in')
const inRecords = ref([])
const outRecords = ref([])
const inPage = ref(1)
const outPage = ref(1)
const inHasMore = ref(true)
const outHasMore = ref(true)
const loading = ref(false)

const currentRecords = computed(() =>
  activeTab.value === 'in' ? inRecords.value : outRecords.value
)

const itemDetails = computed(() => [
  { label: 'Name', value: props.item?.name, icon: Package },
  { label: 'Code', value: props.item?.code, icon: Hash },
  { label: 'Generic Name', value: props.item?.generic_name, icon: Pill },
  { label: 'Packing', value: props.item?.packing, icon: Box },
  { label: 'Barcode', value: props.item?.barcode, icon: Barcode },
  { label: 'Unit Measure', value: props.item?.measure, icon: Ruler },
  { label: 'Brand', value: props.item?.brand_name, icon: Tag },
  { label: 'Category', value: props.item?.category, icon: Layers },
  { label: 'Minimum Stock', value: props.item?.minimum_stock, icon: TrendingDown },
  { label: 'Maximum Stock', value: props.item?.maximum_stock, icon: TrendingUp },
  { label: 'Current Stock', value: props.item?.quantity, icon: Target },
  { label: 'Colors', value: props.item?.colors, icon: Palette },
  { label: 'Size', value: props.item?.size, icon: Ruler },
  { label: 'Purchase Price', value: props.item?.purchase_price, icon: DollarSign },
  { label: 'Cost', value: props.item?.cost, icon: DollarSign },
  { label: 'MRP Rate', value: props.item?.mrp_rate, icon: DollarSign },
  { label: 'Rate A', value: props.item?.rate_a, icon: DollarSign },
  { label: 'Rate B', value: props.item?.rate_b, icon: DollarSign },
  { label: 'Rate C', value: props.item?.rate_c, icon: DollarSign },
  { label: 'Rack No', value: props.item?.rack_no, icon: MapPin },
  { label: 'Fast Search', value: props.item?.fast_search, icon: Search },
])

const close = () => emit('update:modelValue', false)

const resetState = () => {
  inRecords.value = []
  outRecords.value = []
  inPage.value = 1
  outPage.value = 1
  inHasMore.value = true
  outHasMore.value = true
  activeTab.value = 'in'
}
onMounted(() => {
  if (props.modelValue) {
    resetState()
    loadMore()
  }
})
const fetchRecords = async (type, page) => {
  const res = await axios.get(`/items/${props.item.id}/${type}-records`, {
    params: { page, per_page: 10 },
  })
  console.log('this is res', res.data)
  return {
    data: res.data.data || [],
    hasMore: res.data.meta?.current_page < res.data.meta?.last_page,
  }
}

const loadMore = async () => {
  if (!props.modelValue || loading.value) return
  const isIn = activeTab.value === 'in'
  if (isIn ? !inHasMore.value : !outHasMore.value) return

  loading.value = true
  const page = isIn ? inPage.value : outPage.value
  try {
    const { data, hasMore } = await fetchRecords(activeTab.value, page)
    if (isIn) {
      inRecords.value.push(...data)
      inPage.value++
      inHasMore.value = hasMore
    } else {
      outRecords.value.push(...data)
      outPage.value++
      outHasMore.value = hasMore
    }
  } finally {
    loading.value = false
  }
}

const onScroll = (e) => {
  const el = e.target
  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 40) loadMore()
}

const switchTab = (tab) => {
  if (activeTab.value === tab) return
  activeTab.value = tab
  if (tab === 'in' && inRecords.value.length === 0) loadMore()
  if (tab === 'out' && outRecords.value.length === 0) loadMore()
}

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      resetState()
      loadMore()
    }
  }
)
</script>

<template>
  <teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
    >
      <div
        class="w-full max-w-5xl  bg-white rounded-2xl shadow-2xl  flex flex-col max-h-[80vh] overflow-y-auto"
      >
        <!-- Item header -->
        <div
          class="border-b border-slate-200 bg-gradient-to-r from-violet-50 to-slate-50 px-6 py-4 flex justify-between items-center"
        >
          <div class="flex items-center gap-3">
            <div class="bg-violet-500 text-white p-3 rounded-lg">
              <Package class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-slate-900">
                {{ item.name }}
              </h2>
              <p class="text-xs text-slate-500 mt-1">
                {{ item.code }}
              </p>
            </div>
          </div>
          <button
            class="text-slate-400 hover:text-slate-600 transition text-2xl"
            @click="close"
          >
            ✕
          </button>
        </div>

        <!-- Info Section -->
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
          <div class="flex items-center gap-2 mb-4">
            <div class="bg-violet-500 text-white p-1.5 rounded">
              <Layers class="w-4 h-4" />
            </div>
            <h3 class="text-sm font-semibold text-slate-700">Info</h3>
          </div>

          <div class="grid gap-x-6 gap-y-3 grid-cols-2 sm:grid-cols-3">
            <div v-for="detail in itemDetails" :key="detail.label" class="flex items-start gap-2">
              <component
                :is="detail.icon"
                class="w-4 h-4 text-slate-400 mt-0.5 flex-shrink-0"
              />
              <div class="flex-1 min-w-0">
                <p class="text-xs text-slate-500">
                  {{ detail.label }}
                </p>
                <p class="text-sm font-medium text-slate-900 truncate">
                  {{ detail.value ?? '—' }}
                </p>
              </div>
            </div>
          </div>
          <hr class="my-4 border-slate-200" />
          <div class="grid gap-x-6 gap-y-3 grid-cols-2 sm:grid-cols-3">
            <div class="flex items-start gap-2">
              <div class="bg-violet-500 text-white p-1.5 rounded">
                <Target class="w-4 h-4" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs text-slate-500">On Hand</p>
                <p class="text-sm font-medium text-slate-900 truncate">
                  {{ item.on_hand ?? '—' }}
                </p>
              </div>
            </div>

            <div class="flex items-start gap-2">
              <div class="bg-violet-500 text-white p-1.5 rounded">
                <TrendingDown class="w-4 h-4" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs text-slate-500">In Records</p>
                <p class="text-sm font-medium text-slate-900 truncate">
                  {{ item.stock_count ?? '—' }}
                </p>
              </div>
            </div>
            <div class="flex items-start gap-2">
              <div class="bg-violet-500 text-white p-1.5 rounded">
                <TrendingUp class="w-4 h-4" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs text-slate-500">Out Records</p>
                <p class="text-sm font-medium text-slate-900 truncate">
                  {{ item.stock_out_count ?? '—' }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="px-6 pt-3 flex gap-2 border-b border-slate-200 bg-white">
          <button
            class="px-3 py-2 text-sm rounded-t-md border-b-2"
            :class="
              activeTab === 'in'
                ? 'border-violet-500 text-violet-600 font-medium'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            "
            @click="switchTab('in')"
          >
            In Records
          </button>
          <button
            class="px-3 py-2 text-sm rounded-t-md border-b-2"
            :class="
              activeTab === 'out'
                ? 'border-violet-500 text-violet-600 font-medium'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            "
            @click="switchTab('out')"
          >
            Out Records
          </button>
        </div>

        <!-- Table -->
        <div
          class="px-6 pb-4 pt-2 text-xs text-slate-700"
          @scroll="onScroll"

        >
          <table class="w-full border-collapse">
            <thead class="sticky top-0 bg-slate-50 border-b-2 border-slate-200 z-10">
              <tr class="text-xs uppercase tracking-wide text-slate-600 font-semibold">
                <th class="py-3 px-3 text-left whitespace-nowrap">#</th>
                <th class="py-3 px-3 text-left whitespace-nowrap">Ledger</th>
                <th class="py-3 px-3 text-left whitespace-nowrap">Bill Number</th>
                <th class="py-3 px-3 text-center whitespace-nowrap">Qty</th>
                <th class="py-3 px-3 text-left whitespace-nowrap">Source</th>
                <th class="py-3 px-3 text-left whitespace-nowrap">Measure</th>
                <th class="py-3 px-3 text-left whitespace-nowrap">Date</th>
                <th class="py-3 px-3 text-left whitespace-nowrap">Batch</th>
                <th class="py-3 px-3 text-left whitespace-nowrap">Expiry</th>
                <th class="py-3 px-3 text-right whitespace-nowrap">Unit Price</th>
                <th class="py-3 px-3 text-left whitespace-nowrap">Store</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, index) in currentRecords"
                :key="row.id || index"
                class="border-b border-slate-100 hover:bg-slate-50 transition"
              >
                <td class="py-3 px-3 align-middle whitespace-nowrap text-slate-500">
                  {{ index + 1 }}
                </td>
                <td class="py-3 px-3 align-middle whitespace-nowrap font-medium text-slate-900">
                  {{ row.ledger_name || row.bill_number || '—' }}
                </td>
                <td class="py-3 px-3 text-center align-middle whitespace-nowrap font-semibold text-slate-800">
                  {{ row.bill_number || '—' }}
                </td>
                <td class="py-3 px-3 text-center align-middle whitespace-nowrap font-semibold text-slate-800">
                  {{ row.quantity }}
                </td>
                <td class="py-3 px-3 align-middle whitespace-nowrap text-slate-600">
                  {{ row.source_type }}
                </td>
                <td class="py-3 px-3 align-middle whitespace-nowrap text-slate-600">
                  {{ row.measure_unit }}
                </td>
                <td class="py-3 px-3 align-middle whitespace-nowrap text-slate-600">
                  {{ row.date }}
                </td>
                <td class="py-3 px-3 align-middle whitespace-nowrap text-slate-600">
                  {{ row.batch || '—' }}
                </td>
                <td class="py-3 px-3 align-middle whitespace-nowrap text-slate-600">
                  {{ row.expiry || '—' }}
                </td>
                <td class="py-3 px-3 text-right align-middle whitespace-nowrap font-semibold text-slate-900">
                  {{ row.unit_price }}
                </td>
                <td class="py-3 px-3 align-middle whitespace-nowrap text-slate-600">
                  {{ row.store }}
                </td>
              </tr>

              <tr v-if="!loading && currentRecords.length === 0">
                <td colspan="9" class="py-8 text-center text-slate-400">
                  {{ $t('general.no_record_available') }}
                </td>
              </tr>
            </tbody>
          </table>

          <div
            v-if="loading"
            class="py-3 flex items-center justify-center text-slate-400 text-xs gap-2"
          >
            <span
              class="h-3 w-3 rounded-full border-2 border-slate-300 border-t-violet-500 animate-spin"
            />
            Loading more…
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>


