<template>
    <div class="relative">
        <!-- Flowbite outlined floating label, using a real border gap.

             A painted label mask (Flowbite's bg-white / dark:bg-gray-900) only
             holds on a flat page. These fields sit on glass, so the outline is
             a <fieldset>: the browser cuts the top border around <legend> and
             nothing has to be coloured in. -->
        <div class="relative">
            <Input
                ref="inputRef"
                :id="id"
                :type="type"
                :autofocus="autofocus"
                v-model="model"
                :step="step"
                :disabled="disabled"
                :readonly="readonly"
                :autocomplete="autocomplete"
                :placeholder="placeholder || ' '"
                @click="handleClick"
                class="next-input-field peer relative z-0 block w-full appearance-none rounded-lg border-transparent bg-transparent px-2.5 text-sm shadow-none
                placeholder:text-transparent focus:border-transparent focus:outline-none focus:ring-0 focus-visible:ring-0 focus-visible:ring-offset-0 focus:placeholder:text-muted-foreground
                disabled:cursor-not-allowed disabled:opacity-50"
            />

            <fieldset
                aria-hidden="true"
                class="next-input-outline pointer-events-none absolute inset-0 z-10 m-0 min-w-0 overflow-visible rounded-lg border border-border bg-transparent px-2 py-0 text-start
                peer-disabled:opacity-50"
            >
                <legend>
                    <span>
                        {{ label }}
                        <span v-if="isRequired">*</span>
                    </span>
                </legend>
            </fieldset>

            <label
                :for="id"
                class="next-input-label pointer-events-none absolute start-2 z-20 px-1.5 text-sm text-muted-foreground">
                {{ label }}
                <span v-if="isRequired" class="text-red-500 ms-0.5">*</span>
            </label>
        </div>

        <!-- error text -->
        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
        <p v-else-if="hint && showHints" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Input from '@/Components/ui/input/Input.vue'

// Inline hints are a per-user preference (appearance.show_field_hints), on
// unless the user turns them off. Errors are never suppressed.
const showHints = computed(() => {
    try {
        return usePage().props.user_preferences?.appearance?.show_field_hints !== false
    } catch (e) {
        return true
    }
})

const props = defineProps({
    modelValue: [String, Number],
    label: { type: String, required: true },
    id: { type: String, default: () => `fi-${Math.random().toString(36).slice(2, 9)}` },
    type: { type: String, default: 'text' },
    autofocus: { type: Boolean, default: false },
    disabled: Boolean,
    isRequired: Boolean,
    readonly: { type: Boolean, default: false },
    autocomplete: { type: String, default: 'off' },
    error: String,
    step: { type: String, default: 'any' },
    hint: String,
    placeholder: String,
    input: Function,
})

const emit = defineEmits(['update:modelValue'])
const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v),
})

const inputRef = ref(null)
function handleClick(e) {
    // Call any click listener provided via $emit
    // Select input value if enabled
    if (!props.disabled && inputRef.value) {
        // The actual input ref may be on the nexted Input, so dig if necessary
        // Vue 3 <script setup> exposes .$el on the component ref
        let el = inputRef.value.$el || inputRef.value
        // If the inner element is an input, select it
        if (el && typeof el.querySelector === 'function') {
            const realInput = el.querySelector('input') || el
            if (realInput && typeof realInput.select === 'function') {
                realInput.select()
            }
        } else if (el && typeof el.select === 'function') {
            el.select()
        }
    }
    // Emit click upwards as before
    // NOTE: this still allows the user of the component to attach their own handler
    // after select is called
    emit('click', e)
}

defineExpose({
    focus: () => inputRef.value?.focus?.(),
})
</script>

<style scoped>
/* The legend is only a spacer: zero height so it does not inflate the
   control, and max-width 0.01px while the label is inside the box so the
   top border stays closed. Opening it to 100% is what cuts the Flowbite gap. */
.next-input-outline legend {
    display: block;
    float: none;
    width: auto;
    height: 0;
    max-width: 0.01px;
    padding: 0;
    overflow: hidden;
    visibility: hidden;
    white-space: nowrap;
    /* The gap is only as wide as this legend, so it has to be measured at the
       exact size the label renders at — the same expression, not a constant.
       These two drifting apart is what left a stub of border poking out from
       under the word. */
    font-size: calc(var(--app-label-font-size, 14px) * 0.8);
    line-height: 0;
    background: transparent;
    transition: max-width 150ms ease;
}

.next-input-outline legend span {
    display: inline-block;
    visibility: visible;
    /* Matches the label's own px-1.5 so the gap lines up with its box rather
       than its text. */
    padding: 0 0.375rem;
    opacity: 0;
}

/* The label spaces its asterisk with ms-0.5; the legend has to spend the same
   width or the gap falls short by exactly that margin. */
.next-input-outline legend span span {
    margin-inline-start: 0.125rem;
}

.next-input-label {
    top: 50%;
    transform: translateY(-50%);
    transform-origin: 0 0;
    transition: top 150ms ease, font-size 150ms ease, color 150ms ease, transform 150ms ease;
}

:dir(rtl) .next-input-label {
    transform-origin: 100% 0;
}

:deep(.next-input-field:focus) ~ .next-input-outline,
:deep(.next-input-field:focus-visible) ~ .next-input-outline {
    border-color: hsl(var(--ring));
    box-shadow: 0 0 0 1px hsl(var(--ring) / 0.35);
}

:deep(.next-input-field:focus) ~ .next-input-outline legend,
:deep(.next-input-field:focus-visible) ~ .next-input-outline legend,
:deep(.next-input-field:not(:placeholder-shown)) ~ .next-input-outline legend {
    max-width: 100%;
}

/* `label { font-size: var(--app-label-font-size) !important }` in app.css beats
   a plain declaration here, so the floated label never actually shrank and sat
   in the gap at full size. !important is the only way past it; the size still
   derives from the user's font preference rather than being pinned, and the
   legend above reserves its width from the identical expression. */
:deep(.next-input-field:focus) ~ .next-input-label,
:deep(.next-input-field:focus-visible) ~ .next-input-label,
:deep(.next-input-field:not(:placeholder-shown)) ~ .next-input-label {
    top: 0;
    font-size: calc(var(--app-label-font-size, 14px) * 0.8) !important;
}

:deep(.next-input-field:focus) ~ .next-input-label,
:deep(.next-input-field:focus-visible) ~ .next-input-label {
    color: hsl(var(--ring));
}

:deep(input[type="number"]) {
    appearance: textfield;
    -moz-appearance: textfield;
}

:deep(input[type="number"])::-webkit-outer-spin-button,
:deep(input[type="number"])::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>
