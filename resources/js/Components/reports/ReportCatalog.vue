<script setup>
const props = defineProps({
  sections: { type: Array, required: true },
  activeReport: { type: String, required: true },
})

const emit = defineEmits(['select'])
</script>

<template>
  <div class="space-y-8">
    <section v-for="section in sections" :key="section.key" class="space-y-4">
      <div>
        <h2 class="text-xl font-semibold tracking-tight text-foreground">{{ section.label }}</h2>
        <p v-if="section.description" class="text-sm text-muted-foreground">{{ section.description }}</p>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <button
          v-for="report in section.reports"
          :key="report.key"
          type="button"
          class="group rounded-2xl border px-5 py-5 text-left shadow-sm transition-all duration-200"
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
</template>
