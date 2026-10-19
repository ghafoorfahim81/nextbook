<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { ArrowLeft, ChevronDown, Database, History, Layers, Network } from 'lucide-vue-next'
import { useActivityLogLabels } from '@/composables/useActivityLogLabels'

const { t } = useI18n()
const { eventLabel, moduleLabel, describe, eventVariant } = useActivityLogLabels()

const props = defineProps({
  log: Object,
})

const logEntry = computed(() => props.log?.data ?? props.log ?? {})

// Formatted on the server (names instead of ids, translated labels). Empty
// sections are left out: a create has no "before", and metadata is usually
// only plumbing that the server already hides.
const sections = computed(() => ([
  { key: 'old_values', title: t('activity_log.old_values'), value: logEntry.value?.display_old_values ?? [] },
  { key: 'new_values', title: t('activity_log.new_values'), value: logEntry.value?.display_new_values ?? [] },
  { key: 'metadata', title: t('activity_log.metadata'), value: logEntry.value?.display_metadata ?? [] },
].filter(section => section.value.length)))

// Everything else the same action saved: a transfer's transaction, an
// item's variants and opening stock, a sale's lines.
const related = computed(() => logEntry.value?.related ?? [])
</script>

<template>
  <AppLayout :title="t('activity_log.view_log')">
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold text-primary">{{ t('activity_log.view_log') }}</h1>
          <p class="text-sm text-muted-foreground">{{ describe(logEntry) }}</p>
        </div>

        <Button as-child variant="outline">
          <Link :href="route('activity-logs.index')">
            <ArrowLeft class="me-2 h-4 w-4 rtl:rotate-180" />
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
              <Badge :variant="eventVariant(logEntry.event_type)">{{ eventLabel(logEntry.event_type) }}</Badge>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.module') }}</div>
              <div class="font-medium">{{ moduleLabel(logEntry.module) }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.reference') }}</div>
              <div class="font-medium" dir="ltr">{{ logEntry.subject || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.reference_id') }}</div>
              <div class="font-mono text-sm" dir="ltr">{{ logEntry.reference_id || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.user') }}</div>
              <div class="font-medium">{{ logEntry.user?.name || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('general.branch') }}</div>
              <div class="font-medium">{{ logEntry.branch?.name || '-' }}</div>
            </div>
            <div class="space-y-1 sm:col-span-2">
              <div class="text-sm text-muted-foreground">{{ t('general.date') }}</div>
              <div class="font-medium"><span dir="ltr">{{ logEntry.created_at_display || '-' }}</span></div>
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
              <div class="font-mono text-sm" dir="ltr">{{ logEntry.request?.ip_address || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">{{ t('activity_log.user_agent') }}</div>
              <div class="break-words text-sm">{{ logEntry.request?.user_agent || '-' }}</div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div v-if="sections.length" class="grid gap-4" :class="sections.length > 1 ? 'xl:grid-cols-2' : ''">
        <Card v-for="section in sections" :key="section.key" class="overflow-hidden">
          <CardHeader>
            <CardTitle class="flex items-center gap-2 text-primary">
              <Database class="h-5 w-5" />
              {{ section.title }}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid gap-2 sm:grid-cols-2">
              <div
                v-for="row in section.value"
                :key="`${section.key}-${row.key}`"
                class="rounded-md border border-border bg-muted/40 px-3 py-2"
              >
                <div class="text-xs tracking-wide text-muted-foreground">
                  {{ row.label }}
                </div>
                <div class="mt-1 whitespace-pre-wrap break-words text-sm font-medium">
                  {{ row.value }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- The rest of the same action: lines, transaction, variants, opening stock. -->
      <Card v-if="related.length">
        <CardHeader>
          <CardTitle class="flex items-center gap-2 text-primary">
            <Layers class="h-5 w-5" />
            {{ t('activity_log.related_records') }}
            <Badge variant="secondary">{{ related.length }}</Badge>
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <details
            v-for="(entry, index) in related"
            :key="`related-${index}`"
            class="group rounded-md border border-border"
            :open="related.length <= 3"
          >
            <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2 text-sm">
              <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
              <Badge :variant="eventVariant(entry.event_type)">{{ eventLabel(entry.event_type) }}</Badge>
              <span class="font-medium">{{ moduleLabel(entry.module) }}</span>
              <span v-if="entry.subject" class="text-muted-foreground" dir="auto">{{ entry.subject }}</span>
            </summary>
            <div v-if="entry.fields.length" class="grid gap-2 border-t border-border p-3 sm:grid-cols-2 xl:grid-cols-3">
              <div
                v-for="field in entry.fields"
                :key="field.key"
                class="rounded-md bg-muted/40 px-3 py-2"
              >
                <div class="text-xs text-muted-foreground">{{ field.label }}</div>
                <div class="mt-1 whitespace-pre-wrap break-words text-sm font-medium">
                  <template v-if="field.old !== null">
                    <span class="text-muted-foreground line-through">{{ field.old }}</span>
                    <span class="mx-1 inline-block text-muted-foreground rtl:rotate-180">→</span>
                  </template>
                  {{ field.value }}
                </div>
              </div>
            </div>
          </details>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
