<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed, watch } from 'vue'
import { useLazyProps } from '@/composables/useLazyProps'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { useI18n } from 'vue-i18n'
import { Trash2, Plus, Upload } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

const { t } = useI18n()
const page = usePage()
const journalEntry = computed(() => page.props.journalEntry?.data || {})
const accounts = computed(() => page.props.accounts?.data || [])
const ledgers = computed(() => page.props.ledgers?.data || [])
const currencies = computed(() => page.props.currencies?.data || [])
const journalClasses = computed(() => page.props.journalClasses?.data || []) 
console.log('journal classes', journalClasses.value)
useLazyProps(page.props, ['accounts', 'ledgers', 'currencies'])

const form = useForm({
  id: '',
  number: '',
  date: '',
  currency_id: '',
  selected_currency: null,
  rate: '',
  remarks: '',
  lines: [],
  // Optionally support attachment editing
  attachment: null
})

const attachmentPreview = ref(null)
const fileInput = ref(null)

// Helper to handle line remarks (null fallback)
function getLineRemark(line) {
  return line.remark ?? ''
}

onMounted(() => {
  // Prefill the form with entry data, handling journalEntry.value structure per sample
  const je = journalEntry.value
  if (!je?.id) return
  form.id = je.id
  form.number = je.number
  form.date = je.date
  // Resolve currency id and selected_currency from transaction or je field
  form.currency_id = je.currency_id || je.transaction?.currency_id || je.transaction?.currency?.id || ''
  // When transaction object present, pick rate/currency from there, else from je
  form.selected_currency =
    (je.transaction && je.transaction.currency)
    || (je.currency_id && currencies.value.find(c => c.id === je.currency_id))
    || null

  // Rate comes from transaction.rate, fallback to journalEntry.transaction?.currency?.rate or 1
  form.rate = (je.transaction?.rate ?? je.rate) ?? 1

  // Remarks: use "remark" (journalEntry) or "remarks" (form potential)
  form.remarks = je.remarks ?? je.remark ?? ''

  // Map in detail lines: enhance for editability, map by account_id, remark/null handling.
  form.lines = (je.transaction?.lines || je.lines || []).map(line => ({
    ...line,
    debit: Number(line.debit) || 0,
    credit: Number(line.credit) || 0,
    selected_account: accounts.value.find(acc => acc.id === line.account_id) || null,
    remark: getLineRemark(line),
    ledger: line.ledger ?? '', 
    journal_class_id: line.journal_class_id ?? '',
  }))

  // If no transaction currency, sync selected_currency from currencies/currency_id for edit mode select
  if (!form.selected_currency && form.currency_id) {
    form.selected_currency = currencies.value.find(c => c.id === form.currency_id) || null
  }
})

watch(currencies, () => {
  if (form.currency_id && !form.selected_currency) {
    form.selected_currency = currencies.value.find(c => c.id === form.currency_id) || form.selected_currency
  }
})

function handleSelectChange(field, value) {
  if (field === 'currency_id') {
    form.currency_id = value
    const chosen = currencies.value.find(c => c.id === value)
    if (chosen) {
      form.rate = chosen.exchange_rate ?? form.rate
      form.selected_currency = chosen
    }
  } else {
    form[field] = value
  }
}

function handleAccountChange(lineIndex, acc) {
  const line = form.lines[lineIndex]
  if (!line) return
  line.selected_account = acc
  line.account_id = acc?.id || null
}

function handleAmountChange(index, type) {
  // Optionally perform sum checks or field logic
}

function addLine() {
  form.lines.push({
    account_id: null,
    selected_account: null,
    debit: 0,
    credit: 0,
    remark: '',
    ledger: '', 
    journal_class_id: '',
  })
}

function removeLine(index) {
  form.lines.splice(index, 1)
}

// For attachment, you may extend as needed
function handleFileChange(e) {
  const file = e.target.files[0]
  if (file) {
    form.attachment = file
    const reader = new FileReader()
    reader.onload = e => {
      attachmentPreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}
function removeAttachment() {
  form.attachment = null
  attachmentPreview.value = null
  if (fileInput.value) fileInput.value.value = ''
}
const totalCredit = computed(() => {
    return form.lines.reduce((sum, d) => sum + (Number(d.credit) || 0), 0);
});

const totalDebit = computed(() => {
    return form.lines.reduce((sum, d) => sum + (Number(d.debit) || 0), 0);
});
const normalize = () => {
    return form.lines.filter(d =>
        d.account_id &&
        ((Number(d.debit) > 0) || (Number(d.credit) > 0))
    );
}

function submit() {
    const validDetails = normalize();
    if (validDetails.length === 0) {
        toast.error(t('account.detail_lines'), {
            description: t('account.at_least_one_detail'),
            class: 'bg-red-600',
        });
        return;
    }

    if (totalDebit.value !== totalCredit.value) {
        toast.error(t('account.equality_error'), {
            description: t('account.total_debit_and_credit_must_be_equal_description'),
            class: 'bg-red-600',
        });
        return;
    }

    form.lines = normalize();
  form.put(`/journal-entries/${form.id}`)
}
</script>

<template>
  <AppLayout :title="t('general.edit', { name: t('journal_entry.journal_entry') })">
    <form @submit.prevent="submit()">
      <!-- MAIN HEADER SECTION -->
      <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.edit', { name: t('journal_entry.journal_entry') }) }}
        </div>
        <ModuleHelpButton module="journal_entry" />
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextInput
            :placeholder="t('general.enter', { text: t('general.number') })"
            :error="form.errors?.number"
            v-model="form.number"
            type="text"
            :label="t('general.number')"
          />
          <NextDate
            v-model="form.date"
            :error="form.errors?.date"
            :placeholder="t('general.enter', { text: t('general.date') })"
            :label="t('general.date')"
          />
          <div class="grid grid-cols-2 gap-2">
            <NextSelect
                :options="currencies.data || currencies"
                v-model="form.selected_currency"
                @update:modelValue="handleCurrencyChange"
                label-key="code"
                value-key="id"
                :reduce="cur => cur"
                :floating-text="t('admin.currency.currency')"
                :error="form.errors?.currency_id"
                :searchable="true"
            />
            <NextInput
                v-model="form.rate"
                type="number"
                step="any"
                :label="t('general.rate')"
                :error="form.errors?.rate"
            />
        </div>
        <div class="mt-4">
          <NextTextarea
            :placeholder="t('general.enter', { text: t('general.remarks') })"
            :error="form.errors?.remarks"
            v-model="form.remarks"
            :label="t('general.remarks')"
            rows="2"
          />
        </div>
        <div class="mt-4">
          <label class="block text-sm font-medium mb-2">{{ t('general.attachment') }}</label>
          <div class="flex items-center gap-4">
            <input
              ref="fileInput"
              type="file"
              @change="handleFileChange"
              class="hidden"
              accept="image/*,.pdf,.doc,.docx"
            />
            <button
              type="button"
              class="btn btn-outline"
              @click="() => fileInput?.click()"
            >
              {{ t('general.upload_file') }}
            </button>
            <span v-if="form.attachment" class="text-sm text-muted-foreground">
              {{ form.attachment.name }}
              <button
                type="button"
                @click="removeAttachment"
                class="ml-2 text-red-500 hover:text-red-700"
              >x</button>
            </span>
          </div>
          <img
            v-if="attachmentPreview"
            :src="attachmentPreview"
            class="mt-2 max-h-32 rounded border"
          />
        </div>
        </div>
      </div>

      <!-- DETAIL LINES -->
      <div class="rounded-xl border bg-card shadow-sm overflow-x-auto mb-4">

        <table class="w-full min-w-[600px]">
          <thead class="bg-muted/50">
            <tr class="text-sm text-muted-foreground rtl:text-right ltr:text-left">
              <th class="px-4 py-2 w-12">#</th>
              <th class="px-4 py-2">{{ t('account.account') }} *</th>
              <th class="px-4 py-2">{{ t('general.debit') }} *</th>
              <th class="px-4 py-2">{{ t('general.credit') }} *</th>
              <th class="px-4 py-2">{{ t('general.remark') }}</th>
              <th class="px-4 py-2">{{ t('general.ledger') }}</th>
              <th class="px-4 py-2">{{ t('sidebar.journal_entry.journal_class') }}</th>
              <th class="px-4 py-2 w-12"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, index) in form.lines" :key="index" class="border-t hover:bg-muted/30">
                <td class="px-4 py-2 text-center text-muted-foreground">{{ index + 1 }}</td>
                <td class="px-4 py-2 w-64">
                    <NextSelect
                        :options="accounts.data || accounts"
                        v-model="line.selected_account"
                        @update:modelValue="(val) => { line.account_id = val?.id || null }"
                        label-key="name"
                        value-key="id"
                        :reduce="acc => acc"
                        :error="form.errors?.[`line.${index}.account_id`]"
                        :searchable="true"
                    />
                </td>
                <td class="px-4 py-2 w-40">
                    <NextInput
                        v-model="line.debit"
                        type="number"
                        step="any"
                        @input="handleAmountChange(index, 'debit')"
                        :placeholder="t('general.debit')"
                        :error="form.errors?.[`line.${index}.debit`]"
                        class="text-right"
                    />
                </td>
                <td class="px-4 py-2 w-40">
                    <NextInput
                        v-model="line.credit"
                        type="number"
                        step="any"
                        @input="handleAmountChange(index, 'credit')"
                        :placeholder="t('general.credit')"
                        :error="form.errors?.[`line.${index}.credit`]"
                        class="text-right"
                    />
                </td>
                <td class="px-4 py-2 w-40">
                    <NextInput
                        v-model="line.remark"
                        :placeholder="t('general.remark')"
                        :error="form.errors?.[`line.${index}.remark`]"
                        class="text-right"
                    />
                </td>
                <td class="px-4 py-2 w-64">
                    <NextSelect
                        :options="ledgers.data || ledgers"
                            v-model="line.selected_ledger"
                        @update:modelValue="(val) => { line.ledger_id = val?.id || '' }"
                        label-key="name"
                        value-key="id"
                        :reduce="ledger => ledger"
                        :error="form.errors?.[`line.${index}.ledger_id`]"
                        :searchable="true"
                    />
                </td>
                <td class="px-4 py-2 w-40">
                    <NextSelect
                        :options="journalClasses.data || journalClasses"
                        v-model="line.selected_journal_class"
                        @update:modelValue="(val) => { line.journal_class_id = val?.id || '' }"
                        label-key="name"
                        value-key="id"
                        :reduce="journalClass => journalClass"
                        :error="form.errors?.[`line.${index}.journal_class_id`]"
                        :searchable="true"
                    />
                </td>

                <td class="px-4 py-2 text-center">
                    <button
                        type="button"
                        @click="removeLine(index)"
                        :disabled="form.lines.length <= 1"
                        class="text-red-500 hover:text-red-700 disabled:opacity-30 disabled:cursor-not-allowed"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </td>
            </tr>
          </tbody>
          <tfoot class="bg-violet-500/10">
            <tr class="font-semibold">
                <td colspan="2" class="px-4 py-3 text-right">{{ t('general.total') }}:</td>
                <td class="px-4 py-3 text-center">
                    {{ totalDebit.toLocaleString() }}
                </td>
                <td class="px-4 py-3 text-center">
                    {{ totalCredit.toLocaleString() }}
                </td>
                <td colspan="2"></td>
                <td class="px-4 py-3">
                    <Button type="button" variant="outline" size="sm" class="btn btn-primary px-4 py-2 rounded-md bg-violet-500 hover:bg-violet-600 border text-white disabled:bg-gray-300"
                    @click="addLine">
                       <div class="flex items-center gap-2">
                        {{ t('general.add_more') }}
                        <Plus class="w-4 h-4 mr-1" />
                       </div>
                    </Button>
                </td>
            </tr>

        </tfoot>
        </table>
      </div>

      <div class="mt-4 flex gap-2">
        <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white">{{ t('general.update') }}</button>
        <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit('/journal-entries')">{{ t('general.cancel') }}</button>
      </div>
    </form>
  </AppLayout>
</template>
