import { computed, ref, unref, watch } from 'vue'

function flattenRowValues(row) {
  return Object.values(row || {})
    .flatMap((value) => {
      if (value === null || value === undefined) {
        return []
      }

      if (Array.isArray(value)) {
        return value.flatMap(flattenRowValues)
      }

      if (typeof value === 'object') {
        return flattenRowValues(value)
      }

      return [String(value)]
    })
}

function compareValues(left, right) {
  if (left === right) {
    return 0
  }

  if (left === null || left === undefined || left === '') {
    return 1
  }

  if (right === null || right === undefined || right === '') {
    return -1
  }

  const leftNumber = Number(left)
  const rightNumber = Number(right)
  const leftIsNumber = Number.isFinite(leftNumber) && leftNumber !== 0 ? !Number.isNaN(leftNumber) : !Number.isNaN(leftNumber)
  const rightIsNumber = Number.isFinite(rightNumber) && rightNumber !== 0 ? !Number.isNaN(rightNumber) : !Number.isNaN(rightNumber)

  if (leftIsNumber && rightIsNumber) {
    return leftNumber - rightNumber
  }

  const leftDate = Date.parse(left)
  const rightDate = Date.parse(right)

  if (!Number.isNaN(leftDate) && !Number.isNaN(rightDate)) {
    return leftDate - rightDate
  }

  return String(left).localeCompare(String(right), undefined, {
    numeric: true,
    sensitivity: 'base',
  })
}

export function useClientTable(rowsSource, options = {}) {
  const search = ref('')
  const sortKey = ref(options.defaultSortKey || '')
  const sortDirection = ref(options.defaultSortDirection || 'asc')
  const perPage = ref(options.perPage || 10)
  const currentPage = ref(1)

  const rows = computed(() => {
    const value = unref(rowsSource)
    return Array.isArray(value) ? value : []
  })

  watch([search, sortKey, sortDirection, perPage], () => {
    currentPage.value = 1
  })

  const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase()
    const sourceRows = rows.value

    const searchFiltered = !query
      ? sourceRows
      : sourceRows.filter((row) => flattenRowValues(row).some((value) => value.toLowerCase().includes(query)))

    if (!sortKey.value) {
      return searchFiltered
    }

    const directionMultiplier = sortDirection.value === 'desc' ? -1 : 1

    return [...searchFiltered].sort((left, right) => {
      const result = compareValues(left?.[sortKey.value], right?.[sortKey.value])
      return result * directionMultiplier
    })
  })

  const pagination = computed(() => {
    const total = filteredRows.value.length
    const lastPage = Math.max(1, Math.ceil(total / perPage.value))
    const page = Math.min(Math.max(1, currentPage.value), lastPage)
    const from = total > 0 ? ((page - 1) * perPage.value) + 1 : null
    const to = total > 0 ? Math.min(total, page * perPage.value) : null

    if (page !== currentPage.value) {
      currentPage.value = page
    }

    return {
      current_page: page,
      last_page: lastPage,
      per_page: perPage.value,
      total,
      from,
      to,
    }
  })

  const paginatedRows = computed(() => {
    const page = pagination.value.current_page
    const start = (page - 1) * perPage.value
    return filteredRows.value.slice(start, start + perPage.value)
  })

  function setPage(page) {
    currentPage.value = page
  }

  function toggleSort(key) {
    if (sortKey.value === key) {
      sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
      return
    }

    sortKey.value = key
    sortDirection.value = 'asc'
  }

  function reset() {
    search.value = ''
    sortKey.value = options.defaultSortKey || ''
    sortDirection.value = options.defaultSortDirection || 'asc'
    perPage.value = options.perPage || 10
    currentPage.value = 1
  }

  return {
    currentPage,
    filteredRows,
    pagination,
    paginatedRows,
    perPage,
    reset,
    search,
    setPage,
    sortDirection,
    sortKey,
    toggleSort,
  }
}
