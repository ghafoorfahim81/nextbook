### Examples

#### Search API
```bash
curl -X POST \
  -H 'Content-Type: application/json' \
  -d '{"search":"par","fields":["name","code"],"limit":5}' \
  http://localhost:8000/api/search/items
```

Response:
```json
{
  "success": true,
  "data": [ { "id": "01J...", "name": "Paracetamol", "code": "PAR-001" } ],
  "meta": { "resource_type": "items", "search_term": "par", "total": 1, "limit": 5 }
}
```

#### Create Item (multipart with photo)
Use a browser form to post to `/items` including fields from `ItemStoreRequest`. `openings[]` may include `store_id, quantity, batch, date, expire_date, discount, tax`.

#### Frontend: Using `NextSelect` with remote search
```vue
<NextSelect
  v-model="form.supplier_id"
  floatingText="Supplier"
  :searchable="true"
  resourceType="ledgers"
  :searchFields="['name','email']"
  :searchOptions="{ limit: 10 }"
/>
```

#### Deleting a resource with confirmation
```js
import { useDeleteResource } from '@/composables/useDeleteResource'
const { deleteResource } = useDeleteResource()
deleteResource('items.destroy', item.id, { name: item.name })
```
