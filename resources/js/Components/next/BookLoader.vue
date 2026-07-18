<script setup>
import { computed } from 'vue'

const props = defineProps({
    size: { type: [Number, String], default: 64 },
})

const widthPx = computed(() => `${Number(props.size)}px`)
</script>

<template>
    <div
        class="book-loader"
        role="status"
        :style="{ width: widthPx, '--book-loader-width': widthPx }"
    >
        <div class="book-loader__cover" />
        <div class="book-loader__spine-nub" />

        <div class="book-loader__page book-loader__page--left">
            <span class="book-loader__block" />
            <span class="book-loader__line" v-for="n in 4" :key="`l${n}`" />
        </div>

        <div class="book-loader__page book-loader__page--right">
            <span class="book-loader__line" v-for="n in 2" :key="`r${n}`" />
            <span class="book-loader__block" />
            <span class="book-loader__line" v-for="n in 2" :key="`r2-${n}`" />
        </div>

        <div class="book-loader__flap">
            <div class="book-loader__flap-face book-loader__flap-face--front">
                <span class="book-loader__line" v-for="n in 2" :key="`f${n}`" />
                <span class="book-loader__block" />
                <span class="book-loader__line" v-for="n in 2" :key="`f2-${n}`" />
            </div>
            <div class="book-loader__flap-face book-loader__flap-face--back" />
        </div>
    </div>
</template>

<style scoped>
.book-loader {
    position: relative;
    aspect-ratio: 4 / 3;
    perspective: calc(1.4 * var(--book-loader-width, 64px));
    filter: drop-shadow(0 6px 8px rgb(139 92 246 / 0.25));
}

.book-loader__cover {
    position: absolute;
    inset: 6% 0 0 0;
    border-radius: 6px;
    background: theme('colors.nextbook.yellow.500');
}

.book-loader__spine-nub {
    position: absolute;
    left: 50%;
    bottom: 4%;
    width: 6%;
    height: 10%;
    transform: translateX(-50%);
    background: theme('colors.nextbook.yellow.700');
    clip-path: polygon(0 0, 100% 0, 50% 100%);
}

.book-loader__page {
    position: absolute;
    top: 0;
    bottom: 8%;
    width: 47%;
    padding: 10% 8%;
    background: theme('colors.white');
    display: flex;
    flex-direction: column;
    gap: 8%;
}

.book-loader__page--left {
    left: 1%;
    border-radius: 4px 1px 1px 4px;
    align-items: flex-end;
}

.book-loader__page--right {
    right: 1%;
    border-radius: 1px 4px 4px 1px;
}

.book-loader__line {
    display: block;
    height: 7%;
    width: 100%;
    border-radius: 999px;
    background: theme('colors.nextbook.blue-gray.200');
}

.book-loader__block {
    display: block;
    height: 22%;
    width: 60%;
    border-radius: 2px;
    background: theme('colors.nextbook.purple.100');
}

.book-loader__flap {
    position: absolute;
    top: 0;
    bottom: 8%;
    right: 1%;
    width: 47%;
    transform-style: preserve-3d;
    transform-origin: left center;
    animation: book-loader-flip 2.2s cubic-bezier(0.45, 0, 0.55, 1) infinite;
}

.book-loader__flap-face {
    position: absolute;
    inset: 0;
    backface-visibility: hidden;
    border-radius: 1px 4px 4px 1px;
}

.book-loader__flap-face--front {
    padding: 10% 8%;
    background: theme('colors.white');
    display: flex;
    flex-direction: column;
    gap: 8%;
}

.book-loader__flap-face--back {
    background: theme('colors.nextbook.blue-gray.100');
    transform: rotateY(180deg);
}

@keyframes book-loader-flip {
    0%, 8% {
        transform: rotateY(0deg);
    }
    50%, 58% {
        transform: rotateY(-178deg);
    }
    100% {
        transform: rotateY(-360deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .book-loader__flap {
        animation: none;
    }
}
</style>
