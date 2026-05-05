<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import {
  AlertTriangle,
  Eye,
  Filter,
  RotateCcw,
  Search,
  Trash2,
  Clock3,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { useAuth } from '@/composables/useAuth'
import AppLayout from '@/Layouts/Layout.vue'
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select'
import { Input } from '@/Components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'

const props = defineProps({
  filters: { type: Object, required: true },
  moduleOptions: { type: Array, default: () => [] },
  records: { type: Object, required: true },
  summary: { type: Object, required: true },
})

const { t } = useI18n()
const { can } = useAuth()

const localFilters = ref({
  search: props.filters.search || '',
  module: props.filters.module || 'all',
  per_page: Number(props.filters.per_page || 25),
  page: Number(props.filters.page || 1),
})

watch(
  () => props.filters,
  (value) => {
    localFilters.value = {
      search: value.search || '',
      module: value.module || 'all',
      per_page: Number(value.per_page || 25),
      page: Number(value.page || 1),
    }
  },
  { deep: true },
)

const activeRecord = ref(null)
const restoreTarget = ref(null)
const restoreOpen = ref(false)
const forceDeleteTarget = ref(null)
const forceDeleteOpen = ref(false)
let searchTimer = null

const records = computed(() => props.records?.data || [])

const summaryCards = computed(() => [
  { label: t('deleted_records.summary.total'), value: props.summary.total ?? 0 },
  { label: t('deleted_records.summary.modules'), value: props.summary.modules ?? 0 },
  { label: t('deleted_records.summary.expiring_soon'), value: props.summary.expiring_soon ?? 0 },
])

function compactFilters(filters) {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined),
  )
}

function submit(page = 1) {
  router.get(route('deleted-records.index'), compactFilters({
    ...localFilters.value,
    page,
  }), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function scheduleSearch() {
  if (searchTimer) {
    window.clearTimeout(searchTimer)
  }

  searchTimer = window.setTimeout(() => submit(1), 300)
}

watch(
  () => localFilters.value.search,
  () => scheduleSearch(),
)

function updateModule(value) {
  localFilters.value.module = value
  submit(1)
}

function updatePerPage(value) {
  localFilters.value.per_page = Number(value)
  submit(1)
}

function openDetails(record) {
  activeRecord.value = record
}

function closeDetails() {
  activeRecord.value = null
}

function askRestore(record) {
  restoreTarget.value = record
  restoreOpen.value = true
}

function askForceDelete(record) {
  forceDeleteTarget.value = record
  forceDeleteOpen.value = true
}

function restoreRecord() {
  if (!restoreTarget.value) {
    return
  }

  const record = restoreTarget.value

  router.patch(route('deleted-records.restore', {
    module: record.module,
    record: record.record_id,
  }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(t('deleted_records.actions.restored'))
      restoreTarget.value = null
      restoreOpen.value = false
      closeDetails()
    },
    onError: () => {
      toast.error(t('deleted_records.actions.restore_failed'))
    },
  })
}

function forceDeleteRecord() {
  if (!forceDeleteTarget.value) {
    return
  }

  const record = forceDeleteTarget.value

  router.delete(route('deleted-records.destroy', {
    module: record.module,
    record: record.record_id,
  }), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(t('deleted_records.actions.force_deleted'))
      forceDeleteTarget.value = null
      forceDeleteOpen.value = false
      closeDetails()
    },
    onError: () => {
      toast.error(t('deleted_records.actions.force_delete_failed'))
    },
  })
}

function clearFilters() {
  localFilters.value.search = ''
  localFilters.value.module = 'all'
  localFilters.value.per_page = 25
  submit(1)
}

function formatValue(value) {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  if (typeof value === 'boolean') {
    return value ? t('deleted_records.boolean.yes') : t('deleted_records.boolean.no')
  }

  if (Array.isArray(value) || typeof value === 'object') {
    return JSON.stringify(value, null, 2)
  }

  return String(value)
}

function daysBadgeClass(daysRemaining) {
  if (daysRemaining <= 3) {
    return 'bg-red-500/15 text-red-700 ring-1 ring-red-500/20'
  }

  if (daysRemaining <= 7) {
    return 'bg-amber-500/15 text-amber-700 ring-1 ring-amber-500/20'
  }

  return 'bg-emerald-500/15 text-emerald-700 ring-1 ring-emerald-500/20'
}
</script>

<template>
  <AppLayout>
    <Head :title="t('deleted_records.title')" />

    <div class="space-y-6 text-foreground">
      <section class="overflow-hidden rounded-[30px] border border-border/70 bg-gradient-to-br from-slate-950 via-slate-900 to-violet-950 p-6 text-white shadow-[0_20px_50px_rgba(15,23,42,0.35)]">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-medium text-white/80 backdrop-blur">
              <Trash2 class="h-3.5 w-3.5" />
              {{ t('deleted_records.badge') }}
            </div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ t('deleted_records.title') }}</h1>
            <p class="max-w-3xl text-sm leading-7 text-white/75">{{ t('deleted_records.subtitle') }}</p>
          </div>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <Card v-for="card in summaryCards" :key="card.label" class="border-border/70 bg-card shadow-sm">
          <CardContent class="flex items-center justify-between p-5">
            <div class="space-y-1">
              <p class="text-sm text-muted-foreground">{{ card.label }}</p>
              <p class="text-2xl font-semibold tracking-tight">{{ card.value }}</p>
            </div>
            <div class="rounded-full bg-primary/10 p-3 text-primary">
              <Clock3 class="h-5 w-5" />
            </div>
          </CardContent>
        </Card>
      </section>

      <section class="space-y-4 rounded-[28px] border border-border/70 bg-card p-5 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_160px_auto] lg:items-end">
          <div class="space-y-2">
            <label class="text-xs font-semibold uppercase tracking-[0.24em] text-muted-foreground">
              {{ t('deleted_records.filters.search') }}
            </label>
            <div class="relative">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                v-model="localFilters.search"
                type="text"
                :placeholder="t('deleted_records.filters.search_placeholder')"
                class="pl-9"
              />
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-xs font-semibold uppercase tracking-[0.24em] text-muted-foreground">
              {{ t('deleted_records.filters.module') }}
            </label>
            <Select :model-value="localFilters.module" @update:model-value="updateModule">
              <SelectTrigger>
                <SelectValue :placeholder="t('deleted_records.filters.module_placeholder')" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in moduleOptions" :key="option.value" :value="option.value">
                  {{ option.label }}{{ option.count !== undefined ? ` (${option.count})` : '' }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <label class="text-xs font-semibold uppercase tracking-[0.24em] text-muted-foreground">
              {{ t('deleted_records.filters.per_page') }}
            </label>
            <Select :model-value="String(localFilters.per_page)" @update:model-value="updatePerPage">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="10">10</SelectItem>
                <SelectItem value="25">25</SelectItem>
                <SelectItem value="50">50</SelectItem>
                <SelectItem value="100">100</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex items-center gap-2">
            <Button variant="outline" class="gap-2" @click="clearFilters">
              <Filter class="h-4 w-4" />
              {{ t('deleted_records.filters.clear') }}
            </Button>
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-border/70">
          <Table>
            <TableHeader class="bg-muted/40">
              <TableRow>
                <TableHead>{{ t('deleted_records.columns.module') }}</TableHead>
                <TableHead>{{ t('deleted_records.columns.record_id') }}</TableHead>
                <TableHead>{{ t('deleted_records.columns.title') }}</TableHead>
                <TableHead>{{ t('deleted_records.columns.deleted_by') }}</TableHead>
                <TableHead>{{ t('deleted_records.columns.deleted_at') }}</TableHead>
                <TableHead class="text-right">{{ t('deleted_records.columns.days_remaining') }}</TableHead>
                <TableHead class="text-right">{{ t('deleted_records.columns.actions') }}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-if="records.length === 0">
                <TableCell colspan="7" class="py-16 text-center text-sm text-muted-foreground">
                  {{ t('deleted_records.empty') }}
                </TableCell>
              </TableRow>

              <TableRow v-for="record in records" :key="`${record.module}:${record.record_id}`" class="hover:bg-muted/40">
                <TableCell class="font-medium">{{ record.module_label }}</TableCell>
                <TableCell class="font-mono text-xs">{{ record.record_id }}</TableCell>
                <TableCell class="max-w-[280px] truncate">{{ record.title }}</TableCell>
                <TableCell>{{ record.deleted_by_name || t('deleted_records.system') }}</TableCell>
                <TableCell>{{ record.deleted_at_display }}</TableCell>
                <TableCell class="text-right">
                  <Badge :class="daysBadgeClass(record.days_remaining)">
                    {{ record.days_remaining }} {{ t('deleted_records.days') }}
                  </Badge>
                </TableCell>
                <TableCell class="text-right">
                  <div class="inline-flex items-center gap-2">
                    <Button variant="ghost" size="icon" @click="openDetails(record)">
                      <Eye class="h-4 w-4" />
                    </Button>
                    <Button v-if="can('deleted_records.restore')" variant="outline" size="icon" @click="askRestore(record)">
                      <RotateCcw class="h-4 w-4" />
                    </Button>
                    <Button v-if="can('deleted_records.force_delete')" variant="destructive" size="icon" @click="askForceDelete(record)">
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        <div class="flex flex-col gap-2 border-t pt-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
          <div>
            {{ t('deleted_records.pagination.showing', {
              from: records?.length ? props.records.meta.from : 0,
              to: records?.length ? props.records.meta.to : 0,
              total: props.records.meta.total || 0,
            }) }}
          </div>
          <div class="flex items-center gap-2">
            <Button variant="outline" :disabled="props.records.meta.current_page <= 1" @click="submit(props.records.meta.current_page - 1)">
              {{ t('deleted_records.pagination.previous') }}
            </Button>
            <span class="text-xs">
              {{ props.records.meta.current_page }} / {{ props.records.meta.last_page }}
            </span>
            <Button variant="outline" :disabled="props.records.meta.current_page >= props.records.meta.last_page" @click="submit(props.records.meta.current_page + 1)">
              {{ t('deleted_records.pagination.next') }}
            </Button>
          </div>
        </div>
      </section>
    </div>

    <Dialog :open="Boolean(activeRecord)" @update:open="(value) => !value && closeDetails()">
      <DialogContent class="max-h-[90vh] max-w-4xl overflow-hidden">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Eye class="h-4 w-4 text-primary" />
            {{ activeRecord?.title }}
          </DialogTitle>
          <DialogDescription>
            {{ activeRecord?.module_label }} - {{ activeRecord?.record_id }}
          </DialogDescription>
        </DialogHeader>

        <div v-if="activeRecord" class="space-y-5 overflow-y-auto pr-1">
          <div
            v-if="activeRecord.dependency_warning"
            class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-900 dark:text-amber-100"
          >
            <div class="flex items-start gap-2">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
              <p>{{ activeRecord.dependency_warning }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-border/70 bg-muted/20 p-4">
              <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.24em] text-muted-foreground">
                {{ t('deleted_records.detail.metadata') }}
              </h3>
              <dl class="space-y-3">
                <div v-for="item in activeRecord.metadata" :key="item.key" class="flex flex-col gap-1">
                  <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ item.label }}</dt>
                  <dd class="text-sm text-foreground">{{ formatValue(item.display_value ?? item.value) }}</dd>
                </div>
              </dl>
            </div>

            <div class="rounded-2xl border border-border/70 bg-muted/20 p-4">
              <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.24em] text-muted-foreground">
                {{ t('deleted_records.detail.original_fields') }}
              </h3>
              <div class="max-h-[34rem] space-y-3 overflow-y-auto pr-1">
                <div v-for="field in activeRecord.fields" :key="field.key" class="rounded-xl border border-border/60 bg-background p-3">
                  <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    {{ field.label }}
                  </div>
                  <pre class="mt-1 whitespace-pre-wrap break-words text-sm text-foreground">{{ formatValue(field.display_value ?? field.value) }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:justify-end">
          <Button variant="outline" @click="closeDetails">
            {{ t('deleted_records.actions.close') }}
          </Button>
          <Button v-if="can('deleted_records.restore')" variant="outline" class="gap-2" @click="askRestore(activeRecord)">
            <RotateCcw class="h-4 w-4" />
            {{ t('deleted_records.actions.restore') }}
          </Button>
          <Button v-if="can('deleted_records.force_delete')" variant="destructive" class="gap-2" @click="askForceDelete(activeRecord)">
            <Trash2 class="h-4 w-4" />
            {{ t('deleted_records.actions.force_delete') }}
          </Button>
        </div>
      </DialogContent>
    </Dialog>

    <ConfirmDeleteDialog
      :open="restoreOpen"
      :title="t('deleted_records.restore_title')"
      :description="restoreTarget ? t('deleted_records.restore_description', { name: restoreTarget.title }) : ''"
      :cancel-text="t('deleted_records.actions.cancel')"
      :continue-text="t('deleted_records.actions.restore')"
      @update:open="restoreOpen = $event"
      @confirm="restoreRecord"
    />

    <ConfirmDeleteDialog
      :open="forceDeleteOpen"
      :title="t('deleted_records.force_delete_title')"
      :description="forceDeleteTarget ? t('deleted_records.force_delete_description', { name: forceDeleteTarget.title }) : ''"
      :cancel-text="t('deleted_records.actions.cancel')"
      :continue-text="t('deleted_records.actions.force_delete')"
      @update:open="forceDeleteOpen = $event"
      @confirm="forceDeleteRecord"
    />
  </AppLayout>
</template>
