<template>
    <div ref="rootRef" class="relative">
        <div
            class="flex h-10 w-full items-stretch overflow-hidden rounded-md border bg-background shadow-sm transition-colors focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 focus-within:ring-offset-background"
            :class="error ? 'border-red-500' : 'border-border'"
        >
            <!-- Country / dial code picker -->
            <button
                type="button"
                class="flex shrink-0 items-center gap-1 border-e border-border px-2 text-sm text-foreground transition-colors hover:bg-muted/60 focus:outline-none"
                :aria-label="t('ledger.country')"
                aria-haspopup="listbox"
                :aria-expanded="open"
                @click="toggleDropdown"
            >
                <span class="text-base leading-none">{{ countryFlag(country.iso) }}</span>
                <span class="tabular-nums" dir="ltr">+{{ country.dial }}</span>
                <ChevronDown class="h-3.5 w-3.5 text-muted-foreground" />
            </button>

            <input
                ref="inputRef"
                :id="id"
                type="tel"
                inputmode="tel"
                autocomplete="tel"
                dir="ltr"
                :placeholder="placeholder || examplePlaceholder"
                :value="nationalDisplay"
                class="min-w-0 flex-1 bg-transparent px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none"
                @input="onInput"
                @paste="onPaste"
            />
        </div>

        <label
            :for="id"
            class="pointer-events-none absolute start-3 top-0 z-10 -translate-y-1/2 rounded bg-background px-1 text-xs text-muted-foreground"
        >
            {{ label }}<span v-if="isRequired" class="ms-0.5 text-red-500">*</span>
        </label>

        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
        <p v-else-if="lengthHint" class="mt-1 text-xs text-amber-600 dark:text-amber-500">{{ lengthHint }}</p>

        <!-- Country list -->
        <div
            v-if="open"
            class="absolute z-50 mt-1 w-72 max-w-[calc(100vw-2rem)] overflow-hidden rounded-md border border-border bg-popover text-popover-foreground shadow-lg"
        >
            <div class="border-b border-border p-2">
                <input
                    ref="searchRef"
                    v-model="search"
                    type="text"
                    :placeholder="t('general.search')"
                    class="h-8 w-full rounded border border-border bg-background px-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    @keydown.esc.prevent="closeDropdown"
                />
            </div>
            <ul class="max-h-64 overflow-y-auto py-1" role="listbox">
                <li v-if="!filteredCountries.length" class="px-3 py-2 text-sm text-muted-foreground">
                    {{ t('general.no_data_found') }}
                </li>
                <li v-for="option in filteredCountries" :key="option.iso">
                    <button
                        type="button"
                        role="option"
                        :aria-selected="option.iso === country.iso"
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-start text-sm transition-colors hover:bg-muted"
                        :class="option.iso === country.iso ? 'bg-muted font-medium' : ''"
                        @click="selectCountry(option)"
                    >
                        <span class="text-base leading-none">{{ countryFlag(option.iso) }}</span>
                        <span class="flex-1 truncate">{{ option.name }}</span>
                        <span class="tabular-nums text-muted-foreground" dir="ltr">+{{ option.dial }}</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { ChevronDown } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import {
    PHONE_COUNTRIES,
    countryFlag,
    findCountry,
    findCountryByDial,
    groupNationalDigits,
    maxNationalDigits,
    minNationalDigits,
} from '@/utils/phoneCountries';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    id: { type: String, default: () => `phone-${Math.random().toString(36).slice(2, 9)}` },
    error: String,
    placeholder: String,
    /** ISO-2 code used when the stored value carries no country code. */
    defaultCountry: { type: String, default: 'AF' },
    isRequired: Boolean,
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const rootRef = ref(null);
const inputRef = ref(null);
const searchRef = ref(null);
const open = ref(false);
const search = ref('');

const fallbackCountry = computed(() => findCountry(props.defaultCountry) ?? PHONE_COUNTRIES[0]);
const selectedIso = ref(fallbackCountry.value.iso);
const country = computed(() => findCountry(selectedIso.value) ?? fallbackCountry.value);

const national = ref('');
const nationalDisplay = computed(() => groupNationalDigits(national.value));

const maxDigits = computed(() => maxNationalDigits(country.value));

const examplePlaceholder = computed(() => groupNationalDigits('#'.repeat(maxDigits.value)));

/** Only warn once the number looks finished enough to judge. */
const lengthHint = computed(() => {
    if (!national.value) return '';

    const min = minNationalDigits(country.value);
    const allowed = country.value.len;

    if (allowed?.length ? allowed.includes(national.value.length) : national.value.length >= min) {
        return '';
    }

    return t('ledger.invalid_phone_for_country', { country: country.value.name });
});

const filteredCountries = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return PHONE_COUNTRIES;

    const digits = term.replace(/\D/g, '');

    return PHONE_COUNTRIES.filter((option) => option.name.toLowerCase().includes(term)
        || option.iso.toLowerCase().includes(term)
        || (digits && option.dial.startsWith(digits)));
});

const onlyDigits = (value) => String(value ?? '').replace(/\D/g, '');

/**
 * Split a stored value into country + national digits. Values written in the
 * local style ("0773502152") carry no country code, so they are read against
 * the currently selected one with the trunk "0" stripped.
 */
function parse(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return { iso: selectedIso.value, national: '' };

    const isInternational = raw.startsWith('+') || raw.startsWith('00');
    let digits = onlyDigits(raw);

    if (raw.startsWith('00')) {
        digits = digits.slice(2);
    }

    if (isInternational) {
        const match = findCountryByDial(digits);

        if (match) {
            return { iso: match.iso, national: digits.slice(match.dial.length).replace(/^0+/, '') };
        }
    }

    return { iso: selectedIso.value, national: digits.replace(/^0+/, '') };
}

/** Stored shape is E.164 so numbers compare and dial identically everywhere. */
function toE164(iso, nationalDigits) {
    if (!nationalDigits) return '';

    const match = findCountry(iso) ?? fallbackCountry.value;

    return `+${match.dial}${nationalDigits}`;
}

function apply(iso, nationalDigits) {
    selectedIso.value = iso;
    national.value = nationalDigits;

    const next = toE164(iso, nationalDigits);

    if (next !== props.modelValue) {
        emit('update:modelValue', next);
    }
}

function onInput(event) {
    const element = event.target;
    const raw = element.value;
    const caret = element.selectionStart ?? raw.length;
    const digitsBeforeCaret = onlyDigits(raw.slice(0, caret)).length;

    // A pasted or typed international number re-targets the country picker.
    const parsed = raw.trim().startsWith('+') || raw.trim().startsWith('00')
        ? parse(raw)
        : { iso: selectedIso.value, national: onlyDigits(raw).replace(/^0+/, '') };

    const clamped = parsed.national.slice(0, maxNationalDigits(findCountry(parsed.iso)));

    apply(parsed.iso, clamped);

    nextTick(() => {
        // The input is only bound one-way, and Vue skips the patch entirely when
        // the model did not change (typing past the maximum), so the raw text has
        // to be pushed back by hand — otherwise rejected keystrokes stay visible.
        syncInputValue();

        const formatted = element.value;
        let position = formatted.length;
        let seen = 0;

        for (let index = 0; index < formatted.length; index += 1) {
            if (seen >= digitsBeforeCaret) {
                position = index;
                break;
            }
            if (/\d/.test(formatted[index])) seen += 1;
        }

        if (seen < digitsBeforeCaret) position = formatted.length;

        element.setSelectionRange(position, position);
    });
}

function onPaste(event) {
    const text = event.clipboardData?.getData('text');
    if (!text || !(text.trim().startsWith('+') || text.trim().startsWith('00'))) return;

    event.preventDefault();
    const parsed = parse(text);
    apply(parsed.iso, parsed.national.slice(0, maxNationalDigits(findCountry(parsed.iso))));
    nextTick(syncInputValue);
}

function syncInputValue() {
    if (inputRef.value && inputRef.value.value !== nationalDisplay.value) {
        inputRef.value.value = nationalDisplay.value;
    }
}

function toggleDropdown() {
    open.value = !open.value;

    if (open.value) {
        search.value = '';
        nextTick(() => searchRef.value?.focus());
    }
}

function closeDropdown() {
    open.value = false;
}

function selectCountry(option) {
    // Re-clamp: switching from a 10-digit plan to an 8-digit one must not leave
    // an over-long national number behind.
    apply(option.iso, national.value.slice(0, maxNationalDigits(option)));
    closeDropdown();
    nextTick(() => {
        syncInputValue();
        inputRef.value?.focus();
    });
}

onClickOutside(rootRef, closeDropdown);

watch(() => props.modelValue, (value) => {
    if (value === toE164(selectedIso.value, national.value)) return;

    const parsed = parse(value);
    selectedIso.value = parsed.iso;
    national.value = parsed.national.slice(0, maxNationalDigits(findCountry(parsed.iso)));
    nextTick(syncInputValue);
}, { immediate: true });

onMounted(() => {
    // Records saved before this field became international are stored as bare
    // local digits; normalise them so the next save writes E.164.
    const normalized = toE164(selectedIso.value, national.value);

    if (props.modelValue && normalized && normalized !== props.modelValue) {
        emit('update:modelValue', normalized);
    }
});
</script>
