<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { CircleX, Search } from 'lucide-vue-next'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  sections: { type: Array, required: true },
  activeReport: { type: String, required: true },
})

const emit = defineEmits(['select'])
const { t, locale } = useI18n()
const search = ref('')
const isRTL = computed(() => ['fa', 'ps'].includes(String(locale.value).toLowerCase()))

function normalizeText(value) {
  return String(value || '')
    .normalize('NFKC')
    // unify Arabic/Persian letter variants for better matching
    .replace(/[يى]/g, 'ی')
    .replace(/ك/g, 'ک')
    .replace(/ة/g, 'ه')
    // remove Arabic diacritics and tatweel
    .replace(/[\u064B-\u065F\u0670\u06D6-\u06ED\u0640]/g, '')
    // treat ZWNJ and punctuation as spaces for fuzzy contains matching
    .replace(/[\u200c\u200d]/g, ' ')
    .replace(/[_-]/g, ' ')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim()
}

/*
 * One colour per report group, so a report's family is readable at a glance
 * and every card in a section agrees. Colour marks the group, not the single
 * report — forty differently coloured cards would read as noise. Class names
 * are written out in full so Tailwind generates them; each has a lighter
 * shade for dark mode.
 */
const GROUP_TONES = {
  financial: { tile: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300', dot: 'bg-emerald-500', hover: 'hover:border-emerald-500/40' },
  cash_flow: { tile: 'bg-sky-500/15 text-sky-600 dark:text-sky-300', dot: 'bg-sky-500', hover: 'hover:border-sky-500/40' },
  party: { tile: 'bg-violet-500/15 text-violet-600 dark:text-violet-300', dot: 'bg-violet-500', hover: 'hover:border-violet-500/40' },
  inventory: { tile: 'bg-amber-500/15 text-amber-600 dark:text-amber-300', dot: 'bg-amber-500', hover: 'hover:border-amber-500/40' },
  expenses: { tile: 'bg-rose-500/15 text-rose-600 dark:text-rose-300', dot: 'bg-rose-500', hover: 'hover:border-rose-500/40' },
  hr: { tile: 'bg-fuchsia-500/15 text-fuchsia-600 dark:text-fuchsia-300', dot: 'bg-fuchsia-500', hover: 'hover:border-fuchsia-500/40' },
  operations: { tile: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-300', dot: 'bg-indigo-500', hover: 'hover:border-indigo-500/40' },
  management: { tile: 'bg-teal-500/15 text-teal-600 dark:text-teal-300', dot: 'bg-teal-500', hover: 'hover:border-teal-500/40' },
}
const toneFor = (groupKey) => GROUP_TONES[groupKey] ?? GROUP_TONES.party

const normalizedSearch = computed(() => normalizeText(search.value))
const totalReports = computed(() => props.sections.reduce((total, section) => total + (section.reports?.length || 0), 0))

const filteredSections = computed(() => props.sections
  .map((section) => ({
    ...section,
    reports: (section.reports || []).filter((report) => {
      if (!normalizedSearch.value) {
        return true
      }

      if (report.key === props.activeReport) {
        return true
      }

      return [
        report.key,
        report.label,
        report.description,
        section.label,
        section.description,
      ].some((value) => normalizeText(value).includes(normalizedSearch.value))
    }),
  }))
  .filter((section) => section.reports.length))

const visibleReports = computed(() => filteredSections.value.reduce((total, section) => total + section.reports.length, 0))

function clearSearch() {
  search.value = ''
}
</script>

<template>
  <div class="space-y-6">
    <div class="rounded-[28px] border border-border bg-card px-5 py-5 shadow-sm">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-1">
          <h2 class="text-xl font-semibold tracking-tight text-foreground">{{ t('report.catalog_label') }}</h2>
        </div>

        <div class="flex w-full flex-col gap-3 sm:flex-row lg:max-w-xl lg:items-center">
          <div class="relative flex-1">
            <Search
              class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
              :class="isRTL ? 'right-3' : 'left-3'"
            />
            <Input
              v-model="search"
              :placeholder="t('general.search_placeholder', { name: t('report.catalog_label').toLowerCase() })"
              :class="isRTL ? 'pl-10 pr-9' : 'pl-9 pr-10'"
            />
            <button
              v-if="search"
              type="button"
              class="absolute inset-y-0 flex items-center justify-center px-3 text-muted-foreground transition-colors hover:text-foreground"
              :class="isRTL ? 'left-0' : 'right-0'"
              :aria-label="t('general.clear')"
              @click="clearSearch"
            >
              <CircleX class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      <div class="mt-3 text-sm text-muted-foreground">
        {{ visibleReports }} / {{ totalReports }}
      </div>
    </div>

    <div v-if="filteredSections.length" class="space-y-8">
      <section v-for="section in filteredSections" :key="section.key" class="space-y-4">
        <div>
          <h3 class="flex items-center gap-2 text-xl font-semibold tracking-tight text-foreground">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="toneFor(section.key).dot" aria-hidden="true" />
            {{ section.label }}
          </h3>
          <p v-if="section.description" class="text-sm text-muted-foreground">{{ section.description }}</p>
        </div>

        <!-- Compact cards: some forty reports, so four across and one line of
             description keeps far more of the catalogue on screen. The full
             description is on the card's tooltip. -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <button
            v-for="report in section.reports"
            :key="report.key"
            type="button"
            class="group rounded-lg border px-5 py-4 text-left shadow-sm transition-all duration-200 rtl:text-right"
            :class="report.key === activeReport
              ? 'border-emerald-500/50 bg-violet-950 text-white shadow-[0_10px_30px_rgba(6,78,59,0.35)] dark:border-violet-400/30 dark:bg-violet-950'
              : ['border-border bg-card hover:-translate-y-0.5 hover:shadow-md', toneFor(section.key).hover]"
            :title="report.description"
            @click="emit('select', report.key)"
          >
            <div class="flex items-center gap-3">
              <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                :class="report.key === activeReport ? 'bg-white/15 text-white' : toneFor(section.key).tile"
              >
                <component :is="report.icon" class="h-5 w-5" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="truncate text-base font-semibold leading-6" :class="report.key === activeReport ? 'text-white' : 'text-card-foreground'">
                  {{ report.label }}
                </div>
                <div class="mt-0.5 truncate text-[13px] leading-5" :class="report.key === activeReport ? 'text-violet-100/85' : 'text-muted-foreground'">
                  {{ report.description }}
                </div>
              </div>
            </div>
          </button>
        </div>
      </section>
    </div>

    <div v-else class="rounded-[28px] border border-dashed border-border bg-card px-6 py-12 text-center shadow-sm">
      <p class="text-lg font-semibold text-foreground">{{ t('general.no_data_found') }}</p>
      <button
        v-if="search"
        type="button"
        class="mt-4 inline-flex items-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground"
        @click="clearSearch"
      >
        <CircleX class="h-4 w-4" />
        {{ t('general.clear') }}
      </button>
    </div>
  </div>
</template>
