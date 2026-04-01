<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Table from '@/components/ui/Table.vue';
import Modal from '@/components/ui/Modal.vue';
import { PlusIcon, FunnelIcon, PencilIcon, CurrencyRupeeIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const uiStore = useUiStore();

const loading = ref(true);
const purchases = ref([]);
const stats = ref(null);
const meta = ref({});
const search = ref('');
const vendorFilter = ref('');
const categoryFilter = ref('');
const purposeFilter = ref('');
const statusFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const showFilters = ref(false);

// Dropdown data
const vendors = ref([]);
const categories = ref([]);
const purposes = ref([]);

// Payment modal
const showPaymentModal = ref(false);
const selectedPurchase = ref(null);
const paymentForm = ref({ amount: 0, payment_method: 'cash', account_id: '' });
const payingPurchase = ref(false);
const accounts = ref([]);

const columns = [
  { key: 'purchase_number', label: 'Purchase #' },
  { key: 'purchase_date', label: 'Date' },
  { key: 'vendor', label: 'Vendor' },
  { key: 'item_description', label: 'Item' },
  { key: 'category', label: 'Category' },
  { key: 'purpose', label: 'Purpose' },
  { key: 'total_amount', label: 'Amount' },
  { key: 'payment_status', label: 'Status' },
  { key: 'actions', label: '' },
];

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
    { value: 'credit', label: 'Credit (Pay Later)' }
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

const fetchAccounts = async () => {
  try {
    const response = await api.get('/accounts/all');
    accounts.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch accounts:', error);
  }
};

const fetchPurchases = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      search: search.value || undefined,
      vendor_id: vendorFilter.value || undefined,
      category_id: categoryFilter.value || undefined,
      purpose_id: purposeFilter.value || undefined,
      payment_status: statusFilter.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    };
    const response = await api.get('/purchases', { params });
    purchases.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch purchases', 'error');
  } finally {
    loading.value = false;
  }
};

const fetchStats = async () => {
  try {
    const response = await api.get('/purchases/stats');
    stats.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch stats:', error);
  }
};

const fetchDropdowns = async () => {
  try {
    const [vendorsRes, categoriesRes, purposesRes] = await Promise.all([
      api.get('/vendors/all'),
      api.get('/purchase-categories/all'),
      api.get('/purchase-purposes/all'),
    ]);
    vendors.value = vendorsRes.data.data;
    categories.value = categoriesRes.data.data;
    purposes.value = purposesRes.data.data;
  } catch (error) {
    console.error('Failed to fetch dropdowns:', error);
  }
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatAmount = (amount) => {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};

const getStatusClass = (status) => {
  switch (status) {
    case 'paid': return 'bg-green-100 text-green-800';
    case 'partial': return 'bg-yellow-100 text-yellow-800';
    default: return 'bg-red-100 text-red-800';
  }
};

const openPaymentModal = (purchase) => {
  selectedPurchase.value = purchase;
  const cashAccount = accounts.value.find(a => a.account_type === 'cash');
  paymentForm.value = {
    amount: purchase.total_amount - purchase.paid_amount,
    payment_method: 'cash',
    account_id: cashAccount?.id || '',
  };
  showPaymentModal.value = true;
};

// Auto-select account when payment method changes
watch(() => paymentForm.value.payment_method, (method) => {
  if (!accounts.value.length) return;
  if (method === 'cash') {
    const cashAccount = accounts.value.find(a => a.account_type === 'cash');
    paymentForm.value.account_id = cashAccount?.id || '';
  } else if (method === 'upi') {
    const upiAccount = accounts.value.find(a => a.is_upi_account);
    paymentForm.value.account_id = upiAccount?.id || '';
  } else if (method === 'card') {
    const cardAccount = accounts.value.find(a => a.is_card_account);
    paymentForm.value.account_id = cardAccount?.id || '';
  } else if (method === 'credit') {
    paymentForm.value.account_id = '';
  } else {
    const bankAccounts = accounts.value.filter(a => a.account_type === 'bank');
    paymentForm.value.account_id = bankAccounts.length === 1 ? bankAccounts[0].id : '';
  }
});

const submitPayment = async () => {
  if (!selectedPurchase.value || paymentForm.value.amount <= 0) return;

  payingPurchase.value = true;
  try {
    await api.post(`/purchases/${selectedPurchase.value.id}/payment`, paymentForm.value);
    uiStore.showToast('Payment recorded successfully', 'success');
    showPaymentModal.value = false;
    fetchPurchases(meta.value.current_page);
    fetchStats();
  } catch (error) {
    uiStore.showToast('Failed to record payment', 'error');
  } finally {
    payingPurchase.value = false;
  }
};

const clearFilters = () => {
  vendorFilter.value = '';
  categoryFilter.value = '';
  purposeFilter.value = '';
  statusFilter.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  search.value = '';
};

watch([search, vendorFilter, categoryFilter, purposeFilter, statusFilter, dateFrom, dateTo], () => {
  fetchPurchases(1);
});

onMounted(() => {
  fetchPurchases();
  fetchStats();
  fetchDropdowns();
  fetchAccounts();
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Purchase Management</h1>
        <p class="text-gray-500">Track temple purchases and expenses</p>
      </div>
      <div class="flex gap-3">
        <Button variant="outline" @click="router.push('/purchases/vendors')">
          Manage Vendors
        </Button>
        <Button variant="primary" @click="router.push('/purchases/new')">
          <PlusIcon class="w-5 h-5 mr-1" />
          New Purchase
        </Button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">Today's Purchases</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.today.count }}</p>
          <p class="text-sm text-primary-600">{{ formatAmount(stats.today.total) }}</p>
        </div>
      </Card>
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">This Month</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.month.count }}</p>
          <p class="text-sm text-primary-600">{{ formatAmount(stats.month.total) }}</p>
        </div>
      </Card>
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">Pending Payments</p>
          <p class="text-2xl font-bold text-red-600">{{ stats.pending.count }}</p>
          <p class="text-sm text-red-600">{{ formatAmount(stats.pending.amount) }}</p>
        </div>
      </Card>
    </div>

    <!-- Filters -->
    <Card class="mb-6">
      <div class="flex items-center justify-between mb-4">
        <div class="flex-1 max-w-md">
          <input
            v-model="search"
            type="text"
            placeholder="Search purchases..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </div>
        <Button variant="ghost" @click="showFilters = !showFilters">
          <FunnelIcon class="w-5 h-5 mr-1" />
          Filters
        </Button>
      </div>

      <div v-if="showFilters" class="grid grid-cols-2 md:grid-cols-6 gap-4 pt-4 border-t">
        <select v-model="vendorFilter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
          <option value="">All Vendors</option>
          <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
        </select>
        <select v-model="categoryFilter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
          <option value="">All Categories</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="purposeFilter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
          <option value="">All Purposes</option>
          <option v-for="p in purposes" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select v-model="statusFilter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="partial">Partial</option>
          <option value="paid">Paid</option>
        </select>
        <input v-model="dateFrom" type="date" class="px-3 py-2 border border-gray-300 rounded-md text-sm" />
        <input v-model="dateTo" type="date" class="px-3 py-2 border border-gray-300 rounded-md text-sm" />
        <Button variant="ghost" size="sm" @click="clearFilters">Clear</Button>
      </div>
    </Card>

    <!-- Table -->
    <Card>
      <Table :columns="columns" :data="purchases" :loading="loading">
        <template #purchase_date="{ row }">
          {{ formatDate(row.purchase_date) }}
        </template>
        <template #vendor="{ row }">
          {{ row.vendor?.name || '-' }}
        </template>
        <template #category="{ row }">
          {{ row.category?.name || '-' }}
        </template>
        <template #purpose="{ row }">
          <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
            {{ row.purpose?.name || '-' }}
          </span>
        </template>
        <template #total_amount="{ row }">
          <div>
            <div class="font-medium">{{ formatAmount(row.total_amount) }}</div>
            <div v-if="row.payment_status !== 'paid'" class="text-xs text-red-600">
              Due: {{ formatAmount(row.total_amount - row.paid_amount) }}
            </div>
          </div>
        </template>
        <template #payment_status="{ row }">
          <div>
            <span :class="['px-2 py-1 text-xs font-medium rounded-full', getStatusClass(row.payment_status)]">
              {{ row.payment_status }}
            </span>
            <div v-if="row.account && row.paid_amount > 0" class="text-xs text-gray-500 mt-1">
              via {{ row.account.account_name }}
            </div>
          </div>
        </template>
        <template #actions="{ row }">
          <div class="flex items-center gap-1">
            <button
              v-if="row.payment_status !== 'paid'"
              @click="openPaymentModal(row)"
              class="p-2 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-lg"
              title="Add Payment"
            >
              <CurrencyRupeeIcon class="w-4 h-4" />
            </button>
            <button
              @click="router.push(`/purchases/${row.id}/edit`)"
              class="p-2 text-gray-500 hover:text-primary-600 hover:bg-gray-100 rounded-lg"
              title="Edit"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
          </div>
        </template>
      </Table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-4 pt-4 border-t">
        <Button
          v-for="page in meta.last_page"
          :key="page"
          :variant="page === meta.current_page ? 'primary' : 'outline'"
          size="sm"
          @click="fetchPurchases(page)"
        >
          {{ page }}
        </Button>
      </div>
    </Card>

    <!-- Payment Modal -->
    <Modal :show="showPaymentModal" title="Record Payment" @close="showPaymentModal = false">
      <div v-if="selectedPurchase" class="space-y-4">
        <div class="p-3 bg-gray-50 rounded-lg">
          <p class="text-sm text-gray-600">Purchase: {{ selectedPurchase.purchase_number }}</p>
          <p class="text-sm text-gray-600">Item: {{ selectedPurchase.item_description }}</p>
          <p class="font-medium">Due: {{ formatAmount(selectedPurchase.total_amount - selectedPurchase.paid_amount) }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
          <input
            v-model.number="paymentForm.amount"
            type="number"
            min="0.01"
            :max="selectedPurchase.total_amount - selectedPurchase.paid_amount"
            step="0.01"
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
          <select v-model="paymentForm.payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>

        <div v-if="paymentForm.payment_method !== 'credit'">
          <label class="block text-sm font-medium text-gray-700 mb-1">Debit From Account *</label>
          <select
            v-model="paymentForm.account_id"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
          >
            <option value="">Select Account</option>
            <option v-for="acc in filteredAccounts" :key="acc.id" :value="acc.id">
              {{ acc.account_name }} (₹{{ parseFloat(acc.current_balance).toLocaleString() }})
            </option>
          </select>
        </div>

        <div class="flex justify-end gap-3 pt-4">
          <Button variant="outline" @click="showPaymentModal = false">Cancel</Button>
          <Button variant="primary" :loading="payingPurchase" @click="submitPayment">Record Payment</Button>
        </div>
      </div>
    </Modal>
  </div>
</template>
