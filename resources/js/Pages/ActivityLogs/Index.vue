<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { Link, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import NextDatePicker from '@/Components/next/NextDatePicker.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Label } from '@/Components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { Badge } from '@/Components/ui/badge'
import { Eye, Filter, RotateCcw } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
  logs: Object,
  filters: Object,
  filterOptions: Object,
})

const form = reactive({
  search: props.filters?.search ?? '',
  from: props.filters?.from ?? '',
  to: props.filters?.to ?? '',
  module: props.filters?.module ?? 'all',
  event_type: props.filters?.event_type ?? 'all',
  user_id: props.filters?.user_id ?? 'all',
  reference_type: props.filters?.reference_type ?? '',
  reference_id: props.filters?.reference_id ?? '',
  per_page: String(props.filters?.per_page ?? 25),
})

const rows = computed(() => props.logs?.data ?? [])
const meta = computed(() => props.logs?.meta ?? {})
const moduleOptions = computed(() => ([
  { id: 'all', name: t('general.all') },
  ...(props.filterOptions?.modules || []).map(module => ({
    id: module,
    name: module,
  })),
]))
const eventTypeOptions = computed(() => ([
  { id: 'all', name: t('general.all') },
  ...(props.filterOptions?.event_types || []).map(eventType => ({
    id: eventType,
    name: eventType,
  })),
]))
const userOptions = computed(() => ([
  { id: 'all', name: t('general.all') },
  ...(props.filterOptions?.users || []).map(user => ({
    id: user.id,
    name: user.name,
  })),
]))
const paginationLinks = computed(() => {
  const candidates = Array.isArray(props.logs?.meta?.links)
    ? props.logs.meta.links
    : Array.isArray(props.logs?.links)
      ? props.logs.links
      : []

  return candidates.filter(link => link && typeof link === 'object' && 'label' in link)
})

function applyFilters(page = 1) {
  router.get(route('activity-logs.index'), {
    page,
    search: form.search || undefined,
    from: form.from || undefined,
    to: form.to || undefined,
    module: form.module !== 'all' ? form.module : undefined,
    event_type: form.event_type !== 'all' ? form.event_type : undefined,
    user_id: form.user_id !== 'all' ? form.user_id : undefined,
    reference_type: form.reference_type || undefined,
    reference_id: form.reference_id || undefined,
    per_page: form.per_page || 25,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

function resetFilters() {
  form.search = ''
  form.from = ''
  form.to = ''
  form.module = 'all'
  form.event_type = 'all'
  form.user_id = 'all'
  form.reference_type = ''
  form.reference_id = ''
  form.per_page = '25'
  applyFilters(1)
}

function visitPage(url) {
  if (!url) return
  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
  })
}

function eventVariant(eventType) {
  const type = String(eventType || '').toLowerCase()

  if (['deleted', 'rejected', 'cancelled'].includes(type)) return 'destructive'
  if (['approved', 'posted', 'completed', 'created'].includes(type)) return 'default'

  return 'secondary'
}
</script>

<template>
  <AppLayout :title="t('activity_log.activity_logs')">
    <div class="space-y-6">
      <Card>
        <CardHeader class="pb-4">
          <CardTitle class="flex items-center gap-2 text-primary">
            <Filter class="h-5 w-5" />
            {{ t('activity_log.filters') }}
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <NextInput
              v-model="form.search"
              :label="t('general.search')"
              :placeholder="t('general.search_placeholder', { name: t('activity_log.activity_logs') })"
            />

            <NextSelect
              v-model="form.module"
              :options="moduleOptions"
              label-key="name"
              value-key="id"
              :reduce="option => option.id"
              :searchable="false"
              :show-arrow="true"
              :has-add-button="false"
              :floating-text="t('activity_log.module')"
            />

            <NextSelect
              v-model="form.event_type"
              :options="eventTypeOptions"
              label-key="name"
              value-key="id"
              :reduce="option => option.id"
              :searchable="false"
              :show-arrow="true"
              :has-add-button="false"
              :floating-text="t('activity_log.event_type')"
            />

            <NextSelect
              v-model="form.user_id"
              :options="userOptions"
              label-key="name"
              value-key="id"
              :reduce="option => option.id"
              :searchable="true"
              :show-arrow="true"
              :has-add-button="false"
              :floating-text="t('general.created_by')"
            />

            <div class="space-y-2">
              <NextDatePicker
                v-model="form.from"
                :placeholder="t('general.from')"
                :show-label="false"
                :show-icon="true"
              />
            </div>

            <div class="space-y-2">
              <NextDatePicker
                v-model="form.to"
                :placeholder="t('general.to')"
                :show-label="false"
                :show-icon="true"
              />
            </div>

            <NextInput
              v-model="form.reference_type"
              :label="t('activity_log.reference_type')"
              placeholder="sale"
            />

            <NextInput
              v-model="form.reference_id"
              :label="t('activity_log.reference_id')"
              placeholder="01H..."
            />
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Button @click="applyFilters(1)">{{ t('general.filter') }}</Button>
            <Button variant="outline" @click="resetFilters">
              <RotateCcw class="mr-2 h-4 w-4" />
              {{ t('general.reset') }}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-3">
          <CardTitle class="text-primary">{{ t('activity_log.activity_logs') }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="overflow-x-auto rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>#</TableHead>
                  <TableHead>{{ t('activity_log.event_type') }}</TableHead>
                  <TableHead>{{ t('activity_log.module') }}</TableHead>
                  <TableHead>{{ t('activity_log.reference_id') }}</TableHead>
                  <TableHead>{{ t('general.created_by') }}</TableHead>
                  <TableHead>{{ t('general.branch') }}</TableHead>
                  <TableHead>{{ t('general.date') }}</TableHead>
                  <TableHead>{{ t('general.description') }}</TableHead>
                  <TableHead class="w-[120px]">{{ t('general.actions') }}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="rows.length === 0">
                  <TableCell colspan="9" class="py-10 text-center text-muted-foreground">
                    {{ t('activity_log.empty_state') }}
                  </TableCell>
                </TableRow>

                <TableRow v-for="(log, index) in rows" :key="log.id">
                  <TableCell>{{ ((meta.current_page || 1) - 1) * (meta.per_page || 25) + index + 1 }}</TableCell>
                  <TableCell>
                    <Badge :variant="eventVariant(log.event_type)">
                      {{ log.event_type }}
                    </Badge>
                  </TableCell>
                  <TableCell>{{ log.module || '-' }}</TableCell>
                  <TableCell class="font-mono text-xs">{{ log.reference_id || '-' }}</TableCell>
                  <TableCell>{{ log.user?.name || '-' }}</TableCell>
                  <TableCell>{{ log.branch?.name || '-' }}</TableCell>
                  <TableCell>{{ log.created_at ? new Date(log.created_at).toLocaleString() : '-' }}</TableCell>
                  <TableCell class="max-w-[340px] truncate">{{ log.description || '-' }}</TableCell>
                  <TableCell>
                    <Button as-child variant="outline" size="sm">
                      <Link :href="route('activity-logs.show', log.id)">
                        <Eye class="mr-2 h-4 w-4" />
                        {{ t('activity_log.view_details') }}
                      </Link>
                    </Button>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-muted-foreground">
              {{ t('datatable.showing', {
                from: meta.from || 0,
                to: meta.to || 0,
                total: meta.total || 0,
              }) }}
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <Button
                v-for="link in paginationLinks"
                :key="`${link.label}-${link.url}`"
                :variant="link.active ? 'default' : 'outline'"
                size="sm"
                :disabled="!link.url"
                @click="visitPage(link.url)"
                v-html="link.label"
              />
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
