<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import {
  ArrowLeftIcon,
  PrinterIcon,
  ArrowUpIcon,
  ArrowDownIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const uiStore = useUiStore();

const loading = ref(false);
const accounts = ref([]);
const statement = ref(null);

const filters = ref({
  account_id: '',
  from_date: new Date().toISOString().slice(0, 8) + '01', // First day of current month
  to_date: new Date().toISOString().slice(0, 10), // Today
});

const formatAmount = (amount) => {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount || 0);
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
};

const fetchAccounts = async () => {
  try {
    const response = await api.get('/accounts/all');
    accounts.value = response.data.data;
    if (accounts.value.length > 0 && !filters.value.account_id) {
      filters.value.account_id = accounts.value[0].id;
    }
  } catch (error) {
    uiStore.showToast('Failed to fetch accounts', 'error');
  }
};

const fetchStatement = async () => {
  if (!filters.value.account_id) {
    uiStore.showToast('Please select an account', 'error');
    return;
  }

  loading.value = true;
  try {
    const response = await api.get('/ledger/statement', { params: filters.value });
    statement.value = response.data.data;
  } catch (error) {
    uiStore.showToast('Failed to fetch statement', 'error');
  } finally {
    loading.value = false;
  }
};

const printStatement = () => {
  window.print();
};

const goBack = () => {
  router.push('/ledger');
};

onMounted(async () => {
  await fetchAccounts();
  if (filters.value.account_id) {
    fetchStatement();
  }
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6 print:hidden">
      <div class="flex items-center gap-4">
        <button @click="goBack" class="p-2 hover:bg-gray-100 rounded-lg">
          <ArrowLeftIcon class="w-5 h-5 text-gray-600" />
        </button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Account Statement</h1>
          <p class="text-gray-500">View detailed transactions for an account</p>
        </div>
      </div>
      <Button v-if="statement" variant="outline" @click="printStatement">
        <PrinterIcon class="w-4 h-4 mr-2" />
        Print
      </Button>
    </div>

    <!-- Filters -->
    <Card class="mb-6 print:hidden">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Select
          v-model="filters.account_id"
          label="Account"
          :options="accounts.map(a => ({ value: a.id, label: `${a.account_name} (${a.account_type})` }))"
          required
        />
        <Input
          v-model="filters.from_date"
          label="From Date"
          type="date"
          required
        />
        <Input
          v-model="filters.to_date"
          label="To Date"
          type="date"
          required
        />
        <div class="flex items-end">
          <Button variant="primary" :loading="loading" @click="fetchStatement" class="w-full">
            Generate Statement
          </Button>
        </div>
      </div>
    </Card>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <!-- Statement -->
    <div v-else-if="statement">
      <!-- Header for Print -->
      <div class="hidden print:block mb-6 text-center">
        <h2 class="text-xl font-bold">Account Statement</h2>
        <p class="text-gray-600">{{ statement.account?.account_name }}</p>
        <p class="text-sm text-gray-500">
          Period: {{ formatDate(statement.from_date) }} to {{ formatDate(statement.to_date) }}
        </p>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <Card class="bg-gray-50">
          <p class="text-sm text-gray-600">Opening Balance</p>
          <p class="text-xl font-bold text-gray-900">{{ formatAmount(statement.opening_balance) }}</p>
        </Card>
        <Card class="bg-green-50 border-green-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-green-600">Total Credits</p>
              <p class="text-xl font-bold text-green-700">{{ formatAmount(statement.total_credits) }}</p>
            </div>
            <ArrowUpIcon class="w-6 h-6 text-green-400" />
          </div>
        </Card>
        <Card class="bg-red-50 border-red-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-red-600">Total Debits</p>
              <p class="text-xl font-bold text-red-700">{{ formatAmount(statement.total_debits) }}</p>
            </div>
            <ArrowDownIcon class="w-6 h-6 text-red-400" />
          </div>
        </Card>
        <Card class="bg-blue-50 border-blue-200">
          <p class="text-sm text-blue-600">Closing Balance</p>
          <p class="text-xl font-bold text-blue-700">{{ formatAmount(statement.closing_balance) }}</p>
        </Card>
      </div>

      <!-- Transactions Table -->
      <Card>
        <template #header>
          <div class="flex items-center justify-between">
            <span class="font-semibold">Transactions</span>
            <span class="text-sm text-gray-500">{{ statement.entries?.length || 0 }} entries</span>
          </div>
        </template>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entry #</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Narration</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <!-- Opening Balance Row -->
              <tr class="bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-600" colspan="4">
                  <strong>Opening Balance</strong>
                </td>
                <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">
                  {{ formatAmount(statement.opening_balance) }}
                </td>
              </tr>

              <tr v-for="entry in statement.entries" :key="entry.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(entry.entry_date) }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-600">
                  {{ entry.entry_number }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <span
                    :class="[
                      'px-2 py-1 text-xs font-medium rounded-full',
                      entry.source_type === 'opening_balance' ? 'bg-gray-100 text-gray-700' :
                      entry.source_type === 'booking' ? 'bg-purple-100 text-purple-700' :
                      entry.source_type === 'donation' ? 'bg-green-100 text-green-700' :
                      entry.source_type === 'purchase' ? 'bg-orange-100 text-orange-700' :
                      entry.source_type === 'expense' ? 'bg-red-100 text-red-700' :
                      entry.source_type === 'salary' ? 'bg-blue-100 text-blue-700' :
                      entry.source_type === 'employee_payment' ? 'bg-indigo-100 text-indigo-700' :
                      'bg-yellow-100 text-yellow-700'
                    ]"
                  >
                    {{ entry.source_label }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">
                  {{ entry.narration }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                  <span v-if="entry.type === 'credit'" class="text-green-600 font-medium">
                    {{ formatAmount(entry.amount) }}
                  </span>
                  <span v-else class="text-gray-300">-</span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                  <span v-if="entry.type === 'debit'" class="text-red-600 font-medium">
                    {{ formatAmount(entry.amount) }}
                  </span>
                  <span v-else class="text-gray-300">-</span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
                  {{ formatAmount(entry.balance_after) }}
                </td>
              </tr>

              <!-- Closing Balance Row -->
              <tr class="bg-blue-50">
                <td class="px-4 py-3 text-sm text-blue-800" colspan="4">
                  <strong>Closing Balance</strong>
                </td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-green-600">
                  {{ formatAmount(statement.total_credits) }}
                </td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-red-600">
                  {{ formatAmount(statement.total_debits) }}
                </td>
                <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">
                  {{ formatAmount(statement.closing_balance) }}
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="!statement.entries?.length" class="text-center py-8 text-gray-500">
            No transactions found for this period
          </div>
        </div>
      </Card>
    </div>

    <!-- No Statement -->
    <Card v-else>
      <div class="text-center py-12 text-gray-500">
        Select an account and date range to generate statement
      </div>
    </Card>
  </div>
</template>

<style>
@media print {
  body {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
}
</style>
