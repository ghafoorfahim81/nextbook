<script setup>
    import { ref, computed, watch, onMounted  } from 'vue';
    import { Separator } from "@/Components/ui/separator";
    import { Button } from "@/Components/ui/button";
    import { Label } from '@/Components/ui/label'
    import { Switch } from '@/Components/ui/switch'
    import NextInput from '@/Components/next/NextInput.vue';
    import NextSelect from '@/Components/next/NextSelect.vue';
    import NextTextarea from '@/Components/next/NextTextarea.vue';
    import ModalDialog from '@/Components/next/Dialog.vue';
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();
    const props = defineProps({
      open: Boolean,
      payment: {
        type: Object,
        default: () => ({
          method: '',
          amount: '',
          account_id: '', 
          note: '',
        })
      },
      billTotal: {
        type: Number,
        default: 0
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
    
    const emit = defineEmits(["update:open", "confirm", "cancel", "update:payment"]);   
    
    // Local form state
    const localPayment = ref({ ...props.payment });
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
      return  localPayment.value.amount &&
             localPayment.value.method &&
             localPayment.value.account_id;
    });
    
    // Watch for prop changes and sync local state
    watch(() => props.payment, (newPayment) => {
      localPayment.value = { ...newPayment };
    }, { deep: true });
    
    // No need to fetch accounts since they're passed as props


    const closeModal = () => {
    emit('update:open', false)
}

    // Handle form submission
    const handleSubmit = () => {  
      if (!isFormValid.value) return; 
      emit('update:payment', { ...localPayment.value });
      emit('confirm');
      closeModal();
    }
    
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
            :open="open"
            :title="t('general.credit_payment_details')"
            :confirmText="t('general.save')"
            @update:open="open = $event; emit('update:open', $event)"
            :closeable="true"
            width="w-[600px] max-w-[600px]"
            @confirm="handleSubmit"
            @cancel="handleCancel"
            :cancel-text="t('general.cancel')"
        >
    
          <div class="space-y-4 mt-3"> 
                <NextInput
                  v-model="localPayment.amount"
                  :label="t('general.payment_amount')"
                  type="number"
                  step="0.01"
                  :placeholder="t('general.enter', { text: t('general.payment_amount') })"
                  :error="errors.amount"
                  :isRequired="true"
                  @update:modelValue="(value) => updatePayment('amount', value)"
                />  
    
            <!-- Payment Method Field -->
            <NextSelect
              v-model="localPayment.method"
              :options="paymentMethods"
              label-key="name"
              value-key="id"
              :floating-text="t('general.payment_method')"
              :placeholder="t('general.select_payment_method')"
              :error="errors.method"
              :isRequired="true"
              @update:modelValue="(value) => updatePayment('method', value)"
            />
    
            <!-- Bank Account Field -->
            <NextSelect
              v-model="localPayment.account_id"
              :options="bankAccounts"
              label-key="name"
              value-key="id"
              :floating-text="t('general.bank_account')"
              :placeholder="t('general.select_bank_account')"
              :error="errors.account_id"
              :isRequired="true"
              @update:modelValue="(value) => updatePayment('account_id', value)"
            />

            <NextInput
                  v-model="localPayment.note"
                  :label="t('general.add_any_additional_notes')"
                  type="text"
                  step="0.01"
                  :placeholder="t('general.enter', { text: t('general.any_notes') })" 
                  @update:modelValue="(value) => updatePayment('note', value)"
                />  
            
            <div class="grid grid-cols-4 gap-4 text-nowrap border rounded-md pt-2 mt-2 divide-x divide-gray-200 p-2"> 
              <div class="col-span-1 text-sm text-gray-700 mt-2">
                  <span class="font-bold">{{ t('general.bill_amount') }}:</span> {{ (props.billTotal) }}
                </div>
                <div class="col-span-1 text-sm text-gray-700 mt-2">
                  <span class="font-bold">{{ t('general.paid_amount') }}:</span> {{ (localPayment.amount) }}
                </div>
                <div class="col-span-1 text-sm text-gray-700 mt-2">
                  <span class="font-bold">{{ t('general.remaining_balance') }}:</span> {{ (props.billTotal - localPayment.amount) }}
                </div>
            </div>
          </div> 
        </ModalDialog>
    </template>
    
    <style scoped>
    /* Additional styles if needed */
    </style>
    