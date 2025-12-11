<script setup>
    import { computed, ref, reactive, watch } from 'vue'
    import { useForm, router } from '@inertiajs/vue3'
    import { Input } from '@/Components/ui/input'
    import { Textarea } from '@/Components/ui/textarea'
    import { Label } from '@/Components/ui/label'
    import ModalDialog from '@/Components/next/Dialog.vue'
    import vSelect from 'vue-select'
    import NextInput from "@/Components/next/NextInput.vue";
    import FloatingLabel from "@/Components/next/FloatingLabel.vue";
    import NextTextarea from "@/Components/next/NextTextarea.vue";
    import NextSelect from "@/Components/next/NextSelect.vue";
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n()
    // const props = defineProps({
    //     isDialogOpen: Boolean,
    //     payment: Object, // ✅ this is passed from Index.vue
    //     categories: {
    //         type: Array,
    //         default: () => [],
    //     },
    //     errors: Object,
    // })
    const props = defineProps({
      isDialogOpen: Boolean,
      payment: {
        type: Object,
        default: () => ({
          method: '',
          amount: '',
          account_id: '',
          note: '',
        })
      },
      errors: {
        type: Object,
        default: () => ({})
      },
      submitting: Boolean,
      accounts: {
        type: Array,
        default: () => []
      },
    });

    const emit = defineEmits(['update:isDialogOpen', 'confirm', 'cancel', 'update:payment'])

    const categories = computed(() => props.categories.data ?? props.categories)

    const localDialogOpen = ref(props.isDialogOpen)

    watch(() => props.isDialogOpen, (val) => {
        localDialogOpen.value = val
    })

    watch(() => localDialogOpen.value, (val) => {
        emit('update:isDialogOpen', val)
    })
    const localPayment = reactive({
    method: '',
    amount: '',
    account_id: '',
    note: '',
})

// Sync from props when dialog opens or props change
watch(() => props.payment, (newPayment) => {
    Object.assign(localPayment, {
        method: newPayment?.method || '',
        amount: newPayment?.amount || '',
        account_id: newPayment?.account_id || '',
        note: newPayment?.note || '',
    })
}, { immediate: true, deep: true })

    const bankAccounts = ref([]);

    // Watch for accounts prop changes and update bankAccounts
    watch(() => props.accounts, (accounts) => {
      bankAccounts.value = accounts || [];
    }, { immediate: true });

    // Payment method options
    const paymentMethods = [
      { id: 'cash', name: 'Cash' },
      { id: 'bank_transfer', name: 'Bank Transfer' },
      { id: 'check', name: 'Check' },
      { id: 'card', name: 'Card' },
    ];

    // Form validation
    const isFormValid = computed(() => {
      return localPayment.value.amount &&
             localPayment.value.method &&
             localPayment.value.account_id;
    });

    // Watch for prop changes and sync local state
    watch(() => props.payment, (newPayment) => {
      localPayment.value = { ...newPayment };
    }, { deep: true });

    // No need to fetch accounts since they're passed as props

    // Handle form submission
    const handleSubmit = () => {
      if (!isFormValid.value) return;

      emit('update:payment', { ...localPayment.value });
      emit('confirm');
      emit('update:open', false);
    };

    // Handle dialog cancel
    const handleCancel = () => {
      // Reset local form to original values
      localPayment.value = { ...props.payment };
      emit('cancel');
      emit('update:open', false);
    };

    // Update local payment when props change
    const updatePayment = (field, value) => {
      localPayment.value[field] = value;
      emit('update:payment', { ...localPayment.value });
    };

    // Reset form when dialog opens
    const resetForm = () => {
      localPayment.value = {
        method: '',
        amount: '',
        account_id: '',
        note: '',
      };
    };

    </script>

<template>
 <ModalDialog
        :open="localDialogOpen"
        :title="t('general.payment')"
        :confirmText="t('general.confirm')"
        :cancel-text="t('general.cancel')"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        @confirm="handleSubmit"
        @cancel="handleCancel"
    >
        <div class="grid gap-4 py-4">
            <NextInput
                :modelValue="localPayment.amount"
                @update:modelValue="(value) => updatePayment('amount', value)"
                :label="t('general.amount')"
                :placeholder="t('general.enter', { text: t('general.amount') })"
                :error="errors?.amount"
                type="number"
                step="any"
            />
            <NextSelect
                v-model="localPayment.method"
                :options="paymentMethods"
                label-key="name"
                value-key="id"
                :reduce="paymentMethod => paymentMethod"
                floating-text="Payment Method"
                placeholder="Select payment method"
                @update:modelValue="(value) => localPayment.method = value"
                :error="errors?.method"
                :isRequired="true" 
            /> 

            <NextSelect
                :modelValue="localPayment.account_id"
                @update:modelValue="(value) => updatePayment('account_id', value)"
                :options="bankAccounts"
                label-key="name"
                value-key="id"
                floating-text="Bank Account"
                placeholder="Select bank account"
                :error="errors?.account_id"
                :isRequired="true"
            />
            <NextTextarea
                :modelValue="localPayment.note"
                @update:modelValue="(value) => updatePayment('note', value)"
                :label="t('general.note')"
                :placeholder="t('general.enter', { text: t('general.note') })"
                :error="errors?.note"
                rows="3"
            />
        </div>
    </ModalDialog>
</template>
