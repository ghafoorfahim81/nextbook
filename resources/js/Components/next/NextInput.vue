<template>
    <div class="relative">
        <!-- Flowbite outlined floating label, using a real border gap.

             A painted label mask (Flowbite's bg-white / dark:bg-gray-900) only
             holds on a flat page. These fields sit on glass, so the outline is
             a <fieldset>: the browser cuts the top border around <legend> and
             nothing has to be coloured in. -->
        <div class="next-input-shell relative">
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
                class="next-input-field peer relative z-0 block w-full appearance-none rounded-lg border-transparent bg-transparent text-sm shadow-none
                placeholder:text-transparent focus:border-transparent focus:outline-none focus:ring-0 focus-visible:ring-0 focus-visible:ring-offset-0 focus:placeholder:text-muted-foreground
                disabled:cursor-not-allowed disabled:opacity-50"
            />

            <fieldset
                aria-hidden="true"
                class="next-input-outline pointer-events-none absolute inset-0 z-10 m-0 min-w-0 overflow-visible rounded-lg border border-border bg-transparent py-0 text-start
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
                class="next-input-label pointer-events-none absolute z-20 text-sm text-muted-foreground">
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
/* One notch, measured once.
 *
 * Where the gap starts, where the label sits and where the field's own text
 * begins are three readings of the same measurement, and they were three
 * separate literals (fieldset px-2, label start-2, input px-2.5) that could
 * not agree: the label's box began 2px left of the gap, and the field's text
 * began 4px left of the label — so the word shifted sideways as it floated.
 *
 * The gap also has to start where the corner arc ends. The arc owns the first
 * --radius of the top edge; a notch cut into it leaves the curve hanging as a
 * detached hook, because it is severed while still sloping. At the 0.5rem
 * radius this was built against the two coincided and it never showed, then
 * the glass surface raised --radius to 0.9rem and the hook appeared.
 */
.next-input-shell {
    --next-input-notch: max(var(--radius, 0.5rem), 0.5rem);
    --next-input-label-pad: 0.375rem;
}

/* inset-0 + border-box means content starts at border + padding, so the
   padding carries the border's width and the notch holds still when focus
   thickens it — otherwise focus slid the gap 1px right of the label and left
   a sliver of border against the first letter. */
.next-input-outline {
    padding-inline: calc(var(--next-input-notch) - 1px);
}

/* Text lines up under the label rather than 4px inside it, so floating the
   label is a move up, not up and across. */
:deep(.next-input-field) {
    padding-inline: calc(var(--next-input-notch) + var(--next-input-label-pad));
}

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

/* Direct child only. As a descendant selector this also matched the asterisk's
   own span, spending the padding twice and making the gap exactly 12px too
   wide — but only on required fields, which is why it read as an asterisk bug
   rather than a padding one. */
.next-input-outline legend > span {
    display: inline-block;
    visibility: visible;
    /* The same padding the label spends, from the same variable, so the gap
       is its box and not its text — and so the clearance is even on both
       sides instead of 4px in and 8px out. */
    padding: 0 var(--next-input-label-pad);
    opacity: 0;
}

/* The label spaces its asterisk with ms-0.5; the legend has to spend the same
   width or the gap falls short by exactly that margin. */
.next-input-outline legend > span > span {
    margin-inline-start: 0.125rem;
}

.next-input-label {
    inset-inline-start: var(--next-input-notch);
    padding-inline: var(--next-input-label-pad);
    top: 50%;
    transform: translateY(-50%);
    transform-origin: 0 0;
    transition: top 150ms ease, font-size 150ms ease, color 150ms ease, transform 150ms ease;
}

:dir(rtl) .next-input-label {
    transform-origin: 100% 0;
}

/* The focus ring has to BE the border, not a shadow around it. A box-shadow is
   painted from the border box and knows nothing about the <legend> — the gap
   cuts the border only, so the ring closed back over it and drew a line
   through the label. Thickening the border keeps the same weight with the gap
   intact; the fieldset is inset-0 and border-box, so the extra pixel grows
   inward and nothing shifts. */
:deep(.next-input-field:focus) ~ .next-input-outline,
:deep(.next-input-field:focus-visible) ~ .next-input-outline {
    border-color: hsl(var(--ring));
    border-width: 2px;
    padding-inline: calc(var(--next-input-notch) - 2px);
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
