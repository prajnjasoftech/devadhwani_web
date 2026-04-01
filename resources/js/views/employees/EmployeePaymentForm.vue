<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const route = useRoute();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(true);
const saving = ref(false);

// Dropdown data
const employees = ref([]);
const accounts = ref([]);

const paymentTypes = [
  { value: 'bonus', label: 'Bonus' },
  { value: 'advance', label: 'Advance' },
  { value: 'reimbursement', label: 'Reimbursement' },
  { value: 'incentive', label: 'Incentive' },
  { value: 'other', label: 'Other' },
];

// Form
const form = ref({
  employee_id: '',
  payment_date: new Date().toISOString().split('T')[0],
  payment_type: 'bonus',
  description: '',
  amount: 0,
  payment_method: 'cash',
  account_id: '',
  reference_number: '',
  notes: '',
});

const errors = ref({});

// Payment methods - only show UPI/Card if accounts are bound
const paymentMethods = computed(() => {
  const methods = [{ value: 'cash', label: 'Cash' }];
  if (accounts.value.some(a => a.is_upi_account)) {
    methods.push({ value: 'upi', label: 'UPI' });
  }
  if (accounts.value.some(a => a.is_card_account)) {
    methods.push({ value: 'card', label: 'Card' });
  }
  methods.push(
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cheque', label: 'Cheque' }
  );
  return methods;
});

// Filter accounts based on payment method
const filteredAccounts = computed(() => {
  const method = form.value.payment_method;
  if (method === 'cash') {
    return accounts.value.filter(a => a.account_type === 'cash');
  } else if (method === 'upi') {
    return accounts.value.filter(a => a.is_upi_account);
  } else if (method === 'card') {
    return accounts.value.filter(a => a.is_card_account);
  } else {
    return accounts.value.filter(a => a.account_type === 'bank');
  }
});

const fetchEmployees = async () => {
  try {
    const response = await api.get('/employees/all');
    employees.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch employees:', error);
  }
};

const fetchAccounts = async () => {
  try {
    const response = await api.get('/accounts/all');
    accounts.value = response.data.data;
    // Auto-select cash account
    if (!isEdit.value) {
      const cashAccount = accounts.value.find(a => a.account_type === 'cash');
      if (cashAccount) form.value.account_id = cashAccount.id;
    }
  } catch (error) {
    console.error('Failed to fetch accounts:', error);
  }
};

const fetchPayment = async () => {
  if (!isEdit.value) {
    loading.value = false;
    return;
  }

  try {
    const response = await api.get(`/employee-payments/${route.params.id}`);
    const data = response.data.data;
    form.value = {
      employee_id: data.employee_id,
      payment_date: data.payment_date.split('T')[0],
      payment_type: data.payment_type,
      description: data.description,
      amount: data.amount,
      payment_method: data.payment_method,
      account_id: data.account_id,
      reference_number: data.reference_number || '',
      notes: data.notes || '',
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch payment', 'error');
    router.push('/employees/payments');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    if (isEdit.value) {
      await api.put(`/employee-payments/${route.params.id}`, form.value);
      uiStore.showToast('Payment updated successfully', 'success');
    } else {
      await api.post('/employee-payments', form.value);
      uiStore.showToast('Payment recorded successfully', 'success');
    }
    router.push('/employees/payments');
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save payment', 'error');
    }
  } finally {
    saving.value = false;
  }
};

// Auto-select appropriate account when payment method changes
watch(() => form.value.payment_method, (method) => {
  if (method === 'cash') {
    const cashAccount = accounts.value.find(a => a.account_type === 'cash');
    form.value.account_id = cashAccount?.id || '';
  } else if (method === 'upi') {
    const upiAccount = accounts.value.find(a => a.is_upi_account);
    form.value.account_id = upiAccount?.id || '';
  } else if (method === 'card') {
    const cardAccount = accounts.value.find(a => a.is_card_account);
    form.value.account_id = cardAccount?.id || '';
  } else {
    const bankAccounts = accounts.value.filter(a => a.account_type === 'bank');
    form.value.account_id = bankAccounts.length === 1 ? bankAccounts[0].id : '';
  }
});

onMounted(async () => {
  await Promise.all([fetchEmployees(), fetchAccounts()]);
  await fetchPayment();
});
</script>

<template>
  <div>
    <div class="flex items-center gap-4 mb-6">
      <Button variant="ghost" @click="router.push('/employees/payments')">
        <ArrowLeftIcon class="w-5 h-5" />
      </Button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          {{ isEdit ? 'Edit Payment' : 'Add Payment' }}
        </h1>
        <p class="text-gray-500">
          {{ isEdit ? 'Update payment details' : 'Record a new employee payment' }}
        </p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Employee & Date -->
      <Card title="Payment Details">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee *</label>
            <select
              v-model="form.employee_id"
              required
              :disabled="isEdit"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:bg-gray-100"
              :class="{ 'border-red-500': errors.employee_id }"
            >
              <option value="">Select Employee</option>
              <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                {{ emp.employee_code }} - {{ emp.name }}
              </option>
            </select>
            <p v-if="errors.employee_id" class="mt-1 text-sm text-red-600">{{ errors.employee_id[0] }}</p>
          </div>
          <Input
            v-model="form.payment_date"
            label="Payment Date *"
            type="date"
            required
            :error="errors.payment_date?.[0]"
          />
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Type *</label>
            <select
              v-model="form.payment_type"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option v-for="type in paymentTypes" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
          <Input
            v-model="form.description"
            label="Description *"
            placeholder="e.g., Festival bonus, Travel reimbursement"
            required
            :error="errors.description?.[0]"
          />
          <Input
            v-model.number="form.amount"
            label="Amount *"
            type="number"
            min="0.01"
            step="0.01"
            required
            :error="errors.amount?.[0]"
          />
        </div>
      </Card>

      <!-- Payment Method -->
      <Card title="Payment Method">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Method *</label>
            <select
              v-model="form.payment_method"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Account *</label>
            <select
              v-model="form.account_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              :class="{ 'border-red-500': errors.account_id }"
            >
              <option value="">Select Account</option>
              <option v-for="acc in filteredAccounts" :key="acc.id" :value="acc.id">
                {{ acc.account_name }} (₹{{ parseFloat(acc.current_balance).toLocaleString() }})
              </option>
            </select>
            <p v-if="errors.account_id" class="mt-1 text-sm text-red-600">{{ errors.account_id[0] }}</p>
          </div>
          <Input
            v-model="form.reference_number"
            label="Reference Number"
            placeholder="Transaction ID / Cheque No."
          />
        </div>
      </Card>

      <!-- Notes -->
      <Card title="Additional Notes">
        <textarea
          v-model="form.notes"
          rows="3"
          class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          placeholder="Any additional notes..."
        ></textarea>
      </Card>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-4">
        <Button type="button" variant="outline" @click="router.push('/employees/payments')">
          Cancel
        </Button>
        <Button type="submit" variant="primary" :loading="saving">
          {{ isEdit ? 'Update Payment' : 'Record Payment' }}
        </Button>
      </div>
    </form>
  </div>
</template>
