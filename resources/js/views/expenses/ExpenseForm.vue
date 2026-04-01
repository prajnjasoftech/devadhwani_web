<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Modal from '@/components/ui/Modal.vue';
import { PlusIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const route = useRoute();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(true);
const saving = ref(false);

// Dropdown data
const categories = ref([]);
const accounts = ref([]);

// Quick add modal
const showCategoryModal = ref(false);
const quickAddName = ref('');
const quickAddDescription = ref('');
const quickAddSaving = ref(false);

// Success modal
const showSuccessModal = ref(false);
const createdExpense = ref(null);

// Form
const form = ref({
  expense_date: new Date().toISOString().split('T')[0],
  category_id: '',
  description: '',
  amount: 0,
  payment_status: 'pending',
  paid_amount: 0,
  payment_method: 'cash',
  account_id: '',
  reference_number: '',
  paid_to: '',
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
    { value: 'cheque', label: 'Cheque' },
    { value: 'other', label: 'Other' }
  );
  return methods;
});

const balanceAmount = computed(() => {
  return (form.value.amount || 0) - (form.value.paid_amount || 0);
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
    // bank_transfer, cheque, other - show all bank accounts
    return accounts.value.filter(a => a.account_type === 'bank');
  }
});

const fetchDropdowns = async () => {
  try {
    const [categoriesRes, accountsRes] = await Promise.all([
      api.get('/expense-categories/all'),
      api.get('/accounts/all'),
    ]);
    categories.value = categoriesRes.data.data;
    accounts.value = accountsRes.data.data;
    // Set default to cash account
    const cashAccount = accounts.value.find(a => a.account_type === 'cash');
    if (cashAccount && !form.value.account_id) {
      form.value.account_id = cashAccount.id;
    }
  } catch (error) {
    console.error('Failed to fetch dropdowns:', error);
  }
};

// Flag to skip auto-select when loading existing data
const skipAutoSelect = ref(false);

// Auto-select account when payment method changes
watch(() => form.value.payment_method, (method) => {
  if (skipAutoSelect.value) {
    skipAutoSelect.value = false;
    return;
  }
  // Don't auto-select if accounts not loaded yet
  if (!accounts.value.length) return;

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
    // For bank_transfer, cheque, other - select first bank account or clear
    const bankAccounts = accounts.value.filter(a => a.account_type === 'bank');
    form.value.account_id = bankAccounts.length === 1 ? bankAccounts[0].id : '';
  }
});

const fetchExpense = async () => {
  if (!isEdit.value) {
    loading.value = false;
    return;
  }

  try {
    const response = await api.get(`/expenses/${route.params.id}`);
    const data = response.data.data;
    // Skip auto-select when loading existing data
    skipAutoSelect.value = true;
    form.value = {
      expense_date: data.expense_date.split('T')[0],
      category_id: data.category_id,
      description: data.description,
      amount: data.amount,
      payment_status: data.payment_status,
      paid_amount: data.paid_amount,
      payment_method: data.payment_method || 'cash',
      account_id: data.account_id || '',
      reference_number: data.reference_number || '',
      paid_to: data.paid_to || '',
      notes: data.notes || '',
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch expense', 'error');
    router.push('/expenses');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    const submitData = { ...form.value };

    // Set payment status based on paid amount
    if (submitData.paid_amount >= submitData.amount) {
      submitData.payment_status = 'paid';
    } else if (submitData.paid_amount > 0) {
      submitData.payment_status = 'partial';
    } else {
      submitData.payment_status = 'pending';
    }

    if (isEdit.value) {
      await api.put(`/expenses/${route.params.id}`, submitData);
      uiStore.showToast('Expense updated successfully', 'success');
      router.push('/expenses');
    } else {
      const response = await api.post('/expenses', submitData);
      createdExpense.value = response.data.data;
      showSuccessModal.value = true;
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save expense', 'error');
    }
  } finally {
    saving.value = false;
  }
};

const quickAddCategory = async () => {
  if (!quickAddName.value.trim()) return;
  quickAddSaving.value = true;
  try {
    const response = await api.post('/expense-categories', {
      name: quickAddName.value.trim(),
      description: quickAddDescription.value.trim() || null,
    });
    categories.value.push(response.data.data);
    form.value.category_id = response.data.data.id;
    showCategoryModal.value = false;
    quickAddName.value = '';
    quickAddDescription.value = '';
    uiStore.showToast('Category added', 'success');
  } catch (error) {
    uiStore.showToast('Failed to add category', 'error');
  } finally {
    quickAddSaving.value = false;
  }
};

const resetForm = () => {
  form.value = {
    expense_date: new Date().toISOString().split('T')[0],
    category_id: '',
    description: '',
    amount: 0,
    payment_status: 'pending',
    paid_amount: 0,
    payment_method: 'cash',
    account_id: accounts.value.find(a => a.account_type === 'cash')?.id || '',
    reference_number: '',
    paid_to: '',
    notes: '',
  };
  errors.value = {};
  showSuccessModal.value = false;
  createdExpense.value = null;
};

onMounted(async () => {
  await fetchDropdowns();
  await fetchExpense();
});
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isEdit ? 'Edit Expense' : 'New Expense' }}
      </h1>
      <p class="text-gray-500">
        {{ isEdit ? 'Update expense details' : 'Record a new expense entry' }}
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Expense Details -->
      <Card title="Expense Details">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Input
            v-model="form.expense_date"
            label="Expense Date"
            type="date"
            required
            :error="errors.expense_date?.[0]"
          />

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-sm font-medium text-gray-700">Category *</label>
              <button type="button" @click="showCategoryModal = true; quickAddName = ''; quickAddDescription = ''" class="text-xs text-primary-600 hover:underline">
                + Add New
              </button>
            </div>
            <select
              v-model="form.category_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option value="">Select Category</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <Input
            v-model="form.paid_to"
            label="Paid To"
            placeholder="Vendor/Person name"
            :error="errors.paid_to?.[0]"
          />
        </div>

        <div class="mt-6">
          <Input
            v-model="form.description"
            label="Description"
            placeholder="e.g., Electricity bill for March 2026"
            required
            :error="errors.description?.[0]"
          />
        </div>
      </Card>

      <!-- Amount & Payment -->
      <Card title="Amount & Payment">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <Input
            v-model.number="form.amount"
            label="Total Amount"
            type="number"
            min="0.01"
            step="0.01"
            required
            :error="errors.amount?.[0]"
          />

          <Input
            v-model.number="form.paid_amount"
            label="Amount Paid"
            type="number"
            min="0"
            :max="form.amount"
            step="0.01"
          />

          <Select
            v-model="form.payment_method"
            label="Payment Method"
            :options="paymentMethods"
          />

          <div v-if="form.paid_amount > 0">
            <label class="block text-sm font-medium text-gray-700 mb-1">Debit From Account *</label>
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

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Balance</label>
            <div :class="['px-3 py-2 rounded-md text-lg font-bold', balanceAmount > 0 ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700']">
              {{ new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(balanceAmount) }}
            </div>
          </div>
        </div>

        <div class="mt-4">
          <Input
            v-model="form.reference_number"
            label="Reference/Bill Number"
            placeholder="Invoice, receipt, or cheque number"
          />
        </div>

        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea
            v-model="form.notes"
            rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Any additional notes..."
          ></textarea>
        </div>
      </Card>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-4">
        <Button type="button" variant="outline" @click="router.push('/expenses')">
          Cancel
        </Button>
        <Button type="submit" variant="primary" :loading="saving">
          {{ isEdit ? 'Update Expense' : 'Save Expense' }}
        </Button>
      </div>
    </form>

    <!-- Quick Add Category Modal -->
    <Modal :show="showCategoryModal" title="Add New Category" @close="showCategoryModal = false">
      <div class="space-y-4">
        <Input v-model="quickAddName" label="Category Name" placeholder="e.g., Electricity, Salary, etc." />
        <Input v-model="quickAddDescription" label="Description (Optional)" placeholder="Brief description" />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showCategoryModal = false">Cancel</Button>
          <Button variant="primary" :loading="quickAddSaving" @click="quickAddCategory">Add</Button>
        </div>
      </div>
    </Modal>

    <!-- Success Modal -->
    <Modal :show="showSuccessModal" title="Expense Recorded" @close="router.push('/expenses')">
      <div class="text-center py-4">
        <div class="mx-auto flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
          <CheckCircleIcon class="w-10 h-10 text-green-600" />
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Expense Recorded Successfully!</h3>
        <p v-if="createdExpense" class="text-gray-600">
          {{ createdExpense.expense_number }}
        </p>
      </div>
      <div class="flex justify-center gap-3 pt-4 border-t">
        <Button variant="outline" @click="resetForm">Add Another</Button>
        <Button variant="primary" @click="router.push('/expenses')">View All</Button>
      </div>
    </Modal>
  </div>
</template>
