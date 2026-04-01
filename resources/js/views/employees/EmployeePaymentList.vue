<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Table from '@/components/ui/Table.vue';
import {
  ArrowLeftIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  BanknotesIcon,
  GiftIcon,
  ArrowPathIcon,
  CurrencyRupeeIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const loading = ref(true);
const payments = ref([]);
const employees = ref([]);
const stats = ref(null);
const meta = ref({});

// Filters
const filters = ref({
  employee_id: '',
  payment_type: '',
  date_from: '',
  date_to: '',
});

const columns = [
  { key: 'payment_date', label: 'Date' },
  { key: 'employee', label: 'Employee' },
  { key: 'payment_type', label: 'Type' },
  { key: 'description', label: 'Description' },
  { key: 'amount', label: 'Amount' },
  { key: 'payment_method', label: 'Method' },
  { key: 'actions', label: '' },
];

const paymentTypes = [
  { value: 'bonus', label: 'Bonus', color: 'green', icon: GiftIcon },
  { value: 'advance', label: 'Advance', color: 'blue', icon: BanknotesIcon },
  { value: 'reimbursement', label: 'Reimbursement', color: 'purple', icon: ArrowPathIcon },
  { value: 'incentive', label: 'Incentive', color: 'orange', icon: CurrencyRupeeIcon },
  { value: 'other', label: 'Other', color: 'gray', icon: BanknotesIcon },
];

const fetchPayments = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      employee_id: filters.value.employee_id || undefined,
      payment_type: filters.value.payment_type || undefined,
      date_from: filters.value.date_from || undefined,
      date_to: filters.value.date_to || undefined,
    };
    const response = await api.get('/employee-payments', { params });
    payments.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch payments', 'error');
  } finally {
    loading.value = false;
  }
};

const fetchEmployees = async () => {
  try {
    const response = await api.get('/employees/all');
    employees.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch employees:', error);
  }
};

const fetchStats = async () => {
  try {
    const response = await api.get('/employee-payments/stats');
    stats.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch stats:', error);
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

const getTypeConfig = (type) => {
  return paymentTypes.find(t => t.value === type) || paymentTypes[4];
};

const deletePayment = async (payment) => {
  if (!confirm('Are you sure you want to delete this payment?')) return;

  try {
    await api.delete(`/employee-payments/${payment.id}`);
    uiStore.showToast('Payment deleted successfully', 'success');
    fetchPayments();
    fetchStats();
  } catch (error) {
    uiStore.showToast('Failed to delete payment', 'error');
  }
};

watch(filters, () => fetchPayments(1), { deep: true });

onMounted(() => {
  fetchPayments();
  fetchEmployees();
  fetchStats();
});
</script>

<template>
  <div>
    <div class="flex items-center gap-4 mb-6">
      <Button variant="ghost" @click="router.push('/employees')">
        <ArrowLeftIcon class="w-5 h-5" />
      </Button>
      <div class="flex-1">
        <h1 class="text-2xl font-bold text-gray-900">Other Payments</h1>
        <p class="text-gray-500">Bonus, advance, reimbursements and other payments</p>
      </div>
      <Button
        v-if="authStore.hasPermission('employees.create')"
        variant="primary"
        @click="router.push('/employees/payments/new')"
      >
        <PlusIcon class="w-5 h-5 mr-1" />
        Add Payment
      </Button>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
      <Card class="text-center">
        <p class="text-sm text-gray-500">This Month Total</p>
        <p class="text-xl font-bold text-gray-900">{{ formatAmount(stats.this_month?.total || 0) }}</p>
      </Card>
      <Card v-for="type in paymentTypes.slice(0, 4)" :key="type.value" class="text-center">
        <p class="text-sm" :class="`text-${type.color}-600`">{{ type.label }}</p>
        <p class="text-lg font-bold" :class="`text-${type.color}-700`">
          {{ formatAmount(stats.this_month?.by_type?.find(t => t.payment_type === type.value)?.total || 0) }}
        </p>
      </Card>
    </div>

    <!-- Filters -->
    <Card class="mb-6">
      <div class="flex flex-wrap gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
          <select
            v-model="filters.employee_id"
            class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">All Employees</option>
            <option v-for="emp in employees" :key="emp.id" :value="emp.id">
              {{ emp.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
          <select
            v-model="filters.payment_type"
            class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">All Types</option>
            <option v-for="type in paymentTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
          <input
            v-model="filters.date_from"
            type="date"
            class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
          <input
            v-model="filters.date_to"
            type="date"
            class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </div>
      </div>
    </Card>

    <!-- Table -->
    <Card>
      <Table :columns="columns" :data="payments" :loading="loading">
        <template #payment_date="{ row }">
          {{ formatDate(row.payment_date) }}
        </template>
        <template #employee="{ row }">
          <div>
            <div class="font-medium text-gray-900">{{ row.employee?.name }}</div>
            <div class="text-xs text-gray-500">{{ row.employee?.employee_code }}</div>
          </div>
        </template>
        <template #payment_type="{ row }">
          <span :class="['px-2 py-1 text-xs font-medium rounded-full', `bg-${getTypeConfig(row.payment_type).color}-100 text-${getTypeConfig(row.payment_type).color}-800`]">
            {{ getTypeConfig(row.payment_type).label }}
          </span>
        </template>
        <template #amount="{ row }">
          <span class="font-semibold">{{ formatAmount(row.amount) }}</span>
        </template>
        <template #payment_method="{ row }">
          <div>
            <span class="text-gray-600 capitalize">{{ row.payment_method?.replace('_', ' ') }}</span>
            <div v-if="row.account" class="text-xs text-gray-500">via {{ row.account.account_name }}</div>
            <div v-if="row.reference_number" class="text-xs text-gray-400">{{ row.reference_number }}</div>
          </div>
        </template>
        <template #actions="{ row }">
          <div class="flex items-center gap-1">
            <button
              v-if="authStore.hasPermission('employees.update')"
              @click="router.push(`/employees/payments/${row.id}/edit`)"
              class="p-2 text-gray-500 hover:text-primary-600 hover:bg-gray-100 rounded-lg"
              title="Edit"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              v-if="authStore.hasPermission('employees.delete')"
              @click="deletePayment(row)"
              class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg"
              title="Delete"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </template>
      </Table>

      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-4 pt-4 border-t">
        <Button
          v-for="page in meta.last_page"
          :key="page"
          :variant="page === meta.current_page ? 'primary' : 'outline'"
          size="sm"
          @click="fetchPayments(page)"
        >
          {{ page }}
        </Button>
      </div>
    </Card>
  </div>
</template>
