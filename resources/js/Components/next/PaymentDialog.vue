<script setup>
import { ref, computed, watch, onMounted  } from 'vue';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle
} from "@/Components/ui/dialog";
import { Separator } from "@/Components/ui/separator";
import { Button } from "@/Components/ui/button";
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';

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
const isLoading = ref(false);

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
  <Dialog :open="open" @update:open="value => emit('update:open', value)">
    <div v-if="open" class="fixed inset-0 bg-white/5 z-[500]" />

    <DialogContent class="w-[500px] max-w-[500px]">
      <DialogHeader>
        <DialogTitle>Credit Payment Details</DialogTitle>
        <DialogDescription>
          Enter the payment information for this credit transaction.
        </DialogDescription>
      </DialogHeader>

      <Separator />

      <div class="space-y-4">
        <!-- Amount Field -->
        <NextInput
          v-model="localPayment.amount"
          label="Amount"
          type="number"
          step="0.01"
          placeholder="Enter payment amount"
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
          floating-text="Payment Method"
          placeholder="Select payment method"
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
          floating-text="Bank Account"
          placeholder="Select bank account"
          :error="errors.account_id"
          :isRequired="true"
          @update:modelValue="(value) => updatePayment('account_id', value)"
        />

        <!-- Note Field -->
        <NextTextarea
          v-model="localPayment.note"
          label="Note (Optional)"
          placeholder="Add any additional notes"
          :error="errors.note"
          rows="3"
          @update:modelValue="(value) => updatePayment('note', value)"
        />
      </div>

      <DialogFooter>
        <Button
          type="button"
          variant="outline"
          @click="handleCancel"
        >
          Cancel
        </Button>
        <Button
          variant="outline"
          :disabled="!isFormValid || submitting"
          class="bg-primary text-white"
          @click="handleSubmit"
        >
          <span v-if="submitting">Saving...</span>
          <span v-else>Save Payment</span>
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<style scoped>
/* Additional styles if needed */
</style>
