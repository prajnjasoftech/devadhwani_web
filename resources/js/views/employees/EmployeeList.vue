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
  PlusIcon,
  PencilIcon,
  NoSymbolIcon,
  CheckCircleIcon,
  UserCircleIcon,
  CurrencyRupeeIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const loading = ref(true);
const employees = ref([]);
const stats = ref(null);
const meta = ref({});
const search = ref('');

const columns = [
  { key: 'employee_code', label: 'Code' },
  { key: 'name', label: 'Name' },
  { key: 'designation', label: 'Designation' },
  { key: 'contact_number', label: 'Contact' },
  { key: 'basic_salary', label: 'Basic Salary' },
  { key: 'is_user', label: 'App User' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: '' },
];

const fetchEmployees = async (page = 1) => {
  loading.value = true;
  try {
    const params = { page, search: search.value || undefined };
    const response = await api.get('/employees', { params });
    employees.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch employees', 'error');
  } finally {
    loading.value = false;
  }
};

const fetchStats = async () => {
  try {
    const response = await api.get('/employees/stats');
    stats.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch stats:', error);
  }
};

const formatAmount = (amount) => {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};

const toggleStatus = async (employee) => {
  try {
    await api.put(`/employees/${employee.id}`, { is_active: !employee.is_active });
    employee.is_active = !employee.is_active;
    uiStore.showToast(`Employee ${employee.is_active ? 'activated' : 'deactivated'}`, 'success');
  } catch (error) {
    uiStore.showToast('Failed to update status', 'error');
  }
};

watch(search, () => fetchEmployees(1));

onMounted(() => {
  fetchEmployees();
  fetchStats();
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Employee Management</h1>
        <p class="text-gray-500">Manage temple employees and their salaries</p>
      </div>
      <div class="flex gap-3">
        <Button variant="outline" @click="router.push('/employees/salaries')">
          <CurrencyRupeeIcon class="w-5 h-5 mr-1" />
          Salaries
        </Button>
        <Button variant="outline" @click="router.push('/employees/payments')">
          Other Payments
        </Button>
        <Button
          v-if="authStore.hasPermission('employees.create')"
          variant="primary"
          @click="router.push('/employees/new')"
        >
          <PlusIcon class="w-5 h-5 mr-1" />
          Add Employee
        </Button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
      <Card class="bg-blue-50 border-blue-200">
        <div class="flex items-center gap-4">
          <div class="p-3 bg-blue-100 rounded-full">
            <UsersIcon class="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <p class="text-sm text-blue-600">Active Employees</p>
            <p class="text-2xl font-bold text-blue-700">{{ stats.total_employees }}</p>
          </div>
        </div>
      </Card>
      <Card class="bg-green-50 border-green-200">
        <div class="flex items-center gap-4">
          <div class="p-3 bg-green-100 rounded-full">
            <CurrencyRupeeIcon class="w-6 h-6 text-green-600" />
          </div>
          <div>
            <p class="text-sm text-green-600">Monthly Salary</p>
            <p class="text-2xl font-bold text-green-700">{{ formatAmount(stats.total_monthly_salary) }}</p>
          </div>
        </div>
      </Card>
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">Pending Salaries</p>
          <p class="text-2xl font-bold" :class="stats.pending_salaries > 0 ? 'text-red-600' : 'text-gray-700'">
            {{ stats.pending_salaries }}
          </p>
          <p class="text-xs text-gray-500">this month</p>
        </div>
      </Card>
    </div>

    <!-- Table -->
    <Card>
      <div class="mb-4">
        <input
          v-model="search"
          type="text"
          placeholder="Search employees..."
          class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
        />
      </div>

      <Table :columns="columns" :data="employees" :loading="loading">
        <template #name="{ row }">
          <div class="flex items-center gap-2">
            <UserCircleIcon class="w-8 h-8 text-gray-400" />
            <div>
              <div class="font-medium text-gray-900">{{ row.name }}</div>
              <div v-if="row.email" class="text-xs text-gray-500">{{ row.email }}</div>
            </div>
          </div>
        </template>
        <template #basic_salary="{ row }">
          {{ formatAmount(row.basic_salary) }}
        </template>
        <template #is_user="{ row }">
          <span v-if="row.user_id" class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-700">
            Yes
          </span>
          <span v-else class="text-gray-400">-</span>
        </template>
        <template #is_active="{ row }">
          <span :class="['px-2 py-1 text-xs font-medium rounded-full', row.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
            {{ row.is_active ? 'Active' : 'Inactive' }}
          </span>
        </template>
        <template #actions="{ row }">
          <div class="flex items-center gap-1">
            <button
              v-if="authStore.hasPermission('employees.update')"
              @click="router.push(`/employees/${row.id}/edit`)"
              class="p-2 text-gray-500 hover:text-primary-600 hover:bg-gray-100 rounded-lg"
              title="Edit"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              v-if="authStore.hasPermission('employees.update')"
              @click="toggleStatus(row)"
              :class="['p-2 rounded-lg', row.is_active ? 'text-gray-500 hover:text-red-600 hover:bg-red-50' : 'text-gray-500 hover:text-green-600 hover:bg-green-50']"
              :title="row.is_active ? 'Deactivate' : 'Activate'"
            >
              <NoSymbolIcon v-if="row.is_active" class="w-4 h-4" />
              <CheckCircleIcon v-else class="w-4 h-4" />
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
          @click="fetchEmployees(page)"
        >
          {{ page }}
        </Button>
      </div>
    </Card>
  </div>
</template>
