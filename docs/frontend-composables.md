### Frontend Composables

#### `useSearchResources()`
- Exports: `{ searchResources(term, localOptions?, resourceType, options?), isLoading, clearCache(resourceType?), getCacheStats() }`
- Options: `{ labelKey='name', valueKey='id', searchFields=[labelKey], minSearchLength=2, cacheTimeout=300000, debounceMs=300, limit=20, additionalParams={} }`
- Behavior: debounced search; merges local and remote results; caches remote responses per `resourceType:term`.
- Example:
```js
import { useSearchResources } from '@/composables/useSearchResources'

const { searchResources, isLoading } = useSearchResources()
const results = await searchResources('par', categories, 'categories', {
  searchFields: ['name','description'], limit: 10
})
```

#### `useDeleteResource()`
- Exports: `{ deleteResource(routeName, id, options?) }`
- Behavior: renders a `ConfirmDeleteDialog` portal, calls Inertia `router.delete`, shows i18n toasts on success/error.
- `options`: `{ title?, description?, successMessage?, name?, onSuccess?(), onError?() }`
- Example:
```js
import { useDeleteResource } from '@/composables/useDeleteResource'

const { deleteResource } = useDeleteResource()
deleteResource('items.destroy', itemId, { name: item.name })
```

#### `useSaveShortcut(options?)`
- Exports: the template ref to bind on the page's `<form>` element.
- Options: `{ form?, enabled=true, onSave? }`
- Behavior: Ctrl/Cmd + S saves full-page Create / Edit forms. Clicks the form's `button[type="submit"]` rather than submitting directly, so the per-module save confirmation in `<SubmitButtons>` still runs and a disabled button still blocks the save. Skips while `form.processing` or while a dialog is open, and always swallows the browser's own Save-page dialog. Not for modal forms (`CreateEditModal.vue`).
- Example:
```js
import { useSaveShortcut } from '@/composables/useSaveShortcut'

const saveFormRef = useSaveShortcut({ form })
// <form ref="saveFormRef" @submit.prevent="handleSubmitAction(false)">
```

#### `lib/utils`
- `cn(...inputs)`: merges class names with tailwind-merge
- `valueUpdater(nextOrFn, ref)`: set ref value or result of updater function
