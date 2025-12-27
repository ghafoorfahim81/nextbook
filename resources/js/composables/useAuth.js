import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useAuth() {
  const page = usePage()

  const user = computed(() => page.props.auth?.user || null)
  const permissions = computed(() => user.value?.permissions || [])
  const roles = computed(() => user.value?.roles || [])

  const isSuperAdmin = computed(() => roles.value.includes('super-admin'))

  /**
   * Check if the current user has at least one of the required permissions.
   * - `required` can be a single string or an array (OR logic).
   */
  function can(required) {
    if (isSuperAdmin.value) {
      return true
    }

    if (!required) {
      return true
    }

    const list = Array.isArray(required) ? required : [required]
    if (!permissions.value || permissions.value.length === 0) {
      return false
    }

    return list.some((perm) => permissions.value.includes(perm))
  }

  return {
    user,
    permissions,
    roles,
    isSuperAdmin,
    can,
  }
}


