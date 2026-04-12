<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Table from '@/components/ui/Table.vue';
import Modal from '@/components/ui/Modal.vue';
import {
  ArrowLeftIcon,
  BanknotesIcon,
  CalendarIcon,
  CheckCircleIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const loading = ref(true);
const generating = ref(false);
const salaries = ref([]);
const accounts = ref([]);
const meta = ref({});

// Filters
const filters = ref({
  month: new Date().toISOString().slice(0, 7),
  status: '',
});

// Payment modal
const showPaymentModal = ref(false);
const payingSalary = ref(null);
const paymentForm = ref({
  payment_date: new Date().toISOString().split('T')[0],
  payment_method: 'cash',
  account_id: '',
  reference_number: '',
});
const paymentErrors = ref({});
const paying = ref(false);

const columns = [
  { key: 'employee', label: 'Employee' },
  { key: 'basic_salary', label: 'Basic' },
  { key: 'allowances', label: 'Allowances' },
  { key: 'deductions', label: 'Deductions' },
  { key: 'net_salary', label: 'Net Salary' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: '' },
];

const fetchSalaries = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      month: filters.value.month || undefined,
      status: filters.value.status || undefined,
    };
    const response = await api.get('/employee-salaries', { params });
    salaries.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch salaries', 'error');
  } finally {
    loading.value = false;
  }
};

const fetchAccounts = async () => {
  try {
    const response = await api.get('/accounts/all');
    accounts.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch accounts:', error);
  }
};

const generateMonthlySalaries = async () => {
  if (!filters.value.month) {
    uiStore.showToast('Please select a month', 'error');
    return;
  }

  generating.value = true;
  try {
    const response = await api.post('/employee-salaries/generate', {
      month: filters.value.month,
    });
    uiStore.showToast(response.data.message, 'success');
    fetchSalaries();
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Failed to generate salaries', 'error');
  } finally {
    generating.value = false;
  }
};

const formatAmount = (amount) => {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-IN', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const openPaymentModal = (salary) => {
  payingSalary.value = salary;
  paymentForm.value = {
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'cash',
    account_id: accounts.value.find(a => a.account_type === 'cash')?.id || '',
    reference_number: '',
  };
  paymentErrors.value = {};
  showPaymentModal.value = true;
};

const handlePayment = async () => {
  if (!paymentForm.value.account_id) {
    paymentErrors.value = { account_id: ['Please select an account'] };
    return;
  }

  paying.value = true;
  try {
    await api.post(`/employee-salaries/${payingSalary.value.id}/pay`, paymentForm.value);
    uiStore.showToast('Salary paid successfully', 'success');
    showPaymentModal.value = false;
    fetchSalaries();
  } catch (error) {
    if (error.response?.status === 422) {
      paymentErrors.value = error.response.data.errors || {};
      // Show message if no field-specific errors (e.g., insufficient balance)
      if (error.response.data.message && !error.response.data.errors) {
        uiStore.showToast(error.response.data.message, 'error');
      }
    } else {
      uiStore.showToast('Failed to process payment', 'error');
    }
  } finally {
    paying.value = false;
  }
};

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
  const method = paymentForm.value.payment_method;
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

// Auto-select account when payment method changes
watch(() => paymentForm.value.payment_method, (method) => {
  if (method === 'cash') {
    const cashAccount = accounts.value.find(a => a.account_type === 'cash');
    paymentForm.value.account_id = cashAccount?.id || '';
  } else if (method === 'upi') {
    const upiAccount = accounts.value.find(a => a.is_upi_account);
    paymentForm.value.account_id = upiAccount?.id || '';
  } else if (method === 'card') {
    const cardAccount = accounts.value.find(a => a.is_card_account);
    paymentForm.value.account_id = cardAccount?.id || '';
  } else {
    const bankAccounts = accounts.value.filter(a => a.account_type === 'bank');
    paymentForm.value.account_id = bankAccounts.length === 1 ? bankAccounts[0].id : '';
  }
});

onMounted(() => {
  fetchSalaries();
  fetchAccounts();
});
</script>

<template>
  <div>
    <div class="flex items-center gap-4 mb-6">
      <Button variant="ghost" @click="router.push('/employees')">
        <ArrowLeftIcon class="w-5 h-5" />
      </Button>
      <div class="flex-1">
        <h1 class="text-2xl font-bold text-gray-900">Salary Management</h1>
        <p class="text-gray-500">Generate and manage monthly salaries</p>
      </div>
      <Button
        v-if="authStore.hasPermission('employees.create')"
        variant="primary"
        :loading="generating"
        @click="generateMonthlySalaries"
      >
        <BanknotesIcon class="w-5 h-5 mr-1" />
        Generate for {{ filters.month }}
      </Button>
    </div>

    <!-- Filters -->
    <Card class="mb-6">
      <div class="flex flex-wrap gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
          <input
            v-model="filters.month"
            type="month"
            class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            @change="fetchSalaries(1)"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select
            v-model="filters.status"
            class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            @change="fetchSalaries(1)"
          >
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="paid">Paid</option>
          </select>
        </div>
      </div>
    </Card>

    <!-- Table -->
    <Card>
      <Table :columns="columns" :data="salaries" :loading="loading">
        <template #employee="{ row }">
          <div>
            <div class="font-medium text-gray-900">{{ row.employee?.name }}</div>
            <div class="text-xs text-gray-500">{{ row.employee?.employee_code }} | {{ row.employee?.designation }}</div>
          </div>
        </template>
        <template #basic_salary="{ row }">
          {{ formatAmount(row.basic_salary) }}
        </template>
        <template #allowances="{ row }">
          <span class="text-green-600">+{{ formatAmount(row.allowances) }}</span>
        </template>
        <template #deductions="{ row }">
          <span class="text-red-600">-{{ formatAmount(row.deductions) }}</span>
        </template>
        <template #net_salary="{ row }">
          <span class="font-semibold">{{ formatAmount(row.net_salary) }}</span>
        </template>
        <template #status="{ row }">
          <span :class="['px-2 py-1 text-xs font-medium rounded-full', row.status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800']">
            {{ row.status === 'paid' ? 'Paid' : 'Pending' }}
          </span>
          <div v-if="row.status === 'paid'" class="text-xs text-gray-500 mt-1">
            {{ formatDate(row.payment_date) }}
          </div>
        </template>
        <template #actions="{ row }">
          <Button
            v-if="row.status === 'pending' && authStore.hasPermission('employees.update')"
            variant="primary"
            size="sm"
            @click="openPaymentModal(row)"
          >
            Pay
          </Button>
          <span v-else-if="row.status === 'paid'" class="text-xs text-gray-500">
            via {{ row.payment_method }}
          </span>
        </template>
      </Table>

      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-4 pt-4 border-t">
        <Button
          v-for="page in meta.last_page"
          :key="page"
          :variant="page === meta.current_page ? 'primary' : 'outline'"
          size="sm"
          @click="fetchSalaries(page)"
        >
          {{ page }}
        </Button>
      </div>
    </Card>

    <!-- Payment Modal -->
    <Modal :show="showPaymentModal" title="Pay Salary" @close="showPaymentModal = false">
      <div v-if="payingSalary" class="space-y-4">
        <div class="p-3 bg-gray-50 rounded-lg">
          <p class="font-medium">{{ payingSalary.employee?.name }}</p>
          <p class="text-sm text-gray-500">{{ payingSalary.salary_month }}</p>
          <p class="text-lg font-bold text-primary-600 mt-1">{{ formatAmount(payingSalary.net_salary) }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
          <input
            v-model="paymentForm.payment_date"
            type="date"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
          <select
            v-model="paymentForm.payment_method"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Account *</label>
          <select
            v-model="paymentForm.account_id"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            :class="{ 'border-red-500': paymentErrors.account_id }"
          >
            <option value="">Select Account</option>
            <option v-for="acc in filteredAccounts" :key="acc.id" :value="acc.id">
              {{ acc.account_name }} ({{ formatAmount(acc.current_balance) }})
            </option>
          </select>
          <p v-if="paymentErrors.account_id" class="mt-1 text-sm text-red-600">{{ paymentErrors.account_id[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
          <input
            v-model="paymentForm.reference_number"
            type="text"
            placeholder="Transaction ID / Cheque No."
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-4 border-t mt-4">
        <Button variant="outline" @click="showPaymentModal = false">Cancel</Button>
        <Button variant="primary" :loading="paying" @click="handlePayment">
          <CheckCircleIcon class="w-5 h-5 mr-1" />
          Confirm Payment
        </Button>
      </div>
    </Modal>
  </div>
</template>
