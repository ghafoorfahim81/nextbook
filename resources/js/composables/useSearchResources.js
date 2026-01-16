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
    const searchResources = async (
        searchTerm,
        localOptions = [],
        resourceType,
        options = {}
      ) => {
        const {
          labelKey = 'name',
          valueKey = 'id',
          searchFields = [labelKey],
          minSearchLength = 2,
          cacheTimeout = 300000,
          debounceMs = 300,
          limit = 20,
          additionalParams = {},
        } = options

        // ✅ EMPTY SEARCH → signal caller to reset
        if (!searchTerm || !searchTerm.trim()) {
          return null
        }

        // ✅ BELOW MIN LENGTH → local only
        if (searchTerm.length < minSearchLength) {
          return searchLocal(searchTerm, localOptions, searchFields)
        }

        // Clear previous debounce
        if (searchTimeout.value) {
          clearTimeout(searchTimeout.value)
        }

        return new Promise((resolve) => {
          searchTimeout.value = setTimeout(async () => {
            try {
              // 1️⃣ Local search first
              const localResults = searchLocal(
                searchTerm,
                localOptions,
                searchFields
              )

              if (localResults.length >= 5) {
                resolve(localResults)
                return
              }

              // 2️⃣ Cache
              const paramsKey = serializeParams(additionalParams)
              const fieldsKey = searchFields?.length ? `fields:${searchFields.join(',')}` : ''
              const cacheKeyParts = [
                `limit:${limit}`,
                paramsKey ? `params:${paramsKey}` : '',
                fieldsKey,
              ].filter(Boolean)
              const cacheKey = `${resourceType}:${searchTerm}:${cacheKeyParts.join('|') || 'default'}`
              const cached = searchCache.value.get(cacheKey)

              if (cached && Date.now() - cached.timestamp < cacheTimeout) {
                resolve(mergeResults(localResults, cached.data, valueKey))
                return
              }

              // 3️⃣ Remote search
              isLoading.value = true
              const remoteResults = await searchRemote(searchTerm, resourceType, options)

              searchCache.value.set(cacheKey, {
                data: remoteResults,
                timestamp: Date.now(),
              })

              resolve(mergeResults(localResults, remoteResults, valueKey))
            } catch (e) {
              console.error(e)
              resolve(searchLocal(searchTerm, localOptions, searchFields))
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
            // Build query params properly for GET request
            const params = new URLSearchParams()
            params.append('search', searchTerm)
            params.append('limit', limit)
            // Add fields as array format: fields[]=name&fields[]=code
            searchFields.forEach(field => params.append('fields[]', field))
            // Add additional params
            Object.entries(additionalParams || {}).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    params.append(key, value)
                }
            })

            const response = await fetch(`/search/${resourceType}?${params.toString()}`, {
                method: 'GET',
                credentials: 'include',
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

    const serializeParams = (params = {}) => {
        const entries = Object.entries(params || {})
        if (!entries.length) return ''

        return entries
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([key, value]) => `${key}:${JSON.stringify(value)}`)
            .join('|')
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
