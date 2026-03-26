<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  rows: { type: Array, default: () => [] },
  pagination: { type: Object, required: true },
  meta: { type: Object, default: () => ({}) },
  emptyMessage: { type: String, required: true },
})

const emit = defineEmits(['page-change'])
const { t } = useI18n()

const pages = computed(() => {
  const current = Number(props.pagination.current_page || 1)
  const last = Number(props.pagination.last_page || 1)
  const start = Math.max(1, current - 2)
  const end = Math.min(last, start + 4)
  const first = Math.max(1, end - 4)

  return Array.from({ length: end - first + 1 }, (_, index) => first + index)
})

const headlineCards = computed(() => [
  {
    key: 'total_users',
    label: t('report.user_activity.cards.total_users'),
    value: Number(props.summary?.total_users || 0),
    tone: 'border-emerald-500/30 bg-emerald-500/10',
  },
  {
    key: 'active_users',
    label: t('report.user_activity.cards.active_users'),
    value: Number(props.summary?.active_users || 0),
    note: `${Number(props.summary?.active_rate || 0).toLocaleString()}% ${t('report.user_activity.cards.active_suffix')}`,
    tone: 'border-sky-500/30 bg-sky-500/10',
  },
  {
    key: 'total_activities',
    label: t('report.user_activity.cards.total_activities'),
    value: Number(props.summary?.total_activities || 0),
    tone: 'border-violet-500/30 bg-violet-500/10',
  },
  {
    key: 'total_logins',
    label: t('report.user_activity.cards.total_logins'),
    value: Number(props.summary?.total_logins || 0),
    tone: 'border-amber-500/30 bg-amber-500/10',
  },
])

const actionCards = computed(() => [
  { key: 'login', label: t('report.user_activity.actions.login'), value: Number(props.meta?.activity_breakdown?.login || 0) },
  { key: 'create', label: t('report.user_activity.actions.create'), value: Number(props.meta?.activity_breakdown?.create || 0) },
  { key: 'update', label: t('report.user_activity.actions.update'), value: Number(props.meta?.activity_breakdown?.update || 0) },
  { key: 'delete', label: t('report.user_activity.actions.delete'), value: Number(props.meta?.activity_breakdown?.delete || 0) },
])

function userInitial(row) {
  return (row?.user_name || row?.email || '?').trim().charAt(0).toUpperCase()
}

function barWidth(value, total) {
  const safeTotal = Number(total || 0)
  if (safeTotal <= 0) return '0%'

  return `${Math.max(8, Math.round((Number(value || 0) / safeTotal) * 100))}%`
}

function sourceTags(row) {
  return props.meta?.top_sources_by_user?.[row.user_id] || []
}

function sourceLabel(sourceKey) {
  const translated = t(`report.user_activity.sources.${sourceKey}`)
  return translated === `report.user_activity.sources.${sourceKey}`
    ? sourceKey.replaceAll('_', ' ')
    : translated
}
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div
        v-for="card in headlineCards"
        :key="card.key"
        class="rounded-2xl border px-5 py-5 shadow-sm"
        :class="card.tone"
      >
        <div class="text-sm text-muted-foreground">{{ card.label }}</div>
        <div class="mt-3 text-3xl font-semibold tracking-tight text-foreground">
          {{ card.value.toLocaleString() }}
        </div>
        <div v-if="card.note" class="mt-2 text-sm text-muted-foreground">
          {{ card.note }}
        </div>
      </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
      <section class="rounded-2xl border border-border bg-card shadow-sm">
        <div class="border-b border-border px-5 py-4">
          <h3 class="text-lg font-semibold text-card-foreground">{{ t('report.user_activity.sections.role_distribution') }}</h3>
        </div>
        <div class="space-y-4 p-5">
          <div
            v-if="!meta?.role_distribution?.length"
            class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
          >
            {{ emptyMessage }}
          </div>

          <div
            v-for="role in meta?.role_distribution || []"
            :key="role.role"
            class="space-y-2 rounded-xl border border-border bg-background px-4 py-4"
          >
            <div class="flex items-center justify-between gap-3">
              <Badge variant="outline" class="border-border text-foreground">{{ role.role }}</Badge>
              <div class="text-sm font-semibold text-card-foreground">{{ Number(role.count || 0).toLocaleString() }}</div>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full bg-amber-500" :style="{ width: barWidth(role.count, summary?.total_users) }" />
            </div>
            <div class="text-xs text-muted-foreground">{{ Number(role.percent || 0).toLocaleString() }}%</div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-border bg-card shadow-sm">
        <div class="border-b border-border px-5 py-4">
          <h3 class="text-lg font-semibold text-card-foreground">{{ t('report.user_activity.sections.top_users') }}</h3>
        </div>
        <div class="space-y-4 p-5">
          <div
            v-if="!meta?.top_users?.length"
            class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
          >
            {{ emptyMessage }}
          </div>

          <div
            v-for="user in meta?.top_users || []"
            :key="user.user_id"
            class="space-y-2 rounded-xl border border-border bg-background px-4 py-4"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="truncate font-semibold text-card-foreground">{{ user.user_name }}</div>
                <div class="truncate text-sm text-muted-foreground">{{ user.email }}</div>
              </div>
              <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-500 font-semibold text-white">
                {{ Number(user.total_activities || 0).toLocaleString() }}
              </div>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full bg-amber-500" :style="{ width: barWidth(user.total_activities, summary?.total_activities) }" />
            </div>
            <div class="flex items-center justify-between gap-3 text-xs text-muted-foreground">
              <span>{{ user.role }}</span>
              <span>{{ user.last_login || '-' }}</span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section class="rounded-2xl border border-border bg-card shadow-sm">
      <div class="border-b border-border px-5 py-4">
        <h3 class="text-lg font-semibold text-card-foreground">{{ t('report.user_activity.sections.activity_summary') }}</h3>
      </div>
      <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
        <div
          v-for="card in actionCards"
          :key="card.key"
          class="rounded-xl border border-border bg-background px-4 py-5 text-center"
        >
          <div class="text-3xl font-semibold tracking-tight text-card-foreground">{{ card.value.toLocaleString() }}</div>
          <div class="mt-2 text-sm text-muted-foreground">{{ card.label }}</div>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-border bg-card shadow-sm">
      <div class="flex flex-col gap-3 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 class="text-lg font-semibold text-card-foreground">{{ t('report.user_activity.sections.user_details') }}</h3>
          <p class="mt-1 text-sm text-muted-foreground">{{ meta?.range_label }}</p>
        </div>
      </div>

      <div class="overflow-x-auto p-3">
        <Table>
          <TableHeader>
            <TableRow class="border-border">
              <TableHead>{{ t('report.user_activity.columns.user') }}</TableHead>
              <TableHead>{{ t('report.user_activity.columns.role') }}</TableHead>
              <TableHead class="text-right">{{ t('report.user_activity.columns.total_activities') }}</TableHead>
              <TableHead class="text-right">{{ t('report.user_activity.columns.logins') }}</TableHead>
              <TableHead class="text-right">{{ t('report.user_activity.columns.creates') }}</TableHead>
              <TableHead class="text-right">{{ t('report.user_activity.columns.updates') }}</TableHead>
              <TableHead class="text-right">{{ t('report.user_activity.columns.deletes') }}</TableHead>
              <TableHead>{{ t('report.user_activity.columns.last_login') }}</TableHead>
              <TableHead>{{ t('report.user_activity.columns.top_sources') }}</TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            <TableRow v-if="!rows.length" class="border-border">
              <TableCell colspan="9" class="py-8 text-center text-sm text-muted-foreground">
                {{ emptyMessage }}
              </TableCell>
            </TableRow>

            <TableRow v-for="row in rows" :key="row.user_id" class="border-border">
              <TableCell class="min-w-[260px]">
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-500 font-semibold text-white">
                    {{ userInitial(row) }}
                  </div>
                  <div class="min-w-0">
                    <div class="truncate font-semibold text-card-foreground">{{ row.user_name }}</div>
                    <div class="truncate text-sm text-muted-foreground">{{ row.email }}</div>
                  </div>
                </div>
              </TableCell>
              <TableCell>
                <Badge variant="outline" class="border-border text-foreground">{{ row.role }}</Badge>
              </TableCell>
              <TableCell class="text-right font-semibold text-amber-500">{{ Number(row.total_activities || 0).toLocaleString() }}</TableCell>
              <TableCell class="text-right">{{ Number(row.logins || 0).toLocaleString() }}</TableCell>
              <TableCell class="text-right">{{ Number(row.creates || 0).toLocaleString() }}</TableCell>
              <TableCell class="text-right">{{ Number(row.updates || 0).toLocaleString() }}</TableCell>
              <TableCell class="text-right">{{ Number(row.deletes || 0).toLocaleString() }}</TableCell>
              <TableCell>{{ row.last_login || '-' }}</TableCell>
              <TableCell class="min-w-[220px]">
                <div class="flex flex-wrap gap-2">
                  <Badge
                    v-for="source in sourceTags(row)"
                    :key="`${row.user_id}-${source.key}`"
                    variant="outline"
                    class="border-border text-foreground"
                  >
                    {{ sourceLabel(source.key) }} ({{ Number(source.count || 0).toLocaleString() }})
                  </Badge>
                  <span v-if="!sourceTags(row).length" class="text-sm text-muted-foreground">-</span>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <div class="flex flex-col gap-3 border-t border-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-muted-foreground">
          {{ t('report.table.showing', {
            from: pagination.from || 0,
            to: pagination.to || 0,
            total: pagination.total || 0,
          }) }}
        </p>

        <div class="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            :disabled="pagination.current_page <= 1"
            @click="emit('page-change', pagination.current_page - 1)"
          >
            {{ t('report.table.previous') }}
          </Button>

          <Button
            v-for="page in pages"
            :key="page"
            :variant="page === pagination.current_page ? 'default' : 'outline'"
            size="sm"
            @click="emit('page-change', page)"
          >
            {{ page }}
          </Button>

          <Button
            variant="outline"
            size="sm"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="emit('page-change', pagination.current_page + 1)"
          >
            {{ t('report.table.next') }}
          </Button>
        </div>
      </div>
    </section>
  </div>
</template>
