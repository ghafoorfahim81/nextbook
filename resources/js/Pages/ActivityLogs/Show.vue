<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { ArrowLeft, Database, History, Network } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
  log: Object,
})

const logEntry = computed(() => props.log?.data ?? props.log ?? {})

const sections = computed(() => ([
  {
    key: 'old_values',
    title: t('activity_log.old_values'),
    empty: t('activity_log.no_old_values'),
    value: logEntry.value?.display_old_values ?? formatEntries(logEntry.value?.old_values),
  },
  {
    key: 'new_values',
    title: t('activity_log.new_values'),
    empty: t('activity_log.no_new_values'),
    value: logEntry.value?.display_new_values ?? formatEntries(logEntry.value?.new_values),
  },
  {
    key: 'metadata',
    title: t('activity_log.metadata'),
    empty: t('activity_log.no_metadata'),
    value: logEntry.value?.display_metadata ?? formatEntries(logEntry.value?.metadata),
  },
]))

function formatEntries(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return []
  }

  return Object.entries(value).map(([key, entryValue]) => ({
    key,
    label: humanizeKey(key),
    value: humanizeValue(entryValue),
  }))
}

function humanizeKey(key) {
  const specialLabels = {
    created_by: t('general.created_by'),
    updated_by: t('general.updated_by'),
    deleted_by: t('general.deleted_by'),
    branch_id: t('general.branch'),
  }

  if (specialLabels[key]) {
    return specialLabels[key]
  }

  return String(key)
    .replace(/_id$/i, '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, char => char.toUpperCase())
}

function humanizeValue(value) {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  if (typeof value === 'boolean') {
    return value ? t('general.yes') : t('general.no')
  }

  if (Array.isArray(value)) {
    return value.length ? value.map(humanizeValue).join(', ') : '-'
  }

  if (typeof value === 'object') {
    return JSON.stringify(value, null, 2)
  }

  return String(value)
}
</script>

<template>
  <AppLayout :title="t('activity_log.view_log')">
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold text-primary">{{ t('activity_log.view_log') }}</h1>
          <p class="text-sm text-muted-foreground">{{ logEntry.description || logEntry.event_type }}</p>
        </div>

        <Button as-child variant="outline">
          <Link :href="route('activity-logs.index')">
            <ArrowLeft class="mr-2 h-4 w-4" />
            {{ t('general.back') }}
          </Link>
        </Button>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
          <CardHeader>
            <CardTitle class="flex items-center gap-2 text-primary">
              <History class="h-5 w-5" />
              {{ t('activity_log.activity_log') }}
            </CardTitle>
          </CardHeader>
          <CardContent class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.event_type') }}</div>
              <Badge>{{ logEntry.event_type || '-' }}</Badge>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.module') }}</div>
              <div class="font-medium">{{ logEntry.module || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.reference_type') }}</div>
              <div class="font-mono text-sm">{{ logEntry.reference_type || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.reference_id') }}</div>
              <div class="font-mono text-sm">{{ logEntry.reference_id || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('general.created_by') }}</div>
              <div class="font-medium">{{ logEntry.user?.name || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('general.branch') }}</div>
              <div class="font-medium">{{ logEntry.branch?.name || '-' }}</div>
            </div>
            <div class="space-y-1 sm:col-span-2">
              <div class="text-sm text-muted-foreground">{{ t('general.date') }}</div>
              <div class="font-medium">{{ logEntry.created_at ? new Date(logEntry.created_at).toLocaleString() : '-' }}</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2 text-primary">
              <Network class="h-5 w-5" />
              {{ t('activity_log.request_context') }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.ip_address') }}</div>
              <div class="font-mono text-sm">{{ logEntry.request?.ip_address || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.user_agent') }}</div>
              <div class="break-words text-sm">{{ logEntry.request?.user_agent || '-' }}</div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <Card v-for="section in sections" :key="section.key" class="overflow-hidden">
          <CardHeader>
            <CardTitle class="flex items-center gap-2 text-primary">
              <Database class="h-5 w-5" />
              {{ section.title }}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="section.value.length" class="space-y-2">
              <div
                v-for="row in section.value"
                :key="`${section.key}-${row.key}`"
                class="rounded-md border border-border bg-muted/40 px-3 py-2"
              >
                <div class="text-xs uppercase tracking-wide text-muted-foreground">
                  {{ row.label }}
                </div>
                <div class="mt-1 whitespace-pre-wrap break-words text-sm font-medium">
                  {{ row.value }}
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-muted-foreground">
              {{ section.empty }}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
