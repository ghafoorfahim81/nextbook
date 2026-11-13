<script setup>
/**
 * Zoom and pan editor for an uploaded picture. The picture starts fitted whole
 * inside a square and can be dragged and zoomed; on apply it emits a square PNG
 * File of exactly what the frame shows (transparent where the picture does not
 * reach), together with a data URL for previews.
 */
import { nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'

const props = defineProps({
    file: { type: File, required: true },
    round: { type: Boolean, default: false },
    output: { type: Number, default: 512 },
    filename: { type: String, default: 'image.png' },
})
const emit = defineEmits(['apply', 'cancel'])
const { t } = useI18n()

const VIEW = 288
const canvas = ref(null)
const zoom = ref(1)
const pos = ref({ x: 0, y: 0 })
let img = null
let drag = null

function draw(target, size) {
    if (!target || !img) return
    const k = size / VIEW
    const s = Math.min(size / img.width, size / img.height) * zoom.value
    const ctx = target.getContext('2d')
    ctx.clearRect(0, 0, size, size)
    ctx.drawImage(
        img,
        size / 2 + pos.value.x * k - (img.width * s) / 2,
        size / 2 + pos.value.y * k - (img.height * s) / 2,
        img.width * s,
        img.height * s,
    )
}
const redraw = () => draw(canvas.value, VIEW)

onMounted(() => {
    const url = URL.createObjectURL(props.file)
    const image = new Image()
    image.onload = () => { img = image; nextTick(redraw) }
    image.src = url
})

function down(e) {
    drag = { x: e.clientX, y: e.clientY, ox: pos.value.x, oy: pos.value.y }
    e.currentTarget.setPointerCapture(e.pointerId)
}
function move(e) {
    if (!drag) return
    pos.value = { x: drag.ox + e.clientX - drag.x, y: drag.oy + e.clientY - drag.y }
    redraw()
}
const up = () => { drag = null }
function wheel(e) {
    zoom.value = Math.min(5, Math.max(0.5, zoom.value - e.deltaY * 0.002))
    redraw()
}

function apply() {
    const out = document.createElement('canvas')
    out.width = out.height = props.output
    draw(out, props.output)
    out.toBlob((blob) => {
        if (!blob) return
        emit('apply', new File([blob], props.filename, { type: 'image/png' }), out.toDataURL('image/png'))
    }, 'image/png')
}
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-sm space-y-4 rounded-xl border border-border bg-background p-5 shadow-xl">
                <div>
                    <p class="font-semibold text-foreground">{{ t('profile.profile.avatar.adjust_title') }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('profile.profile.avatar.adjust_hint') }}</p>
                </div>
                <div class="relative mx-auto touch-none select-none" :style="{ width: VIEW + 'px', height: VIEW + 'px', maxWidth: '100%' }">
                    <canvas
                        ref="canvas"
                        :width="VIEW"
                        :height="VIEW"
                        class="h-full w-full cursor-grab rounded-lg bg-muted active:cursor-grabbing"
                        @pointerdown="down"
                        @pointermove="move"
                        @pointerup="up"
                        @pointercancel="up"
                        @wheel.prevent="wheel"
                    />
                    <div
                        class="pointer-events-none absolute inset-0 ring-2 ring-white/80"
                        :class="round ? 'rounded-full' : 'rounded-xl'"
                        style="box-shadow: 0 0 0 999px rgba(0,0,0,0.45)"
                    />
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-muted-foreground">{{ t('profile.profile.avatar.zoom') }}</span>
                    <input v-model.number="zoom" type="range" min="0.5" max="5" step="0.01" class="flex-1 accent-violet-500" @input="redraw" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="emit('cancel')">{{ t('profile.profile.avatar.cancel') }}</Button>
                    <Button type="button" @click="apply">{{ t('profile.profile.avatar.apply') }}</Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
