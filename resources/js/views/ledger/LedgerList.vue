<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import {
  ArrowUpIcon,
  ArrowDownIcon,
  DocumentTextIcon,
  FunnelIcon,
  MagnifyingGlassIcon,
  CalendarIcon,
  ArrowsRightLeftIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const uiStore = useUiStore();

const loading = ref(true);
const entries = ref([]);
const accounts = ref([]);
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 20,
  total: 0,
});

// Filters
const showFilters = ref(false);
const filters = ref({
  search: '',
  account_id: '',
  type: '',
  source_type: '',
  date_from: '',
  date_to: '',
});

// Stats
const stats = ref({
  today: { credits: 0, debits: 0, net: 0 },
  month: { credits: 0, debits: 0, net: 0 },
});

const sourceTypeOptions = [
  { value: '', label: 'All Sources' },
  { value: 'opening_balance', label: 'Opening Balance' },
  { value: 'booking', label: 'Booking' },
  { value: 'donation', label: 'Donation' },
  { value: 'purchase', label: 'Purchase' },
  { value: 'expense', label: 'Expense' },
  { value: 'salary', label: 'Salary Payment' },
  { value: 'employee_payment', label: 'Employee Payment' },
  { value: 'transfer', label: 'Transfer' },
  { value: 'adjustment', label: 'Adjustment' },
];

// Transfer Modal
const showTransferModal = ref(false);
const transferring = ref(false);
const transferForm = ref({
  from_account_id: '',
  to_account_id: '',
  amount: '',
  narration: '',
  entry_date: new Date().toISOString().split('T')[0],
});

const fromAccountOptions = computed(() => {
  return accounts.value.map(a => ({
    value: a.id,
    label: `${a.account_name} (${formatAmount(a.current_balance)})`,
  }));
});

const toAccountOptions = computed(() => {
  return accounts.value
    .filter(a => a.id !== parseInt(transferForm.value.from_account_id))
    .map(a => ({
      value: a.id,
      label: `${a.account_name} (${formatAmount(a.current_balance)})`,
    }));
});

const openTransferModal = () => {
  transferForm.value = {
    from_account_id: '',
    to_account_id: '',
    amount: '',
    narration: '',
    entry_date: new Date().toISOString().split('T')[0],
  };
  showTransferModal.value = true;
};

const submitTransfer = async () => {
  if (!transferForm.value.from_account_id || !transferForm.value.to_account_id) {
    uiStore.showToast('Please select both accounts', 'error');
    return;
  }
  if (!transferForm.value.amount || parseFloat(transferForm.value.amount) <= 0) {
    uiStore.showToast('Please enter a valid amount', 'error');
    return;
  }
  if (!transferForm.value.narration) {
    uiStore.showToast('Please enter a narration', 'error');
    return;
  }

  transferring.value = true;
  try {
    const response = await api.post('/ledger/transfer', transferForm.value);
    if (response.data.success) {
      uiStore.showToast('Transfer completed successfully', 'success');
      showTransferModal.value = false;
      fetchAccounts();
      fetchEntries();
      fetchStats();
    }
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Failed to complete transfer', 'error');
  } finally {
    transferring.value = false;
  }
};

const typeOptions = [
  { value: '', label: 'All Types' },
  { value: 'credit', label: 'Credit' },
  { value: 'debit', label: 'Debit' },
];

const formatAmount = (amount) => {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
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
  } catch (error) {
    console.error('Failed to fetch accounts', error);
  }
};

const fetchEntries = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      per_page: pagination.value.perPage,
      ...Object.fromEntries(Object.entries(filters.value).filter(([_, v]) => v)),
    };
    const response = await api.get('/ledger', { params });
    entries.value = response.data.data;
    pagination.value = {
      ...pagination.value,
      currentPage: response.data.meta.current_page,
      lastPage: response.data.meta.last_page,
      total: response.data.meta.total,
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch ledger entries', 'error');
  } finally {
    loading.value = false;
  }
};

const fetchStats = async () => {
  try {
    const response = await api.get('/ledger/stats');
    stats.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch stats', error);
  }
};

const applyFilters = () => {
  fetchEntries(1);
};

const clearFilters = () => {
  filters.value = {
    search: '',
    account_id: '',
    type: '',
    source_type: '',
    date_from: '',
    date_to: '',
  };
  fetchEntries(1);
};

const goToStatement = () => {
  router.push('/ledger/statement');
};

const goToBalanceSheet = () => {
  router.push('/ledger/balance-sheet');
};

// Debounced search
let searchTimeout;
watch(() => filters.value.search, () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchEntries(1);
  }, 300);
});

onMounted(() => {
  fetchAccounts();
  fetchEntries();
  fetchStats();
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Ledger</h1>
        <p class="text-gray-500">View all financial transactions</p>
      </div>
      <div class="flex gap-2">
        <Button variant="primary" @click="openTransferModal">
          <ArrowsRightLeftIcon class="w-4 h-4 mr-2" />
          Transfer
        </Button>
        <Button variant="outline" @click="goToStatement">
          <DocumentTextIcon class="w-4 h-4 mr-2" />
          Account Statement
        </Button>
        <Button variant="outline" @click="goToBalanceSheet">
          <CalendarIcon class="w-4 h-4 mr-2" />
          Balance Sheet
        </Button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
      <Card class="bg-green-50 border-green-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-green-600 font-medium">Today's Credits</p>
            <p class="text-2xl font-bold text-green-700">{{ formatAmount(stats.today.credits) }}</p>
          </div>
          <ArrowUpIcon class="w-8 h-8 text-green-400" />
        </div>
      </Card>

      <Card class="bg-red-50 border-red-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-red-600 font-medium">Today's Debits</p>
            <p class="text-2xl font-bold text-red-700">{{ formatAmount(stats.today.debits) }}</p>
          </div>
          <ArrowDownIcon class="w-8 h-8 text-red-400" />
        </div>
      </Card>

      <Card class="bg-blue-50 border-blue-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-blue-600 font-medium">This Month Net</p>
            <p class="text-2xl font-bold text-blue-700">{{ formatAmount(stats.month.net) }}</p>
          </div>
          <DocumentTextIcon class="w-8 h-8 text-blue-400" />
        </div>
      </Card>
    </div>

    <!-- Filters -->
    <Card class="mb-6">
      <div class="flex items-center gap-4 mb-4">
        <div class="flex-1 relative">
          <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search by entry number or narration..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>
        <Button variant="outline" @click="showFilters = !showFilters">
          <FunnelIcon class="w-4 h-4 mr-2" />
          {{ showFilters ? 'Hide' : 'Show' }} Filters
        </Button>
      </div>

      <div v-if="showFilters" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 pt-4 border-t">
        <Select
          v-model="filters.account_id"
          label="Account"
          :options="[{ value: '', label: 'All Accounts' }, ...accounts.map(a => ({ value: a.id, label: a.account_name }))]"
        />
        <Select
          v-model="filters.type"
          label="Type"
          :options="typeOptions"
        />
        <Select
          v-model="filters.source_type"
          label="Source"
          :options="sourceTypeOptions"
        />
        <Input
          v-model="filters.date_from"
          label="From Date"
          type="date"
        />
        <Input
          v-model="filters.date_to"
          label="To Date"
          type="date"
        />
        <div class="md:col-span-3 lg:col-span-5 flex justify-end gap-2">
          <Button variant="outline" @click="clearFilters">Clear</Button>
          <Button variant="primary" @click="applyFilters">Apply Filters</Button>
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

    <!-- Ledger Table -->
    <Card v-else>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entry #</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Narration</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="entry in entries" :key="entry.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                {{ formatDate(entry.entry_date) }}
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-600">
                {{ entry.entry_number }}
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                {{ entry.account?.account_name }}
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
                    entry.source_type === 'transfer' ? 'bg-cyan-100 text-cyan-700' :
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
          </tbody>
        </table>

        <div v-if="entries.length === 0" class="text-center py-12 text-gray-500">
          No ledger entries found
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.lastPage > 1" class="mt-4 pt-4 border-t">
        <Pagination
          :current-page="pagination.currentPage"
          :last-page="pagination.lastPage"
          :total="pagination.total"
          @page-change="fetchEntries"
        />
      </div>
    </Card>

    <!-- Transfer Modal -->
    <Modal
      :show="showTransferModal"
      title="Transfer Between Accounts"
      @close="showTransferModal = false"
    >
      <form @submit.prevent="submitTransfer" class="space-y-4">
        <Select
          v-model="transferForm.from_account_id"
          label="From Account"
          :options="fromAccountOptions"
          required
        />

        <Select
          v-model="transferForm.to_account_id"
          label="To Account"
          :options="toAccountOptions"
          required
          :disabled="!transferForm.from_account_id"
        />

        <Input
          v-model="transferForm.amount"
          label="Amount"
          type="number"
          step="0.01"
          min="0.01"
          required
        />

        <Input
          v-model="transferForm.narration"
          label="Narration / Remarks"
          placeholder="e.g., Cash deposited to bank"
          required
        />

        <Input
          v-model="transferForm.entry_date"
          label="Date"
          type="date"
          required
        />

        <div class="flex justify-end gap-2 pt-4">
          <Button type="button" variant="outline" @click="showTransferModal = false">
            Cancel
          </Button>
          <Button type="submit" variant="primary" :loading="transferring">
            Complete Transfer
          </Button>
        </div>
      </form>
    </Modal>
  </div>
</template>
