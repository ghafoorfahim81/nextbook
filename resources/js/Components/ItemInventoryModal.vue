<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import {
  Package, Hash, Pill, Box, Tag, Layers, TrendingUp, TrendingDown,
  DollarSign, Palette, Ruler, Image, MapPin, Barcode, Search,
  Building, Target
} from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, required: true },
})
console.log('this is item data', props.item)
const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
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
  { label: t('general.name'), value: props.item?.name, icon: Package },
  { label: t('item.code'), value: props.item?.code, icon: Hash },
  { label: t('item.generic_name'), value: props.item?.generic_name, icon: Pill },
  { label: t('item.packing'), value: props.item?.packing, icon: Box },
  { label: t('item.barcode'), value: props.item?.barcode, icon: Barcode },
  { label: t('item.unit_measure'), value: props.item?.measure, icon: Ruler },
  { label: t('item.brand'), value: props.item?.brand_name, icon: Tag },
  { label: t('item.category'), value: props.item?.category, icon: Layers }, 
  { label: t('item.maximum_stock'), value: props.item?.maximum_stock, icon: TrendingUp },
  { label: t('item.current_stock'), value: props.item?.on_hand || 0, icon: Target },
  { label: t('item.colors'), value: props.item?.colors, icon: Palette },
  { label: t('item.size'), value: props.item?.size?.name, icon: Ruler },
  { label: t('item.purchase_price'), value: props.item?.purchase_price, icon: DollarSign },
  { label: t('item.cost'), value: props.item?.cost, icon: DollarSign },
  { label: t('item.sale_price'), value: props.item?.sale_price, icon: DollarSign },
  { label: t('item.rate_a'), value: props.item?.rate_a, icon: DollarSign },
  { label: t('item.rate_b'), value: props.item?.rate_b, icon: DollarSign },
  { label: t('item.rate_c'), value: props.item?.rate_c, icon: DollarSign },
  { label: t('item.rack_no'), value: props.item?.rack_no, icon: MapPin },
  { label: t('item.fast_search'), value: props.item?.fast_search, icon: Search },
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
        class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[80vh]"
      >
        <!-- Item header -->
        <div
          class="border-b border-slate-200 bg-gradient-to-r rtl:bg-gradient-to-l from-violet-400 to-slate-50 px-6 py-4 flex justify-between items-center"
        >
          <div class="flex items-center gap-3">
            <div class="bg-violet-500 text-white p-3 rounded-lg">
              <Package class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-black">
                {{ item.name }}
              </h2>
              <p class="text-xs text-black mt-1">
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

        <!-- Scrollable content -->
        <div class="flex-1 overflow-y-auto" @scroll="onScroll">
          <!-- Info Section -->
          <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
            <div class="flex items-center gap-2 mb-4">
              <div class="bg-violet-500 text-white p-1.5 rounded">
                <Layers class="w-4 h-4" />
              </div>
              <h3 class="text-sm font-semibold text-slate-700">{{ t('general.info') }}</h3>
            </div>

            <div class="grid gap-x-6 gap-y-3 grid-cols-2 sm:grid-cols-3">
              <div v-for="detail in itemDetails" :key="detail.label" class="flex items-start gap-2">
                <component
                  :is="detail.icon"
                  class="w-4 h-4 text-violet-500 mt-0.5 flex-shrink-0"
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
                  <p class="text-xs text-slate-500">{{ t('general.on_hand') }}</p>
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
                  <p class="text-xs text-slate-500 font-bold">{{ t('item.in_records') }}</p>
                  <p class="text-sm font-medium text-slate-900 truncate font-weight-bold">
                    {{ item.stock_count ?? '—' }}
                  </p>
                </div>
              </div>
              <div class="flex items-start gap-2">
                <div class="bg-violet-500 text-white p-1.5 rounded">
                  <TrendingUp class="w-4 h-4" />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs text-slate-500 text-bold text-bold">{{ t('item.out_records') }}</p>
                  <p class="text-sm font-medium text-slate-900 truncate text-bold">
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
                  ? 'border-violet-500 text-violet-600 font-bold'
                  : 'border-transparent text-slate-500 hover:text-slate-700'
              "
              @click="switchTab('in')"
            >
              {{ t('item.in_records') }}
            </button>
            <button
              class="px-3 py-2 text-sm rounded-t-md border-b-2"
              :class="
                activeTab === 'out'
                  ? 'border-violet-500 text-violet-600 font-bold'
                  : 'border-transparent text-slate-500 hover:text-slate-700'
              "
              @click="switchTab('out')"
            >
              {{ t('item.out_records') }}
            </button>
          </div>

          <!-- Table -->
          <div
            class="px-6 pb-4 pt-2 text-xs text-slate-700"
          >
            <table class="w-full border-collapse">
            <thead class="sticky top-0 border-b-2 border-slate-200 z-10 bg-violet-500 text-white">
              <tr class="text-xs uppercase tracking-wide text-white font-semibold">
                <th class="py-3 px-3 text-left rtl:text-right whitespace-nowrap">#</th>
                <th class="py-3 px-3 text-left rtl:text-right whitespace-nowrap">{{ t('general.ledger') }}</th>
                <th class="py-3 px-3 text-left rtl:text-right whitespace-nowrap">{{ t('general.bill_number') }}</th>
                <th class="py-3 px-3 text-center rtl:text-right whitespace-nowrap">{{ t('general.quantity') }}</th>
                <th class="py-3 px-3 text-left rtl:text-right whitespace-nowrap">{{ t('general.source') }}</th>
                <th class="py-3 px-3 text-left rtl:text-right whitespace-nowrap">{{ t('admin.unit_measure.unit_measure') }}</th>
                <th class="py-3 px-3 text-left rtl:text-right whitespace-nowrap">{{ t('general.date') }}</th>
                <th class="py-3 px-3 text-left rtl:text-right whitespace-nowrap">{{ t('item.batch') }}</th>
                <th class="py-3 px-3 text-left rtl:text-right whitespace-nowrap">{{ t('item.expire_date') }}</th>
                <th class="py-3 px-3 text-right rtl:text-right whitespace-nowrap">{{ t('general.unit_price') }}</th>
                <th class="py-3 px-3 text-left rtl:text-right whitespace-nowrap">{{ t('admin.store.store') }}</th>
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
        </div> <!-- end scrollable content -->
      </div>
    </div>
  </teleport>
</template>


