<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Link } from '@inertiajs/vue3'
import { ArrowUpRight } from 'lucide-vue-next'
import AnimatedNumber from './AnimatedNumber.vue'
import { formatNumber } from './format'

const props = defineProps({
  position: { type: Object, default: () => ({}) },
  href: { type: String, default: '' },
})

const { t } = useI18n()

const lines = computed(() => props.position?.lines || [])
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4 px-5 pt-5">
      <div>
        <h2 class="text-base font-semibold text-card-foreground">{{ t('dashboard.cash_position.title') }}</h2>
        <p class="mt-0.5 text-xs text-muted-foreground">{{ t('dashboard.cash_position.description') }}</p>
      </div>
      <Link
        v-if="href"
        :href="href"
        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
      >
        {{ t('dashboard.cash_position.open_report') }}
        <ArrowUpRight class="h-3.5 w-3.5" />
      </Link>
    </div>

    <div class="mt-4 overflow-x-auto px-5 pb-5">
      <div v-if="!lines.length" class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
        {{ t('dashboard.no_data_available') }}
      </div>

      <table v-else class="w-full border-collapse text-sm">
        <thead>
          <tr class="text-[11px] uppercase tracking-wide text-muted-foreground">
            <th class="w-10 pb-2 text-start font-medium">#</th>
            <th class="pb-2 text-start font-medium">{{ t('dashboard.cash_position.currency') }}</th>
            <th class="pb-2 text-start font-medium">{{ t('dashboard.cash_position.currency_name') }}</th>
            <th class="pb-2 text-end font-medium">{{ t('dashboard.cash_position.amount') }}</th>
            <th class="pb-2 text-end font-medium">{{ t('dashboard.cash_position.home_equivalent') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(line, index) in lines"
            :key="line.currency"
            class="border-t border-dashed border-border"
          >
            <td class="py-3 text-muted-foreground [font-variant-numeric:tabular-nums]">{{ index + 1 }}</td>
            <td class="py-3 pe-3 font-medium text-card-foreground">{{ line.currency }}</td>
            <td class="py-3 pe-3 text-muted-foreground">{{ line.currency_name }}</td>
            <td class="py-3 text-end font-medium text-card-foreground [font-variant-numeric:tabular-nums]">
              {{ formatNumber(line.amount) }}
              <span class="ms-1 text-xs text-muted-foreground">
                {{ line.nature === 'cr' ? t('dashboard.cash_position.cr') : t('dashboard.cash_position.dr') }}
              </span>
            </td>
            <td class="py-3 text-end font-medium text-card-foreground [font-variant-numeric:tabular-nums]">
              {{ formatNumber(line.home_equivalent) }}
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="border-t border-border">
            <td colspan="4" class="py-3 text-end text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {{ t('dashboard.cash_position.total_home_equivalent') }}
            </td>
            <td class="py-3 text-end text-base font-semibold text-card-foreground [font-variant-numeric:tabular-nums]">
              <AnimatedNumber :value="position?.total_home_equivalent" />
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>
