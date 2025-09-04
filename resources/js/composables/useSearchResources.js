import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

export function useSearchResources() {
    const searchCache = ref(new Map())
    const isLoading = ref(false)
    const searchTimeout = ref(null)

    /**
     * Search for resources with local and remote fallback
     * @param {string} searchTerm - The search term
     * @param {Array} localOptions - Local options to search through first
     * @param {string} resourceType - The resource type/table name for remote search
     * @param {Object} options - Additional options
     * @returns {Promise<Array>} - Array of search results
     */
    const searchResources = async (searchTerm, localOptions = [], resourceType, options = {}) => {
        if (!searchTerm || searchTerm.length < 2) {
            return localOptions
        }

        const {
            labelKey = 'name',
            valueKey = 'id',
            searchFields = [labelKey],
            minSearchLength = 2,
            cacheTimeout = 300000, // 5 minutes
            debounceMs = 300
        } = options

        // Clear previous timeout
        if (searchTimeout.value) {
            clearTimeout(searchTimeout.value)
        }

        return new Promise((resolve) => {
            searchTimeout.value = setTimeout(async () => {
                try {
                    // First, search locally
                    const localResults = searchLocal(searchTerm, localOptions, searchFields, labelKey)

                    // If we have enough local results, return them
                    if (localResults.length >= 5) {
                        resolve(localResults)
                        return
                    }

                    // Check cache first
                    const cacheKey = `${resourceType}:${searchTerm}`
                    const cached = searchCache.value.get(cacheKey)

                    if (cached && Date.now() - cached.timestamp < cacheTimeout) {
                        // Merge local and cached results, removing duplicates
                        const mergedResults = mergeResults(localResults, cached.data, valueKey)
                        resolve(mergedResults)
                        return
                    }

                    // Search remotely
                    isLoading.value = true
                    const remoteResults = await searchRemote(searchTerm, resourceType, options)

                    // Cache the results
                    searchCache.value.set(cacheKey, {
                        data: remoteResults,
                        timestamp: Date.now()
                    })

                    // Merge local and remote results
                    const mergedResults = mergeResults(localResults, remoteResults, valueKey)
                    resolve(mergedResults)

                } catch (error) {
                    console.error('Search error:', error)
                    // Return local results as fallback
                    resolve(searchLocal(searchTerm, localOptions, searchFields, labelKey))
                } finally {
                    isLoading.value = false
                }
            }, debounceMs)
        })
    }

    /**
     * Search through local options
     */
    const searchLocal = (searchTerm, localOptions, searchFields, labelKey) => {
        if (!localOptions || localOptions.length === 0) return []

        const term = searchTerm.toLowerCase()

        return localOptions.filter(option => {
            return searchFields.some(field => {
                const value = option[field]
                return value && value.toString().toLowerCase().includes(term)
            })
        })
    }

    /**
     * Search remotely via API
     */
    const searchRemote = async (searchTerm, resourceType, options = {}) => {
        const {
            searchFields = ['name'],
            limit = 20,
            additionalParams = {}
        } = options

        try {
            const response = await fetch(`/api/search/${resourceType}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    search: searchTerm,
                    fields: searchFields,
                    limit,
                    ...additionalParams
                })
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const data = await response.json()
            return data.data || []

        } catch (error) {
            console.error('Remote search failed:', error)
            return []
        }
    }

    /**
     * Merge local and remote results, removing duplicates
     */
    const mergeResults = (localResults, remoteResults, valueKey) => {
        const merged = [...localResults]
        const localIds = new Set(localResults.map(item => item[valueKey]))

        remoteResults.forEach(remoteItem => {
            if (!localIds.has(remoteItem[valueKey])) {
                merged.push(remoteItem)
            }
        })

        return merged
    }

    /**
     * Clear search cache
     */
    const clearCache = (resourceType = null) => {
        if (resourceType) {
            // Clear cache for specific resource type
            for (const [key] of searchCache.value) {
                if (key.startsWith(`${resourceType}:`)) {
                    searchCache.value.delete(key)
                }
            }
        } else {
            // Clear all cache
            searchCache.value.clear()
        }
    }

    /**
     * Get cache statistics
     */
    const getCacheStats = () => {
        return {
            size: searchCache.value.size,
            keys: Array.from(searchCache.value.keys())
        }
    }

    return {
        searchResources,
        isLoading: computed(() => isLoading.value),
        clearCache,
        getCacheStats
    }
}
