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

const normalizedSearch = computed(() => search.value.trim().toLowerCase())
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
      ].some((value) => String(value || '').toLowerCase().includes(normalizedSearch.value))
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
          <h3 class="text-xl font-semibold tracking-tight text-foreground">{{ section.label }}</h3>
          <p v-if="section.description" class="text-sm text-muted-foreground">{{ section.description }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <button
            v-for="report in section.reports"
            :key="report.key"
            type="button"
            class="group rounded-2xl border px-5 py-5 text-left shadow-sm transition-all duration-200 rtl:text-right"
            :class="report.key === activeReport
              ? 'border-emerald-500/50 bg-violet-950 text-white shadow-[0_14px_40px_rgba(6,78,59,0.35)] dark:border-violet-400/30 dark:bg-violet-950'
              : 'border-border bg-card hover:-translate-y-0.5 hover:border-violet-500/30 hover:shadow-md'"
            @click="emit('select', report.key)"
          >
            <div class="flex items-start gap-4">
              <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-lg shadow-sm"
                :class="report.key === activeReport ? 'bg-white/12 text-white' : 'bg-violet-500/12 text-violet-600 dark:text-violet-300'"
              >
                <component :is="report.icon" class="h-6 w-6" />
              </div>
              <div class="min-w-0">
                <div class="text-lg font-semibold leading-6" :class="report.key === activeReport ? 'text-white' : 'text-card-foreground'">
                  {{ report.label }}
                </div>
                <div class="mt-1 text-sm leading-6" :class="report.key === activeReport ? 'text-violet-100/85' : 'text-muted-foreground'">
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
