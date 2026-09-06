<script setup>
import { BookOpen, Search } from 'lucide-vue-next'
import { Input } from '@/Components/ui/input'
import { MANUAL_LOCALES } from './content'

defineProps({
    t: { type: Function, required: true },
    manual: { type: Object, required: true },
    manualLocale: { type: String, required: true },
    query: { type: String, required: true },
    activeId: { type: String, required: true },
    filteredChapters: { type: Array, required: true },
})

const emit = defineEmits(['update:query', 'set-locale', 'go'])
</script>

<template>
    <div class="space-y-4">
        <div>
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                {{ t('user_manual.language') }}
            </p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="item in MANUAL_LOCALES"
                    :key="item.id"
                    type="button"
                    class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
                    :class="manualLocale === item.id
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-muted text-foreground hover:bg-accent'"
                    @click="emit('set-locale', item.id)"
                >
                    {{ item.label }}
                </button>
            </div>
        </div>

        <div class="relative">
            <Search class="pointer-events-none absolute top-2.5 size-3.5 text-muted-foreground ltr:left-2.5 rtl:right-2.5" />
            <Input
                :model-value="query"
                :placeholder="t('user_manual.search_placeholder')"
                class="h-9 bg-background ltr:pl-8 rtl:pr-8"
                @update:model-value="emit('update:query', $event)"
            />
        </div>

        <nav class="space-y-0.5 text-sm">
            <button
                type="button"
                data-toc-id="cover"
                class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-start transition"
                :class="activeId === 'cover'
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                @click="emit('go', 'cover')"
            >
                <BookOpen class="size-3.5 shrink-0" />
                <span>{{ manual.title }}</span>
            </button>
            <button
                v-for="chapter in filteredChapters"
                :key="chapter.id"
                type="button"
                :data-toc-id="chapter.id"
                class="flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-start transition"
                :class="activeId === chapter.id
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                @click="emit('go', chapter.id)"
            >
                <span
                    class="mt-0.5 w-5 shrink-0 text-xs font-bold"
                    :class="activeId === chapter.id ? 'text-primary-foreground' : 'text-primary'"
                >{{ chapter.number }}</span>
                <span>{{ chapter.title }}</span>
            </button>
        </nav>
    </div>
</template>
