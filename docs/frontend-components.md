### Frontend Components

#### `Components/DataTable.vue`
- Props: `items (Object with meta), columns (Array of {key,label,sortable}), url (String route name), title (String), filters (Object), showAddButton (Boolean), addTitle (String), addAction ('modal'|'redirect'), addRoute (String), addRouteParams (Object)`
- Emits: `edit(item)`, `delete(id)`, `add()`
- Behavior: client-side UI for searching, sorting, pagination; routes via Inertia using provided `url`.

Usage:
```vue
<DataTable
  :items="items"
  :columns="[
    { key: 'name', label: 'Name', sortable: true },
    { key: 'code', label: 'Code', sortable: true },
    { key: 'actions', label: 'Actions' }
  ]"
  url="items.index"
  title="Items"
  :showAddButton="true"
  addTitle="Item"
  addAction="redirect"
  addRoute="items.create"
  @edit="onEdit"
  @delete="onDelete"
/>
```

#### `Components/next/ConfirmDeleteDialog.vue`
- Props: `open, title, description, cancelText, continueText`
- Emits: `confirm`, `update:open`
- Use with `useDeleteResource` composable.

#### `Components/next/AddNewButton.vue`
- Props: styling (`variant`, `size`, `buttonClass`, `disabled`), content (`title`), behavior (`action: 'modal'|'redirect'`, `modalId`, `route`, `routeParams`, optional `onClick`)
- Emits: `click`, `modal-open`, `redirect`
- Example:
```vue
<AddNewButton title="Item" action="redirect" route="items.create" />
```

#### `Components/next/NextInput.vue`
- Props: `modelValue, label (required), id, type, disabled, isRequired, autocomplete, error, hint, placeholder`
- Emits: `update:modelValue`
- Example:
```vue
<NextInput v-model="form.name" label="Name" :isRequired="true" :error="errors.name" />
```

#### `Components/next/NextSelect.vue`
- Props: `modelValue, options, labelKey='name', valueKey='id', reduce(fn)?, id, floatingText, error, searchable=false, resourceType, searchFields, searchOptions`
- Emits: `update:modelValue`
- Integrates with `useSearchResources` for remote search when `searchable` and `resourceType` are set.
- Example:
```vue
<NextSelect
  v-model="form.supplier_id"
  :options="suppliers"
  floatingText="Supplier"
  :searchable="true"
  resourceType="ledgers"
  :searchFields="['name','email']"
/>
```

#### `Components/next/Dialog.vue`
- Props: `open, title, description, submitting, width, confirmText, cancelText, showCancel, showConfirm, closeable`
- Emits: `update:open`, `confirm`, `cancel`
- Example:
```vue
<Dialog v-model:open="isOpen" title="Edit Item" @confirm="save" :submitting="saving">
  <ItemForm v-model="form"/>
</Dialog>
```
