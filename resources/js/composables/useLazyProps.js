import { onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const hasData = (value) => {
  if (value === undefined || value === null) return false
  if (Array.isArray(value)) return value.length > 0
  if (typeof value === 'object') {
    if ('data' in value) {
      const data = value.data
      return Array.isArray(data) ? data.length > 0 : Boolean(data)
    }
    return Object.keys(value).length > 0
  }
  return Boolean(value)
}

export const useLazyProps = (source, keys) => {
  const loading = ref(false)

  const fetchLazyProps = () => {
    const only = keys.filter((key) => !hasData(source?.[key]))
    if (!only.length) return
    loading.value = true
    router.reload({
      only,
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onFinish: () => { loading.value = false },
    })
  }

  onMounted(fetchLazyProps)

  return { fetchLazyProps, loading }
}
