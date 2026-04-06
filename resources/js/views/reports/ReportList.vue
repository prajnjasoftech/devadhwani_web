<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import {
  CalendarIcon,
  PrinterIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  BanknotesIcon,
  DocumentTextIcon,
  CurrencyRupeeIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();

// State
const loading = ref(true);
const reportData = ref(null);

// Date filter options (same as dashboard)
const filterOptions = [
  { value: 'today', label: 'Today' },
  { value: 'yesterday', label: 'Yesterday' },
  { value: 'this_week', label: 'This Week' },
  { value: 'this_month', label: 'This Month' },
  { value: 'last_month', label: 'Last Month' },
  { value: 'custom', label: 'Custom Range' },
];

const selectedFilter = ref('today');
const customStartDate = ref('');
const customEndDate = ref('');

// Helper to format date as YYYY-MM-DD
const formatDate = (date) => date.toISOString().split('T')[0];

// Computed date range based on selected filter
const dateRange = computed(() => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  switch (selectedFilter.value) {
    case 'today':
      return {
        start_date: formatDate(today),
        end_date: formatDate(today),
      };
    case 'yesterday': {
      const yesterday = new Date(today);
      yesterday.setDate(yesterday.getDate() - 1);
      return {
        start_date: formatDate(yesterday),
        end_date: formatDate(yesterday),
      };
    }
    case 'this_week': {
      const startOfWeek = new Date(today);
      startOfWeek.setDate(today.getDate() - today.getDay());
      return {
        start_date: formatDate(startOfWeek),
        end_date: formatDate(today),
      };
    }
    case 'this_month': {
      const start = new Date(today.getFullYear(), today.getMonth(), 1);
      const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
      return {
        start_date: formatDate(start),
        end_date: formatDate(end),
      };
    }
    case 'last_month': {
      const start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
      const end = new Date(today.getFullYear(), today.getMonth(), 0);
      return {
        start_date: formatDate(start),
        end_date: formatDate(end),
      };
    }
    case 'custom':
      return {
        start_date: customStartDate.value || formatDate(today),
        end_date: customEndDate.value || formatDate(today),
      };
    default:
      return {
        start_date: formatDate(today),
        end_date: formatDate(today),
      };
  }
});

// Fetch report data
const fetchReport = async () => {
  loading.value = true;
  try {
    const params = dateRange.value;
    const response = await api.get('/reports/daily', { params });
    reportData.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch report:', error);
  } finally {
    loading.value = false;
  }
};

// Print report
const printReport = () => {
  window.print();
};

// Watch for filter change
watch(selectedFilter, (newVal) => {
  if (newVal !== 'custom') {
    fetchReport();
  }
});

// Watch custom dates
watch([customStartDate, customEndDate], () => {
  if (selectedFilter.value === 'custom' && customStartDate.value && customEndDate.value) {
    fetchReport();
  }
});

onMounted(fetchReport);
</script>

<template>
  <div class="report-container">
    <!-- Header with filters -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 no-print">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Daily Report</h1>
        <p class="text-gray-500">Income and Expense Summary</p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <CalendarIcon class="w-5 h-5 text-gray-400" />
        <select
          v-model="selectedFilter"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        >
          <option v-for="opt in filterOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>

        <!-- Custom Date Range -->
        <template v-if="selectedFilter === 'custom'">
          <input
            v-model="customStartDate"
            type="date"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
          <span class="text-gray-400">to</span>
          <input
            v-model="customEndDate"
            type="date"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </template>

        <Button @click="printReport" variant="secondary" class="ml-2">
          <PrinterIcon class="w-4 h-4 mr-2" />
          Print
        </Button>
      </div>
    </div>

    <!-- Print Header (only visible when printing) -->
    <div class="print-header hidden print:block mb-6">
      <h1 class="text-2xl font-bold text-center">{{ authStore.user?.temple?.name || 'Temple' }}</h1>
      <h2 class="text-lg text-center text-gray-600">Daily Report</h2>
      <p class="text-center text-gray-500">{{ reportData?.period?.display }}</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <template v-else-if="reportData">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Total Income</p>
              <p class="text-2xl font-bold text-green-600">{{ reportData.summary.total_income_formatted }}</p>
            </div>
            <div class="p-3 bg-green-100 rounded-lg">
              <ArrowTrendingUpIcon class="w-6 h-6 text-green-600" />
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Total Expenses</p>
              <p class="text-2xl font-bold text-red-600">{{ reportData.summary.total_expenses_formatted }}</p>
            </div>
            <div class="p-3 bg-red-100 rounded-lg">
              <ArrowTrendingDownIcon class="w-6 h-6 text-red-600" />
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Net Balance</p>
              <p class="text-2xl font-bold" :class="reportData.summary.net_balance >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ reportData.summary.net_balance_formatted }}
              </p>
            </div>
            <div class="p-3 bg-blue-100 rounded-lg">
              <BanknotesIcon class="w-6 h-6 text-blue-600" />
            </div>
          </div>
        </Card>
      </div>

      <!-- INCOME SECTION -->
      <div class="mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
          <ArrowTrendingUpIcon class="w-6 h-6 text-green-600" />
          Income
          <span class="text-green-600">({{ reportData.income.total_formatted }})</span>
        </h2>

        <!-- Bookings -->
        <Card class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <DocumentTextIcon class="w-5 h-5 text-indigo-600" />
              Bookings
              <span class="text-sm font-normal text-gray-500">({{ reportData.income.bookings.count }} bookings)</span>
            </h3>
            <span class="text-lg font-bold text-green-600">₹{{ reportData.income.bookings.total_paid.toLocaleString() }}</span>
          </div>

          <div v-if="reportData.income.bookings.data.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Booking #</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Date</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Contact</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Poojas</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-500">Items</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Paid</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-500">Status</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="booking in reportData.income.bookings.data" :key="booking.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ booking.booking_number }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ booking.booking_date }}</td>
                  <td class="px-3 py-2 text-gray-600">
                    <div>{{ booking.contact_name || '-' }}</div>
                    <div class="text-xs text-gray-400">{{ booking.contact_number || '' }}</div>
                  </td>
                  <td class="px-3 py-2 text-gray-600">
                    <div v-for="pooja in booking.poojas" :key="pooja" class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded inline-block mr-1 mb-1">
                      {{ pooja }}
                    </div>
                  </td>
                  <td class="px-3 py-2 text-center text-gray-600">{{ booking.items_count }}</td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ booking.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium text-green-600">₹{{ booking.paid_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-center">
                    <span
                      class="px-2 py-0.5 text-xs rounded-full"
                      :class="{
                        'bg-green-100 text-green-700': booking.payment_status === 'paid',
                        'bg-orange-100 text-orange-700': booking.payment_status === 'partial',
                        'bg-red-100 text-red-700': booking.payment_status === 'pending',
                      }"
                    >
                      {{ booking.payment_status }}
                    </span>
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="5" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-semibold text-gray-700">₹{{ reportData.income.bookings.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold text-green-600">₹{{ reportData.income.bookings.total_paid.toLocaleString() }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div v-else class="text-center py-4 text-gray-400">No bookings for this period</div>
        </Card>

        <!-- Donations -->
        <Card>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <CurrencyRupeeIcon class="w-5 h-5 text-purple-600" />
              Donations
              <span class="text-sm font-normal text-gray-500">({{ reportData.income.donations.count }} donations)</span>
            </h3>
            <span class="text-lg font-bold text-green-600">₹{{ reportData.income.donations.total_amount.toLocaleString() }}</span>
          </div>

          <div v-if="reportData.income.donations.data.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Donation #</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Date</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Donor</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Type</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Head/Asset</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Amount</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="donation in reportData.income.donations.data" :key="donation.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ donation.donation_number }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ donation.donation_date }}</td>
                  <td class="px-3 py-2 text-gray-600">
                    <div>{{ donation.donor_name || 'Anonymous' }}</div>
                    <div class="text-xs text-gray-400">{{ donation.donor_contact || '' }}</div>
                  </td>
                  <td class="px-3 py-2">
                    <span
                      class="px-2 py-0.5 text-xs rounded-full"
                      :class="donation.donation_type === 'financial' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                    >
                      {{ donation.donation_type }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-gray-600">{{ donation.head_name || donation.asset_type || '-' }}</td>
                  <td class="px-3 py-2 text-right font-medium text-green-600">
                    ₹{{ (donation.amount || donation.estimated_value || 0).toLocaleString() }}
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="5" class="px-3 py-2 font-semibold text-gray-700">Total Financial Donations</td>
                  <td class="px-3 py-2 text-right font-bold text-green-600">₹{{ reportData.income.donations.total_amount.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div v-else class="text-center py-4 text-gray-400">No donations for this period</div>
        </Card>
      </div>

      <!-- EXPENSE SECTION -->
      <div class="mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
          <ArrowTrendingDownIcon class="w-6 h-6 text-red-600" />
          Expenses
          <span class="text-red-600">({{ reportData.expenses.total_formatted }})</span>
        </h2>

        <!-- Purchases -->
        <Card class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
              Purchases
              <span class="text-sm font-normal text-gray-500">({{ reportData.expenses.purchases.count }})</span>
            </h3>
            <span class="text-lg font-bold text-red-600">₹{{ reportData.expenses.purchases.total_paid.toLocaleString() }}</span>
          </div>

          <div v-if="reportData.expenses.purchases.data.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Purchase #</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Date</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Vendor</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Category</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Description</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Paid</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="purchase in reportData.expenses.purchases.data" :key="purchase.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ purchase.purchase_number }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ purchase.purchase_date }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ purchase.vendor_name }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ purchase.category }}</td>
                  <td class="px-3 py-2 text-gray-600 max-w-xs truncate">{{ purchase.description || '-' }}</td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ purchase.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium text-red-600">₹{{ purchase.paid_amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="5" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-semibold text-gray-700">₹{{ reportData.expenses.purchases.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold text-red-600">₹{{ reportData.expenses.purchases.total_paid.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div v-else class="text-center py-4 text-gray-400">No purchases for this period</div>
        </Card>

        <!-- Expenses -->
        <Card class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
              Other Expenses
              <span class="text-sm font-normal text-gray-500">({{ reportData.expenses.expenses.count }})</span>
            </h3>
            <span class="text-lg font-bold text-red-600">₹{{ reportData.expenses.expenses.total_paid.toLocaleString() }}</span>
          </div>

          <div v-if="reportData.expenses.expenses.data.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Expense #</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Date</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Category</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Description</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Paid</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="expense in reportData.expenses.expenses.data" :key="expense.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ expense.expense_number }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ expense.expense_date }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ expense.category }}</td>
                  <td class="px-3 py-2 text-gray-600 max-w-xs truncate">{{ expense.description || '-' }}</td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ expense.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium text-red-600">₹{{ expense.paid_amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="4" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-semibold text-gray-700">₹{{ reportData.expenses.expenses.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold text-red-600">₹{{ reportData.expenses.expenses.total_paid.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div v-else class="text-center py-4 text-gray-400">No expenses for this period</div>
        </Card>

        <!-- Salaries -->
        <Card class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
              Salaries Paid
              <span class="text-sm font-normal text-gray-500">({{ reportData.expenses.salaries.count }})</span>
            </h3>
            <span class="text-lg font-bold text-red-600">₹{{ reportData.expenses.salaries.total_paid.toLocaleString() }}</span>
          </div>

          <div v-if="reportData.expenses.salaries.data.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Employee</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Code</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Month/Year</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Payment Date</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Gross</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Deductions</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Net Paid</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="salary in reportData.expenses.salaries.data" :key="salary.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ salary.employee_name }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ salary.employee_code }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ salary.month_year }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ salary.payment_date }}</td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ salary.gross_salary.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right text-gray-600">₹{{ salary.deductions.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium text-red-600">₹{{ salary.net_salary.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="6" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-bold text-red-600">₹{{ reportData.expenses.salaries.total_paid.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div v-else class="text-center py-4 text-gray-400">No salaries paid in this period</div>
        </Card>

        <!-- Employee Payments -->
        <Card>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
              Other Employee Payments
              <span class="text-sm font-normal text-gray-500">({{ reportData.expenses.employee_payments.count }})</span>
            </h3>
            <span class="text-lg font-bold text-red-600">₹{{ reportData.expenses.employee_payments.total_paid.toLocaleString() }}</span>
          </div>

          <div v-if="reportData.expenses.employee_payments.data.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Employee</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Code</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Type</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Date</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Description</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Amount</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="payment in reportData.expenses.employee_payments.data" :key="payment.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ payment.employee_name }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ payment.employee_code }}</td>
                  <td class="px-3 py-2">
                    <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700 capitalize">
                      {{ payment.payment_type }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-gray-600">{{ payment.payment_date }}</td>
                  <td class="px-3 py-2 text-gray-600 max-w-xs truncate">{{ payment.description || '-' }}</td>
                  <td class="px-3 py-2 text-right font-medium text-red-600">₹{{ payment.amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="5" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-bold text-red-600">₹{{ reportData.expenses.employee_payments.total_paid.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div v-else class="text-center py-4 text-gray-400">No employee payments in this period</div>
        </Card>
      </div>

      <!-- Final Summary (Print Footer) -->
      <Card class="print:mt-8">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Summary - {{ reportData.period.display }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="p-4 bg-green-50 rounded-lg">
            <p class="text-sm text-green-700">Total Income</p>
            <p class="text-2xl font-bold text-green-600">{{ reportData.summary.total_income_formatted }}</p>
            <div class="text-xs text-green-600 mt-2">
              <div>Bookings: ₹{{ reportData.income.bookings.total_paid.toLocaleString() }}</div>
              <div>Donations: ₹{{ reportData.income.donations.total_amount.toLocaleString() }}</div>
            </div>
          </div>
          <div class="p-4 bg-red-50 rounded-lg">
            <p class="text-sm text-red-700">Total Expenses</p>
            <p class="text-2xl font-bold text-red-600">{{ reportData.summary.total_expenses_formatted }}</p>
            <div class="text-xs text-red-600 mt-2">
              <div>Purchases: ₹{{ reportData.expenses.purchases.total_paid.toLocaleString() }}</div>
              <div>Expenses: ₹{{ reportData.expenses.expenses.total_paid.toLocaleString() }}</div>
              <div>Salaries: ₹{{ reportData.expenses.salaries.total_paid.toLocaleString() }}</div>
              <div>Other Payments: ₹{{ reportData.expenses.employee_payments.total_paid.toLocaleString() }}</div>
            </div>
          </div>
          <div class="p-4 rounded-lg" :class="reportData.summary.net_balance >= 0 ? 'bg-blue-50' : 'bg-orange-50'">
            <p class="text-sm" :class="reportData.summary.net_balance >= 0 ? 'text-blue-700' : 'text-orange-700'">Net Balance</p>
            <p class="text-2xl font-bold" :class="reportData.summary.net_balance >= 0 ? 'text-blue-600' : 'text-orange-600'">
              {{ reportData.summary.net_balance_formatted }}
            </p>
          </div>
        </div>
      </Card>
    </template>
  </div>
</template>

<style>
@media print {
  .no-print {
    display: none !important;
  }

  .print-header {
    display: block !important;
  }

  .report-container {
    padding: 0;
    max-width: 100%;
  }

  body {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
}
</style>
