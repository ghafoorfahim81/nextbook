<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
import { Button } from '@/Components/ui/button';
import { Plus, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    filterOptions: { type: Object, default: () => ({}) },
    defaultBrackets: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);

const isEditing = computed(() => !!props.editingItem?.id);
const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (v) => localDialogOpen.value = v);
watch(() => localDialogOpen.value, (v) => emit('update:isDialogOpen', v));

const periods = computed(() => props.filterOptions?.periods || []);
const currencies = computed(() => props.filterOptions?.currencies || []);

const seedBrackets = () => (props.defaultBrackets.length
    ? props.defaultBrackets.map((b, i) => ({ ...b, sequence: b.sequence ?? i + 1 }))
    : [{ sequence: 1, from_amount: 0, to_amount: null, fixed_amount: 0, rate: 0 }]);

const blank = () => ({
    name: '',
    jurisdiction: 'AF',
    period: 'monthly', selected_period: null,
    effective_from: '',
    effective_to: null,
    currency_id: null, selected_currency: null,
    is_active: true,
    remark: '',
    brackets: seedBrackets(),
});

const form = useForm(blank());

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            ...item,
            brackets: (item.brackets ?? []).map((b) => ({ ...b })),
            selected_period: periods.value.find((x) => x.id === item.period) ?? null,
            selected_currency: currencies.value.find((x) => x.id === item.currency_id) ?? null,
        });
    } else {
        form.defaults(blank());
        form.reset();
        form.brackets = seedBrackets();
        form.selected_period = periods.value.find((x) => x.id === 'monthly') ?? null;
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

const addBracket = () => {
    const last = form.brackets[form.brackets.length - 1];

    // A new band starts where the previous one ended, and the previous one
    // gains a ceiling if it had none — contiguity by construction rather than
    // by asking the user to keep it straight.
    if (last && (last.to_amount === null || last.to_amount === '')) {
        last.to_amount = Number(last.from_amount ?? 0) + 10000;
    }

    form.brackets.push({
        sequence: form.brackets.length + 1,
        from_amount: last ? Number(last.to_amount ?? 0) : 0,
        to_amount: null,
        fixed_amount: 0,
        rate: 0,
    });
};

const removeBracket = (index) => {
    if (form.brackets.length <= 1) return;

    form.brackets.splice(index, 1);
    form.brackets.forEach((b, i) => { b.sequence = i + 1; });

    // Whatever is now last must be open-ended, or the highest earners fall
    // outside the table.
    const last = form.brackets[form.brackets.length - 1];
    if (last) last.to_amount = null;
};

// When a band's ceiling moves, the next band's floor follows it. Leaving them
// to drift apart is exactly how a gap gets into a rate table.
const onCeilingChanged = (index) => {
    const next = form.brackets[index + 1];
    if (next) next.from_amount = form.brackets[index].to_amount;
};

const useDefaults = () => {
    form.brackets = seedBrackets();
};

// Contiguity, mirrored from the FormRequest so the user sees it while typing
// rather than after a round trip.
const bracketWarnings = computed(() => {
    const warnings = [];
    const bands = form.brackets;

    if (Number(bands[0]?.from_amount ?? -1) !== 0) {
        warnings.push(t('hr.first_bracket_starts_at_zero'));
    }

    const last = bands[bands.length - 1];
    if (last && last.to_amount !== null && last.to_amount !== '') {
        warnings.push(t('hr.last_bracket_is_open_ended'));
    }

    for (let i = 1; i < bands.length; i++) {
        const previousTo = bands[i - 1].to_amount;

        if (previousTo === null || previousTo === '') {
            warnings.push(t('hr.only_last_bracket_is_open_ended'));
            break;
        }

        if (Number(bands[i].from_amount) !== Number(previousTo)) {
            warnings.push(t('hr.brackets_must_be_contiguous'));
            break;
        }
    }

    return warnings;
});

// The live check. A rate table is hard to read back, so the form answers
// "what would someone on this salary pay" against the UNSAVED bands.
const previewIncome = ref(50000);
const previewResult = ref(null);
const previewing = ref(false);

const runPreview = async () => {
    previewing.value = true;

    try {
        const { data } = await axios.post(route('tax-bracket-sets.preview'), {
            income: previewIncome.value,
            brackets: form.brackets,
        });
        previewResult.value = data;
    } catch (error) {
        previewResult.value = null;
        toast.error(t('general.error'));
    } finally {
        previewing.value = false;
    }
};

const handleSubmit = async () => {
    const isEdit = isEditing.value;

    form.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([k]) => !k.startsWith('selected_') && k !== 'id' && !k.endsWith('_label'))
    ));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(t('general.success'), {
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.tax_bracket_set') }),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) await form.put(route('tax-bracket-sets.update', props.editingItem.id), options);
    else await form.post(route('tax-bracket-sets.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.tax_bracket_set') }) : t('general.create', { name: t('hr.tax_bracket_set') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        width="w-[95vw] max-w-[95vw] sm:w-[1000px] sm:max-w-[1000px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid grid-cols-1 gap-4 py-2 md:grid-cols-3">
                <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" autofocus />
                <NextSelect
                    :options="periods" v-model="form.selected_period"
                    @update:modelValue="(v) => { form.period = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.tax_period')" :error="form.errors?.period"
                    append-in-dialog
                />
                <NextInput :label="t('hr.jurisdiction')" v-model="form.jurisdiction" :error="form.errors?.jurisdiction" />

                <NextDatePicker :label="t('hr.effective_from')" v-model="form.effective_from" :error="form.errors?.effective_from" />
                <NextDatePicker :label="t('hr.effective_to')" v-model="form.effective_to" :error="form.errors?.effective_to" />
                <NextSelect
                    :options="currencies" v-model="form.selected_currency"
                    @update:modelValue="(v) => { form.currency_id = v?.id ?? null }"
                    label-key="code" value-key="id" :reduce="(x) => x"
                    :floating-text="t('general.currency')" :error="form.errors?.currency_id"
                    append-in-dialog
                />
            </div>

            <!-- Bands -->
            <div class="mt-4 rounded-lg border border-border">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <div>
                        <h3 class="text-sm font-semibold">{{ t('hr.bands') }}</h3>
                        <p class="text-xs text-muted-foreground">{{ t('hr.bands_hint') }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" size="sm" variant="outline" @click="useDefaults">
                            {{ t('hr.use_default_brackets') }}
                        </Button>
                        <Button type="button" size="sm" variant="outline" @click="addBracket">
                            <Plus class="mr-1 h-4 w-4" /> {{ t('hr.add_band') }}
                        </Button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-start">#</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.from_amount') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.to_amount') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.fixed_amount') }}</th>
                                <th class="px-3 py-2 text-start">{{ t('hr.rate_percent') }}</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(bracket, index) in form.brackets" :key="index" class="border-t border-border">
                                <td class="px-3 py-2 text-muted-foreground">{{ bracket.sequence }}</td>
                                <td class="px-3 py-2">
                                    <input
                                        type="number" step="any" v-model.number="bracket.from_amount"
                                        class="w-32 rounded border border-border bg-background px-2 py-1"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="number" step="any" v-model.number="bracket.to_amount"
                                        :placeholder="t('hr.no_upper_limit')"
                                        class="w-32 rounded border border-border bg-background px-2 py-1"
                                        @change="onCeilingChanged(index)"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="number" step="any" v-model.number="bracket.fixed_amount"
                                        class="w-32 rounded border border-border bg-background px-2 py-1"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="number" step="any" v-model.number="bracket.rate"
                                        class="w-24 rounded border border-border bg-background px-2 py-1"
                                    />
                                </td>
                                <td class="px-3 py-2 text-end">
                                    <button
                                        type="button"
                                        class="text-muted-foreground transition hover:text-destructive disabled:opacity-40"
                                        :disabled="form.brackets.length <= 1"
                                        @click="removeBracket(index)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="bracketWarnings.length" class="border-t border-border bg-destructive/10 px-4 py-2">
                    <p v-for="(warning, i) in bracketWarnings" :key="i" class="text-xs text-destructive">
                        {{ warning }}
                    </p>
                </div>
                <p v-if="form.errors?.brackets" class="border-t border-border px-4 py-2 text-xs text-destructive">
                    {{ form.errors.brackets }}
                </p>
            </div>

            <!-- Live check -->
            <div class="mt-4 rounded-lg border border-border bg-muted/30 p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs text-muted-foreground">{{ t('hr.check_an_income') }}</label>
                        <input
                            type="number" step="any" v-model.number="previewIncome"
                            class="w-40 rounded border border-border bg-background px-2 py-1 text-sm"
                        />
                    </div>
                    <Button type="button" size="sm" variant="outline" :disabled="previewing" @click="runPreview">
                        {{ t('hr.calculate_tax') }}
                    </Button>

                    <div v-if="previewResult" class="flex flex-wrap gap-6 text-sm">
                        <div>
                            <span class="block text-xs text-muted-foreground">{{ t('hr.tax') }}</span>
                            <span class="font-semibold">{{ previewResult.tax }}</span>
                        </div>
                        <div>
                            <span class="block text-xs text-muted-foreground">{{ t('hr.net') }}</span>
                            <span class="font-semibold">{{ previewResult.net }}</span>
                        </div>
                        <div>
                            <span class="block text-xs text-muted-foreground">{{ t('hr.marginal_rate') }}</span>
                            <span class="font-semibold">{{ previewResult.marginal_rate }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <label class="mt-4 flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-border text-primary" />
                {{ t('general.active') }}
            </label>
        </form>
    </ModalDialog>
</template>
