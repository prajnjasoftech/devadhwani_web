<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Table from '@/components/ui/Table.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import { PlusIcon, EyeIcon, CurrencyRupeeIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const bookings = ref([]);
const loading = ref(true);
const search = ref('');
const paymentStatus = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const currentPage = ref(1);
const meta = ref({});
const stats = ref(null);

const columns = [
  { key: 'date', label: 'Date' },
  { key: 'pooja', label: 'Pooja' },
  { key: 'quantity', label: 'Qty / Devotees' },
  { key: 'contact', label: 'Contact' },
  { key: 'amount', label: 'Amount' },
  { key: 'balance', label: 'Balance' },
  { key: 'actions', label: '', class: 'text-right' },
];

const paymentStatuses = [
  { value: '', label: 'All Status' },
  { value: 'pending', label: 'Pending' },
  { value: 'partial', label: 'Partial' },
  { value: 'paid', label: 'Paid' },
];

// Quick payment modal
const showPaymentModal = ref(false);
const selectedBooking = ref(null);
const paymentForm = ref({
  amount: 0,
  payment_method: 'cash',
  account_id: '',
  notes: '',
});
const paymentSaving = ref(false);
const paymentErrors = ref({});
const accounts = ref([]);

// Payment methods - only show UPI/Card if accounts are bound
const paymentMethods = computed(() => {
  const methods = [{ value: 'cash', label: 'Cash' }];
  if (accounts.value.some(a => a.is_upi_account)) {
    methods.push({ value: 'upi', label: 'UPI' });
  }
  if (accounts.value.some(a => a.is_card_account)) {
    methods.push({ value: 'card', label: 'Card' });
  }
  methods.push({ value: 'bank_transfer', label: 'Bank Transfer' });
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

const openPaymentModal = (booking) => {
  selectedBooking.value = booking;
  const cashAccount = accounts.value.find(a => a.account_type === 'cash');
  paymentForm.value = {
    amount: booking.balance_amount,
    payment_method: 'cash',
    account_id: cashAccount?.id || '',
    notes: '',
  };
  paymentErrors.value = {};
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
  } else {
    const bankAccounts = accounts.value.filter(a => a.account_type === 'bank');
    paymentForm.value.account_id = bankAccounts.length === 1 ? bankAccounts[0].id : '';
  }
});

const addPayment = async () => {
  paymentErrors.value = {};
  paymentSaving.value = true;

  try {
    await api.post(`/bookings/${selectedBooking.value.id}/payments`, paymentForm.value);
    uiStore.showToast('Payment added successfully', 'success');
    showPaymentModal.value = false;
    selectedBooking.value = null;
    fetchStats();
    fetchBookings();
  } catch (error) {
    if (error.response?.status === 422) {
      paymentErrors.value = error.response.data.errors || {};
      // Show message if no field-specific errors (e.g., insufficient balance)
      if (error.response.data.message && !error.response.data.errors) {
        uiStore.showToast(error.response.data.message, 'error');
      }
    } else {
      uiStore.showToast('Failed to add payment', 'error');
    }
  } finally {
    paymentSaving.value = false;
  }
};

const fetchStats = async () => {
  try {
    const response = await api.get('/bookings/stats');
    stats.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch stats:', error);
  }
};

const fetchBookings = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      search: search.value || undefined,
      payment_status: paymentStatus.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    };

    const response = await api.get('/bookings', { params });
    bookings.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch bookings', 'error');
  } finally {
    loading.value = false;
  }
};

const getStatusColor = (status) => {
  switch (status) {
    case 'paid': return 'bg-green-100 text-green-700';
    case 'partial': return 'bg-yellow-100 text-yellow-700';
    case 'pending': return 'bg-red-100 text-red-700';
    default: return 'bg-gray-100 text-gray-700';
  }
};

watch([search, paymentStatus, dateFrom, dateTo], () => {
  currentPage.value = 1;
  fetchBookings();
});

watch(currentPage, fetchBookings);

onMounted(() => {
  fetchStats();
  fetchBookings();
  fetchAccounts();
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Bookings</h1>
        <p class="text-gray-500">Manage pooja bookings and payments</p>
      </div>
      <Button
        v-if="authStore.hasPermission('bookings.create')"
        @click="router.push('/bookings/create')"
      >
        <PlusIcon class="w-5 h-5 mr-2" />
        New Booking
      </Button>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">Today's Bookings</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.today.bookings }}</p>
          <p class="text-sm text-green-600">₹{{ Number(stats.today.collected).toLocaleString() }} collected</p>
        </div>
      </Card>
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">This Month</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.month.bookings }}</p>
          <p class="text-sm text-green-600">₹{{ Number(stats.month.collected).toLocaleString() }} collected</p>
        </div>
      </Card>
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">Outstanding</p>
          <p class="text-2xl font-bold text-red-600">{{ stats.outstanding.count }}</p>
          <p class="text-sm text-red-600">₹{{ Number(stats.outstanding.amount).toLocaleString() }}</p>
        </div>
      </Card>
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">Month Total</p>
          <p class="text-2xl font-bold text-primary-600">₹{{ Number(stats.month.amount).toLocaleString() }}</p>
        </div>
      </Card>
    </div>

    <Card>
      <div class="flex flex-wrap items-center gap-3 mb-6">
        <input
          v-model="search"
          type="text"
          placeholder="Search..."
          class="w-64 px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        />
        <select
          v-model="paymentStatus"
          class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <option v-for="status in paymentStatuses" :key="status.value" :value="status.value">
            {{ status.label }}
          </option>
        </select>
        <div class="flex items-center gap-2">
          <input
            v-model="dateFrom"
            type="date"
            class="px-2 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
          <span class="text-gray-400">-</span>
          <input
            v-model="dateTo"
            type="date"
            class="px-2 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
          <button
            v-if="dateFrom || dateTo"
            @click="dateFrom = ''; dateTo = ''"
            class="text-xs text-primary-600 hover:text-primary-800"
          >
            Clear
          </button>
        </div>
      </div>

      <Table :columns="columns" :data="bookings" :loading="loading" clickable @row-click="(row) => router.push(`/bookings/${row.id}`)">
        <template #date="{ row }">
          <span class="text-sm text-gray-600">{{ row.booking_date_formatted }}</span>
        </template>

        <template #pooja="{ row }">
          <div class="max-w-xs">
            <div v-for="(item, idx) in row.items?.slice(0, 2)" :key="idx" class="text-sm">
              <span class="font-medium">{{ item.pooja?.name || 'Unknown Pooja' }}</span>
              <span v-if="item.deity" class="text-gray-500 text-xs"> - {{ item.deity.name }}</span>
            </div>
            <div v-if="row.items?.length > 2" class="text-xs text-gray-400">
              +{{ row.items.length - 2 }} more
            </div>
            <div v-if="!row.items?.length" class="text-gray-400 text-sm">No items</div>
          </div>
        </template>

        <template #quantity="{ row }">
          <div class="text-sm">
            <div v-for="(item, idx) in row.items?.slice(0, 2)" :key="idx">
              <!-- Show quantity for quantity-based poojas, beneficiary_count for devotee-based -->
              <span class="font-medium">{{ item.quantity > 1 ? item.quantity : (item.beneficiary_count || 1) }}</span>
              <span v-if="item.beneficiaries?.length" class="text-gray-500 text-xs ml-1">
                ({{ item.beneficiaries.map(b => b.name).slice(0, 2).join(', ') }}<span v-if="item.beneficiaries.length > 2">...</span>)
              </span>
            </div>
            <div v-if="row.items?.length > 2" class="text-xs text-gray-400">...</div>
          </div>
        </template>

        <template #contact="{ row }">
          <div>
            <p class="font-medium text-sm">{{ row.contact_name || '-' }}</p>
            <p class="text-xs text-gray-500">{{ row.contact_number || '-' }}</p>
          </div>
        </template>

        <template #amount="{ row }">
          <span :class="['font-medium', row.payment_status === 'paid' ? 'text-green-600' : 'text-red-600']">
            {{ row.total_amount_formatted }}
          </span>
        </template>

        <template #balance="{ row }">
          <span :class="['font-medium', row.balance_amount > 0 ? 'text-red-600' : 'text-green-600']">
            {{ row.balance_amount_formatted }}
          </span>
        </template>

        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-2" @click.stop>
            <Button
              v-if="row.balance_amount > 0 && authStore.hasPermission('bookings.update')"
              variant="ghost"
              size="sm"
              title="Add Payment"
              @click="openPaymentModal(row)"
            >
              <CurrencyRupeeIcon class="w-4 h-4 text-gray-900" />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              title="View Details"
              @click="router.push(`/bookings/${row.id}`)"
            >
              <EyeIcon class="w-4 h-4" />
            </Button>
          </div>
        </template>
      </Table>

      <Pagination
        v-if="meta.last_page > 1"
        :current-page="currentPage"
        :last-page="meta.last_page"
        :total="meta.total"
        @page-change="currentPage = $event"
        class="mt-6"
      />
    </Card>

    <!-- Quick Payment Modal -->
    <Modal :show="showPaymentModal" title="Add Payment" @close="showPaymentModal = false">
      <div v-if="selectedBooking" class="mb-4 p-3 bg-gray-50 rounded-lg">
        <p class="text-sm text-gray-600">
          <span class="font-mono font-medium text-primary-600">{{ selectedBooking.booking_number }}</span>
          <span v-if="selectedBooking.contact_name"> - {{ selectedBooking.contact_name }}</span>
        </p>
        <p class="text-sm mt-1">
          Balance: <span class="font-medium text-red-600">{{ selectedBooking.balance_amount_formatted }}</span>
        </p>
      </div>

      <form @submit.prevent="addPayment" class="space-y-4">
        <Input
          v-model.number="paymentForm.amount"
          label="Amount"
          type="number"
          min="1"
          :max="selectedBooking?.balance_amount"
          required
          :error="paymentErrors.amount?.[0]"
        />
        <Select
          v-model="paymentForm.payment_method"
          label="Payment Method"
          :options="paymentMethods"
          required
          :error="paymentErrors.payment_method?.[0]"
        />
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Credit To Account *</label>
          <select
            v-model="paymentForm.account_id"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            :class="{ 'border-red-500': paymentErrors.account_id }"
          >
            <option value="">Select Account</option>
            <option v-for="acc in filteredAccounts" :key="acc.id" :value="acc.id">
              {{ acc.account_name }} (₹{{ parseFloat(acc.current_balance).toLocaleString() }})
            </option>
          </select>
          <p v-if="paymentErrors.account_id" class="mt-1 text-sm text-red-600">{{ paymentErrors.account_id[0] }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea
            v-model="paymentForm.notes"
            rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Payment notes..."
          ></textarea>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <Button variant="outline" type="button" @click="showPaymentModal = false">
            Cancel
          </Button>
          <Button variant="primary" type="submit" :loading="paymentSaving">
            Add Payment
          </Button>
        </div>
      </form>
    </Modal>
  </div>
</template>
