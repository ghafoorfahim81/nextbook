<template>
    <div class="p-6 space-y-6">
        <h2 class="text-2xl font-bold">Searchable Select Examples</h2>

        <!-- Example 1: Basic Searchable Select -->
        <div class="space-y-2">
            <h3 class="text-lg font-semibold">Basic Searchable Select (Ledgers)</h3>
            <NextSelect
                :options="localLedgers"
                v-model="selectedLedger"
                :reduce="ledger => ledger.id"
                floating-text="Select a Ledger"
                :searchable="true"
                resource-type="ledgers"
                :search-fields="['name', 'email', 'phone']"
                :search-options="{ type: 'supplier' }"
            />
            <p class="text-sm text-gray-600">Selected: {{ selectedLedger }}</p>
        </div>

        <!-- Example 2: Items Search -->
        <div class="space-y-2">
            <h3 class="text-lg font-semibold">Items Search</h3>
            <NextSelect
                :options="localItems"
                v-model="selectedItem"
                :reduce="item => item.id"
                floating-text="Select an Item"
                :searchable="true"
                resource-type="items"
                :search-fields="['name', 'code', 'description']"
                :search-options="{ category_id: 1 }"
            />
            <p class="text-sm text-gray-600">Selected: {{ selectedItem }}</p>
        </div>

        <!-- Example 3: Non-searchable (Regular) Select -->
        <div class="space-y-2">
            <h3 class="text-lg font-semibold">Regular Select (No Search)</h3>
            <NextSelect
                :options="staticOptions"
                v-model="selectedStatic"
                :reduce="option => option.id"
                floating-text="Select an Option"
            />
            <p class="text-sm text-gray-600">Selected: {{ selectedStatic }}</p>
        </div>

        <!-- Cache Management -->
        <div class="space-y-2">
            <h3 class="text-lg font-semibold">Cache Management</h3>
            <div class="flex space-x-2">
                <button
                    @click="clearCache('ledgers')"
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                >
                    Clear Ledgers Cache
                </button>
                <button
                    @click="clearAllCache"
                    class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600"
                >
                    Clear All Cache
                </button>
                <button
                    @click="showCacheStats"
                    class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
                >
                    Show Cache Stats
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import { useSearchResources } from '@/composables/useSearchResources.js'

// Search composable
const { clearCache, getCacheStats } = useSearchResources()

// Local data
const selectedLedger = ref(null)
const selectedItem = ref(null)
const selectedStatic = ref(null)

// Sample local options
const localLedgers = ref([
    { id: 1, name: 'Local Supplier 1', email: 'supplier1@example.com' },
    { id: 2, name: 'Local Supplier 2', email: 'supplier2@example.com' }
])

const localItems = ref([
    { id: 1, name: 'Local Item 1', code: 'ITEM001' },
    { id: 2, name: 'Local Item 2', code: 'ITEM002' }
])

const staticOptions = ref([
    { id: 1, name: 'Option 1' },
    { id: 2, name: 'Option 2' },
    { id: 3, name: 'Option 3' }
])

// Cache management functions
const clearAllCache = () => {
    clearCache()
    alert('All cache cleared!')
}

const showCacheStats = () => {
    const stats = getCacheStats()
    alert(`Cache Stats:\nSize: ${stats.size}\nKeys: ${stats.keys.join(', ')}`)
}
</script>
