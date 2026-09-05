<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    // Exactly two entries: [{ key, label }]. series[0] drives the headline stat.
    series: { type: Array, required: true },
    // [{ date, values: { [seriesKey]: number } }], ascending by date.
    points: { type: Array, required: true },
    formatValue: {
        type: Function,
        default: (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    },
});

const showTable = ref(false);
const hoverIndex = ref(null);

// Fixed categorical pair, reused across the app: violet reads as the
// "outgoing" half, green as the "incoming" half.
const seriesColorVars = ['--nb-chart-series-a', '--nb-chart-series-b'];

const width = 720;
const height = 260;
const marginTop = 16;
const marginBottom = 26;
const marginLeft = 52;
const marginRight = 16;
const plotWidth = width - marginLeft - marginRight;
const plotHeight = height - marginTop - marginBottom;

const totals = computed(() => props.series.map((s) => props.points.reduce((sum, p) => sum + Number(p.values?.[s.key] || 0), 0)));

const maxValue = computed(() => {
    const max = Math.max(0, ...props.points.flatMap((p) => props.series.map((s) => Number(p.values?.[s.key] || 0))));
    return max > 0 ? max : 1;
});

const gridLines = computed(() => [0, 0.25, 0.5, 0.75, 1].map((fraction) => ({
    y: marginTop + plotHeight * (1 - fraction),
    label: props.formatValue(maxValue.value * fraction),
})));

const pointCount = computed(() => props.points.length);

const xAt = (index) => (pointCount.value <= 1
    ? marginLeft + plotWidth / 2
    : marginLeft + (plotWidth * index) / (pointCount.value - 1));

const yAt = (value) => marginTop + plotHeight * (1 - Number(value || 0) / maxValue.value);

const seriesLines = computed(() => props.series.map((s, seriesIndex) => ({
    key: s.key,
    label: s.label,
    colorVar: seriesColorVars[seriesIndex],
    path: props.points.map((p, i) => `${i === 0 ? 'M' : 'L'}${xAt(i)},${yAt(p.values?.[s.key])}`).join(' '),
    dots: props.points.map((p, i) => ({ x: xAt(i), y: yAt(p.values?.[s.key]) })),
})));

// Up to 4 evenly spread x-axis ticks so labels never overlap.
const xTicks = computed(() => {
    if (pointCount.value === 0) return [];
    if (pointCount.value <= 4) return props.points.map((p, i) => ({ index: i, label: p.date }));
    const indices = new Set([0, Math.round((pointCount.value - 1) / 3), Math.round(((pointCount.value - 1) * 2) / 3), pointCount.value - 1]);
    return Array.from(indices).sort((a, b) => a - b).map((i) => ({ index: i, label: props.points[i].date }));
});

const plotRef = ref(null);

const updateHover = (event) => {
    if (!pointCount.value) return;
    const svg = plotRef.value;
    if (!svg) return;
    const rect = svg.getBoundingClientRect();
    const scale = width / rect.width;
    const localX = (event.clientX - rect.left) * scale;
    const ratio = pointCount.value <= 1 ? 0 : (localX - marginLeft) / plotWidth;
    const index = Math.round(ratio * (pointCount.value - 1));
    hoverIndex.value = Math.min(pointCount.value - 1, Math.max(0, index));
};

const clearHover = () => {
    hoverIndex.value = null;
};

const hoverPoint = computed(() => (hoverIndex.value === null ? null : props.points[hoverIndex.value]));
const hoverX = computed(() => (hoverIndex.value === null ? 0 : xAt(hoverIndex.value)));
</script>

<template>
    <div class="nb-chart space-y-4">
        <!-- Header: title/subtitle + headline stat -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 v-if="title" class="text-sm font-semibold text-foreground">{{ title }}</h3>
                <p v-if="subtitle" class="text-xs text-muted-foreground mt-0.5">{{ subtitle }}</p>
            </div>
            <div v-if="series[0]" class="text-end">
                <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">{{ series[0].label }}</div>
                <div class="text-2xl font-bold text-foreground tabular-nums leading-tight">{{ formatValue(totals[0]) }}</div>
                <div v-if="series[1]" class="text-xs text-muted-foreground">
                    {{ t('general.vs') }} {{ series[1].label }} {{ formatValue(totals[1]) }}
                </div>
            </div>
        </div>

        <!-- Legend + table toggle -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <div v-for="s in seriesLines" :key="s.key" class="flex items-center gap-1.5 text-xs">
                    <span class="h-1.5 w-4 shrink-0 rounded-full" :style="{ backgroundColor: `var(${s.colorVar})` }" />
                    <span class="text-foreground font-medium">{{ s.label }}</span>
                </div>
            </div>
            <button
                type="button"
                class="rounded-md border border-border px-2 py-1 text-xs text-muted-foreground hover:text-foreground hover:bg-muted transition"
                @click="showTable = !showTable"
            >
                {{ showTable ? t('general.chart') : t('general.table') }}
            </button>
        </div>

        <div v-if="!points.length" class="py-8 text-center text-sm text-muted-foreground">
            {{ t('general.no_chart_data') }}
        </div>

        <div v-else-if="showTable" class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-muted/60 text-muted-foreground">
                        <th class="py-2 px-3 text-start font-medium whitespace-nowrap">{{ t('general.date') }}</th>
                        <th v-for="s in series" :key="s.key" class="py-2 px-3 text-end font-medium whitespace-nowrap">{{ s.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="point in points" :key="point.date" class="border-t border-border/60">
                        <td class="py-2 px-3 whitespace-nowrap font-medium text-foreground">{{ point.date }}</td>
                        <td v-for="s in series" :key="s.key" class="py-2 px-3 text-end whitespace-nowrap tabular-nums text-foreground">
                            {{ formatValue(point.values?.[s.key] || 0) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="relative">
            <svg
                ref="plotRef"
                :viewBox="`0 0 ${width} ${height}`"
                class="w-full"
                :style="{ height: `${height}px` }"
                role="img"
                :aria-label="title"
                @pointermove="updateHover"
                @pointerleave="clearHover"
            >
                <!-- Gridlines -->
                <g v-for="line in gridLines" :key="line.y">
                    <line :x1="marginLeft" :x2="width - marginRight" :y1="line.y" :y2="line.y" class="nb-chart-grid" />
                    <text :x="marginLeft - 8" :y="line.y" text-anchor="end" dominant-baseline="middle" class="nb-chart-tick">{{ line.label }}</text>
                </g>

                <!-- X-axis ticks -->
                <text
                    v-for="tick in xTicks"
                    :key="tick.index"
                    :x="xAt(tick.index)"
                    :y="height - 6"
                    :text-anchor="tick.index === 0 ? 'start' : tick.index === pointCount - 1 ? 'end' : 'middle'"
                    class="nb-chart-category"
                >{{ tick.label }}</text>

                <!-- Series lines -->
                <g v-for="s in seriesLines" :key="s.key">
                    <path :d="s.path" fill="none" :style="{ stroke: `var(${s.colorVar})` }" class="nb-chart-line" />
                </g>

                <!-- Crosshair + markers -->
                <template v-if="hoverPoint">
                    <line :x1="hoverX" :x2="hoverX" :y1="marginTop" :y2="marginTop + plotHeight" class="nb-chart-crosshair" />
                    <circle
                        v-for="s in seriesLines"
                        :key="s.key"
                        :cx="hoverX"
                        :cy="yAt(hoverPoint.values?.[s.key])"
                        r="3.5"
                        :style="{ fill: `var(${s.colorVar})` }"
                        class="nb-chart-dot"
                    />
                </template>
            </svg>

            <div
                v-if="hoverPoint"
                class="pointer-events-none absolute z-10 -translate-y-1/2 rounded-md border border-border bg-popover px-2.5 py-1.5 text-xs shadow-md"
                :style="{ left: `${Math.min(88, (hoverX / width) * 100)}%`, top: `${marginTop + 8}px` }"
            >
                <div class="font-semibold text-popover-foreground mb-1">{{ hoverPoint.date }}</div>
                <div v-for="s in seriesLines" :key="s.key" class="flex items-center gap-1.5">
                    <span class="h-0.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: `var(${s.colorVar})` }" />
                    <span class="font-semibold text-popover-foreground tabular-nums">{{ formatValue(hoverPoint.values?.[s.key] || 0) }}</span>
                    <span class="text-muted-foreground">{{ s.label }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.nb-chart {
    --nb-chart-series-a: #4a3aa7;
    --nb-chart-series-b: #008300;
    --nb-chart-grid: #e1e0d9;
    --nb-chart-baseline: #c3c2b7;
    --nb-chart-tick: #898781;
}

:global(.dark) .nb-chart {
    --nb-chart-series-a: #9085e9;
    --nb-chart-series-b: #008300;
    --nb-chart-grid: #2c2c2a;
    --nb-chart-baseline: #383835;
    --nb-chart-tick: #898781;
}

.nb-chart-grid {
    stroke: var(--nb-chart-grid);
    stroke-width: 1;
}

.nb-chart-tick,
.nb-chart-category {
    font-size: 10px;
    fill: var(--nb-chart-tick);
}

.nb-chart-line {
    stroke-width: 2;
    stroke-linejoin: round;
    stroke-linecap: round;
}

.nb-chart-crosshair {
    stroke: var(--nb-chart-baseline);
    stroke-width: 1;
    stroke-dasharray: 3 3;
}

.nb-chart-dot {
    stroke: hsl(var(--card));
    stroke-width: 1.5;
}
</style>
