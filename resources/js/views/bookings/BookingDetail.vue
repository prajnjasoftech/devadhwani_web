<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Modal from '@/components/ui/Modal.vue';
import {
  ArrowLeftIcon,
  PlusIcon,
  PencilIcon,
  CalendarDaysIcon,
  UserIcon,
  CurrencyRupeeIcon,
  CheckCircleIcon,
  ClockIcon,
  XCircleIcon,
  PrinterIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const loading = ref(true);
const booking = ref(null);
const payments = ref([]);
const accounts = ref([]);

// Payment modal
const showPaymentModal = ref(false);
const paymentForm = ref({
  amount: 0,
  payment_method: 'cash',
  account_id: '',
  notes: '',
});
const paymentSaving = ref(false);
const paymentErrors = ref({});

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
    // bank_transfer - show all bank accounts
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
    // For bank_transfer - select first bank account or clear
    const bankAccounts = accounts.value.filter(a => a.account_type === 'bank');
    paymentForm.value.account_id = bankAccounts.length === 1 ? bankAccounts[0].id : '';
  }
});

const getStatusColor = (status) => {
  switch (status) {
    case 'paid': return 'bg-green-100 text-green-700';
    case 'partial': return 'bg-yellow-100 text-yellow-700';
    case 'pending': return 'bg-red-100 text-red-700';
    case 'completed': return 'bg-green-100 text-green-700';
    case 'cancelled': return 'bg-gray-100 text-gray-700';
    default: return 'bg-gray-100 text-gray-700';
  }
};

const getScheduleIcon = (status) => {
  switch (status) {
    case 'completed': return CheckCircleIcon;
    case 'cancelled': return XCircleIcon;
    default: return ClockIcon;
  }
};

const fetchBooking = async () => {
  loading.value = true;
  try {
    const [bookingRes] = await Promise.all([
      api.get(`/bookings/${route.params.id}`),
      fetchAccounts(),
    ]);
    booking.value = bookingRes.data.data;
    await fetchPayments();
  } catch (error) {
    uiStore.showToast('Failed to fetch booking', 'error');
    router.push('/bookings');
  } finally {
    loading.value = false;
  }
};

const fetchPayments = async () => {
  try {
    const response = await api.get(`/bookings/${route.params.id}/payments`);
    payments.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch payments:', error);
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

const openPaymentModal = () => {
  const cashAccount = accounts.value.find(a => a.account_type === 'cash');
  paymentForm.value = {
    amount: booking.value.balance_amount,
    payment_method: 'cash',
    account_id: cashAccount?.id || '',
    notes: '',
  };
  paymentErrors.value = {};
  showPaymentModal.value = true;
};

const addPayment = async () => {
  paymentErrors.value = {};
  paymentSaving.value = true;

  try {
    const response = await api.post(`/bookings/${route.params.id}/payments`, paymentForm.value);
    uiStore.showToast('Payment added successfully', 'success');
    showPaymentModal.value = false;

    // Fetch updated booking first, then print
    await fetchBooking();

    // Print receipt for this payment
    printPaymentReceipt(response.data.data.payment);
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

// Store last payment for print view
const lastPayment = ref(null);

// Print payment receipt using window.print()
const printPaymentReceipt = (payment) => {
  lastPayment.value = payment;
  nextTick(() => {
    window.print();
  });
};

// Print booking receipt
const printBookingReceipt = () => {
  lastPayment.value = null;
  nextTick(() => {
    window.print();
  });
};

onMounted(fetchBooking);
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 print:hidden">
      <div class="flex items-center gap-4">
        <Button variant="ghost" @click="router.push('/bookings')">
          <ArrowLeftIcon class="w-5 h-5" />
        </Button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">
            Booking Details
          </h1>
          <p v-if="booking" class="text-gray-500 font-mono">{{ booking.booking_number }}</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <Button
          v-if="booking"
          variant="secondary"
          @click="printBookingReceipt"
        >
          <PrinterIcon class="w-5 h-5 mr-2" />
          Print
        </Button>
        <Button
          v-if="booking && booking.booking_status !== 'cancelled' && authStore.hasPermission('bookings.update')"
          variant="outline"
          @click="router.push(`/bookings/${route.params.id}/edit`)"
        >
          <PencilIcon class="w-5 h-5 mr-2" />
          Edit
        </Button>
        <Button
          v-if="booking && booking.balance_amount > 0 && authStore.hasPermission('bookings.update')"
          @click="openPaymentModal"
        >
          <PlusIcon class="w-5 h-5 mr-2" />
          Add Payment
        </Button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12 print:hidden">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <div v-else-if="booking" class="space-y-6 print:hidden">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-100 rounded-lg">
              <CalendarDaysIcon class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Booking Date</p>
              <p class="font-medium">{{ booking.booking_date_formatted }}</p>
            </div>
          </div>
        </Card>
        <Card>
          <div class="flex items-center gap-3">
            <div class="p-2 bg-green-100 rounded-lg">
              <CurrencyRupeeIcon class="w-6 h-6 text-green-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Total Amount</p>
              <p class="font-medium">{{ booking.total_amount_formatted }}</p>
            </div>
          </div>
        </Card>
        <Card>
          <div class="flex items-center gap-3">
            <div class="p-2 bg-orange-100 rounded-lg">
              <CurrencyRupeeIcon class="w-6 h-6 text-orange-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Balance</p>
              <p class="font-medium" :class="booking.balance_amount > 0 ? 'text-red-600' : 'text-green-600'">
                {{ booking.balance_amount_formatted }}
              </p>
            </div>
          </div>
        </Card>
        <Card>
          <div class="flex items-center gap-3">
            <div
              class="px-3 py-1 rounded-full text-sm font-medium"
              :class="getStatusColor(booking.payment_status)"
            >
              {{ booking.payment_status_label }}
            </div>
          </div>
        </Card>
      </div>

      <!-- Contact Info -->
      <Card title="Contact Information">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <p class="text-sm text-gray-500">Name</p>
            <p class="font-medium">{{ booking.contact_name }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Phone</p>
            <p class="font-medium">{{ booking.contact_number || '-' }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Address</p>
            <p class="font-medium">{{ booking.contact_address || '-' }}</p>
          </div>
        </div>
        <div v-if="booking.notes" class="mt-4 pt-4 border-t">
          <p class="text-sm text-gray-500">Notes</p>
          <p class="text-gray-700">{{ booking.notes }}</p>
        </div>
      </Card>

      <!-- Booking Items -->
      <Card title="Booking Items">
        <div class="space-y-4">
          <div
            v-for="item in booking.items"
            :key="item.id"
            class="border border-gray-200 rounded-lg p-4"
          >
            <div class="flex items-start justify-between mb-3">
              <div>
                <h4 class="font-medium text-gray-900">{{ item.pooja?.name }}</h4>
                <p class="text-sm text-gray-500">{{ item.deity?.name }}</p>
                <p class="text-sm text-gray-600 mt-1">
                  <template v-if="item.frequency === 'once'">
                    {{ item.start_date_formatted }}
                  </template>
                  <template v-else>
                    From {{ item.start_date_formatted }} to {{ item.end_date_formatted }} {{ item.frequency }}
                    <span v-if="item.schedule_label" class="text-primary-600 font-medium">{{ item.schedule_label.replace('On ', 'on ') }}</span>
                    <span class="text-gray-500">({{ item.occurrence_count }} poojas)</span>
                  </template>
                </p>
              </div>
              <div class="text-right">
                <p class="font-medium">{{ item.total_amount_formatted }}</p>
                <p class="text-xs text-gray-500">
                  <span v-if="item.quantity > 1">{{ item.quantity }} × </span>
                  {{ item.occurrence_count }} pooja<span v-if="item.occurrence_count > 1">s</span>
                </p>
              </div>
            </div>

            <!-- Beneficiaries -->
            <div v-if="item.beneficiaries?.length" class="mb-3">
              <p class="text-sm font-medium text-gray-700 mb-2">Beneficiaries</p>
              <div class="flex flex-wrap gap-2">
                <div
                  v-for="ben in item.beneficiaries"
                  :key="ben.id"
                  class="flex items-center gap-2 px-3 py-1 bg-gray-100 rounded-full text-sm"
                >
                  <UserIcon class="w-4 h-4 text-gray-500" />
                  <span>{{ ben.name }}</span>
                  <span v-if="ben.nakshathra" class="text-gray-500">
                    ({{ ben.nakshathra.malayalam_name }})
                  </span>
                </div>
              </div>
            </div>

            <!-- Schedules -->
            <div v-if="item.schedules?.length">
              <p class="text-sm font-medium text-gray-700 mb-2">Schedules</p>
              <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">
                <div
                  v-for="schedule in item.schedules"
                  :key="schedule.id"
                  class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm"
                  :class="[
                    schedule.status === 'completed'
                      ? 'bg-green-50 text-green-700'
                      : schedule.status === 'cancelled'
                      ? 'bg-gray-100 text-gray-500 line-through'
                      : 'bg-blue-50 text-blue-700',
                  ]"
                >
                  <component :is="getScheduleIcon(schedule.status)" class="w-4 h-4" />
                  <span>{{ schedule.scheduled_date_formatted }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Payments -->
      <Card title="Payment History">
        <div v-if="payments.length === 0" class="text-center py-8 text-gray-500">
          No payments recorded yet.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Received By</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="payment in payments" :key="payment.id">
                <td class="px-4 py-3 text-sm">{{ payment.payment_date_formatted }}</td>
                <td class="px-4 py-3 text-sm font-medium text-green-600">{{ payment.amount_formatted }}</td>
                <td class="px-4 py-3 text-sm capitalize">{{ payment.payment_method }}</td>
                <td class="px-4 py-3 text-sm font-mono text-gray-500">{{ payment.reference_number || '-' }}</td>
                <td class="px-4 py-3 text-sm">{{ payment.received_by?.name || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </div>

    <!-- Payment Modal -->
    <Modal :show="showPaymentModal" title="Add Payment" @close="showPaymentModal = false" class="print:hidden">
      <form @submit.prevent="addPayment" class="space-y-4">
        <Input
          v-model.number="paymentForm.amount"
          label="Amount"
          type="number"
          min="1"
          :max="booking?.balance_amount"
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
        <Select
          v-model="paymentForm.account_id"
          label="Credit To Account"
          :options="filteredAccounts.map(a => ({ value: a.id, label: `${a.account_name} (₹${parseFloat(a.current_balance).toLocaleString()})` }))"
          required
          :error="paymentErrors.account_id?.[0]"
        />
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea
            v-model="paymentForm.notes"
            rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
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

    <!-- Print View (Thermal Printer Friendly) -->
    <div v-if="booking" class="hidden print:block print-view">
      <div class="text-center mb-3 border-b border-dashed border-gray-400 pb-2">
        <div class="font-bold text-lg">{{ authStore.user?.temple?.temple_name || 'Temple' }}</div>
        <div class="text-sm">{{ lastPayment ? 'Payment Receipt' : 'Booking Receipt' }}</div>
      </div>

      <div class="border-b border-dashed border-gray-400 pb-2 mb-2">
        <div class="flex justify-between"><span>Booking No:</span><span class="font-bold">{{ booking.booking_number }}</span></div>
        <div class="flex justify-between"><span>Date:</span><span>{{ lastPayment ? lastPayment.payment_date_formatted : booking.booking_date_formatted }}</span></div>
        <div v-if="booking.contact_name" class="flex justify-between"><span>Name:</span><span>{{ booking.contact_name }}</span></div>
        <div v-if="booking.contact_number" class="flex justify-between"><span>Phone:</span><span>{{ booking.contact_number }}</span></div>
      </div>

      <div class="border-b border-dashed border-gray-400 pb-2 mb-2">
        <div v-for="item in booking.items" :key="item.id" class="mb-2">
          <div class="flex justify-between font-bold">
            <span>{{ item.pooja?.name }}</span>
            <span>{{ item.total_amount_formatted }}</span>
          </div>
          <div v-if="item.deity?.name" class="text-xs text-gray-600 pl-2">
            {{ item.deity?.name }}
          </div>
          <div class="text-xs text-gray-600 pl-2">
            <template v-if="item.frequency === 'once'">
              {{ item.start_date_formatted }}
            </template>
            <template v-else>
              From {{ item.start_date_formatted }} to {{ item.end_date_formatted }} {{ item.frequency }}
              <span v-if="item.schedule_label">{{ item.schedule_label.replace('On ', 'on ') }}</span>
              ({{ item.occurrence_count }} poojas)
            </template>
          </div>
          <div v-for="ben in item.beneficiaries" :key="ben.id" class="text-xs pl-2 flex justify-between">
            <span>{{ ben.name }}</span>
            <span class="font-medium">{{ ben.nakshathra?.malayalam_name || '' }}</span>
          </div>
        </div>
      </div>

      <!-- Payment specific section -->
      <div v-if="lastPayment" class="border-b border-dashed border-gray-400 pb-2 mb-2">
        <div class="flex justify-between font-bold"><span>Amount Paid:</span><span>{{ lastPayment.amount_formatted }}</span></div>
        <div class="flex justify-between"><span>Method:</span><span class="capitalize">{{ lastPayment.payment_method }}</span></div>
        <div v-if="lastPayment.reference_number" class="flex justify-between"><span>Reference:</span><span>{{ lastPayment.reference_number }}</span></div>
      </div>

      <div class="border-b border-dashed border-gray-400 pb-2 mb-2">
        <div class="flex justify-between font-bold"><span>Total:</span><span>{{ booking.total_amount_formatted }}</span></div>
        <div class="flex justify-between"><span>Paid:</span><span>{{ booking.paid_amount_formatted }}</span></div>
        <div v-if="booking.balance_amount > 0" class="flex justify-between font-bold"><span>Balance:</span><span>{{ booking.balance_amount_formatted }}</span></div>
      </div>

      <div class="text-center text-xs text-gray-500 mt-3">
        <div>Thank you!</div>
        <div>Powered by Prajnja Softech LLP</div>
      </div>
    </div>
  </div>
</template>

<style>
@media print {
  @page {
    size: 80mm auto;
    margin: 2mm;
  }

  .print-view {
    font-family: monospace;
    font-size: 14px;
    line-height: 1.4;
    max-width: 76mm;
    padding: 0;
  }

  .print-view .font-bold {
    font-weight: bold;
  }

  .print-view .text-lg {
    font-size: 16px;
  }

  .print-view .text-sm {
    font-size: 13px;
  }

  .print-view .text-xs {
    font-size: 12px;
  }
}
</style>
