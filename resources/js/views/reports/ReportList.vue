<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import VueApexCharts from 'vue3-apexcharts';
import {
  CalendarIcon,
  PrinterIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  BanknotesIcon,
  DocumentTextIcon,
  CurrencyRupeeIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  GiftIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();

// State
const loading = ref(true);
const reportData = ref(null);

// Date filter options
const filterOptions = [
  { value: 'today', label: 'Today' },
  { value: 'yesterday', label: 'Yesterday' },
  { value: 'this_week', label: 'This Week' },
  { value: 'this_month', label: 'This Month' },
  { value: 'last_month', label: 'Last Month' },
  { value: 'this_year', label: 'This Year' },
  { value: 'custom', label: 'Custom Range' },
];

const selectedFilter = ref('today');
const customStartDate = ref('');
const customEndDate = ref('');

// Helper to format date as YYYY-MM-DD (using local time, not UTC)
const formatDate = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

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
    case 'this_year': {
      const start = new Date(today.getFullYear(), 0, 1);
      return {
        start_date: formatDate(start),
        end_date: formatDate(today),
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

// Print report with custom filename
const printReport = () => {
  const originalTitle = document.title;
  const { start_date, end_date } = dateRange.value;

  // Set filename based on date range
  let newTitle;
  if (start_date === end_date) {
    newTitle = `Report-${start_date}`;
  } else {
    newTitle = `Report-${start_date}-to-${end_date}`;
  }

  document.title = newTitle;

  // Force browser to recognize title change
  document.head.querySelector('title').textContent = newTitle;

  // Restore title after print dialog closes
  const restoreTitle = () => {
    document.title = originalTitle;
    window.removeEventListener('afterprint', restoreTitle);
  };
  window.addEventListener('afterprint', restoreTitle);

  // Delay to ensure Chrome picks up title change
  requestAnimationFrame(() => {
    window.print();
  });
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

// Chart computed properties
const incomeVsExpenseChart = computed(() => {
  if (!reportData.value) return null;
  const income = reportData.value.summary.total_income || 0;
  const expense = reportData.value.summary.total_expenses || 0;
  if (income === 0 && expense === 0) return null;

  return {
    series: [income, expense],
    options: {
      chart: { type: 'donut', height: 280 },
      labels: ['Income', 'Expenses'],
      colors: ['#16a34a', '#dc2626'],
      legend: { position: 'bottom' },
      plotOptions: {
        pie: {
          donut: {
            size: '60%',
            labels: {
              show: true,
              total: {
                show: true,
                label: 'Net',
                formatter: () => `₹${(income - expense).toLocaleString()}`
              }
            }
          }
        }
      },
      dataLabels: {
        formatter: (val, opts) => {
          const value = opts.w.config.series[opts.seriesIndex];
          return `₹${value.toLocaleString()}`;
        }
      }
    }
  };
});

const categoryBreakdownChart = computed(() => {
  if (!reportData.value) return null;

  const categories = [];
  const amounts = [];
  const colors = [];

  // Income categories
  if (reportData.value.income.bookings.total_paid > 0) {
    categories.push('Bookings');
    amounts.push(reportData.value.income.bookings.total_paid);
    colors.push('#22c55e');
  }
  if (reportData.value.income.donations.total_amount > 0) {
    categories.push('Donations');
    amounts.push(reportData.value.income.donations.total_amount);
    colors.push('#a855f7');
  }

  // Expense categories
  if (reportData.value.expenses.purchases.total_paid > 0) {
    categories.push('Purchases');
    amounts.push(reportData.value.expenses.purchases.total_paid);
    colors.push('#ef4444');
  }
  if (reportData.value.expenses.expenses.total_paid > 0) {
    categories.push('Expenses');
    amounts.push(reportData.value.expenses.expenses.total_paid);
    colors.push('#f97316');
  }
  if (reportData.value.expenses.salaries.total_paid > 0) {
    categories.push('Salaries');
    amounts.push(reportData.value.expenses.salaries.total_paid);
    colors.push('#eab308');
  }
  if (reportData.value.expenses.employee_payments.total_paid > 0) {
    categories.push('Employee Payments');
    amounts.push(reportData.value.expenses.employee_payments.total_paid);
    colors.push('#f59e0b');
  }

  if (categories.length === 0) return null;

  return {
    series: [{ name: 'Amount', data: amounts }],
    options: {
      chart: { type: 'bar', height: 280 },
      plotOptions: {
        bar: { horizontal: true, borderRadius: 4, distributed: true }
      },
      colors: colors,
      xaxis: { categories: categories },
      yaxis: { labels: { style: { fontSize: '12px' } } },
      dataLabels: {
        formatter: (val) => `₹${val.toLocaleString()}`
      },
      legend: { show: false },
      tooltip: {
        y: { formatter: (val) => `₹${val.toLocaleString()}` }
      }
    }
  };
});

const paidVsPendingChart = computed(() => {
  if (!reportData.value || !reportData.value.pending) return null;

  const bookingsPaid = reportData.value.income.bookings.total_paid || 0;
  const bookingsPending = reportData.value.income.bookings.total_pending || 0;
  const purchasesPaid = reportData.value.expenses.purchases.total_paid || 0;
  const purchasesPending = reportData.value.expenses.purchases.total_pending || 0;
  const expensesPaid = reportData.value.expenses.expenses.total_paid || 0;
  const expensesPending = reportData.value.expenses.expenses.total_pending || 0;

  if (bookingsPaid === 0 && bookingsPending === 0 && purchasesPaid === 0 && purchasesPending === 0 && expensesPaid === 0 && expensesPending === 0) {
    return null;
  }

  return {
    series: [
      { name: 'Paid', data: [bookingsPaid, purchasesPaid, expensesPaid] },
      { name: 'Pending', data: [bookingsPending, purchasesPending, expensesPending] }
    ],
    options: {
      chart: { type: 'bar', height: 280, stacked: true },
      plotOptions: { bar: { horizontal: false, borderRadius: 4 } },
      colors: ['#22c55e', '#ef4444'],
      xaxis: { categories: ['Bookings', 'Purchases', 'Expenses'] },
      yaxis: { labels: { formatter: (val) => `₹${val.toLocaleString()}` } },
      legend: { position: 'top' },
      dataLabels: { enabled: false },
      tooltip: {
        y: { formatter: (val) => `₹${val.toLocaleString()}` }
      }
    }
  };
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
      <h1 class="text-2xl font-bold text-center">{{ authStore.user?.temple?.temple_name || 'Temple' }}</h1>
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

      <!-- Pending Summary Cards -->
      <div v-if="reportData.pending && (reportData.pending.receivables.total > 0 || reportData.pending.payables.total > 0)" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Pending Receivables</p>
              <p class="text-2xl font-bold text-red-600">{{ reportData.pending.receivables.total_formatted }}</p>
              <p class="text-xs text-gray-400 mt-1">{{ reportData.pending.receivables.bookings.count }} bookings</p>
            </div>
            <div class="p-3 bg-red-100 rounded-lg">
              <ClockIcon class="w-6 h-6 text-red-600" />
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Pending Payables</p>
              <p class="text-2xl font-bold text-red-600">{{ reportData.pending.payables.total_formatted }}</p>
              <p class="text-xs text-gray-400 mt-1">
                {{ reportData.pending.payables.purchases.count + reportData.pending.payables.expenses.count + reportData.pending.payables.salaries.count }} items
              </p>
            </div>
            <div class="p-3 bg-red-100 rounded-lg">
              <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
            </div>
          </div>
        </Card>
      </div>

      <!-- Visual Charts -->
      <div v-if="incomeVsExpenseChart || categoryBreakdownChart || paidVsPendingChart" class="charts-container grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <!-- Income vs Expense Donut Chart -->
        <Card v-if="incomeVsExpenseChart" class="chart-card">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">Income vs Expenses</h3>
          <VueApexCharts
            type="donut"
            :options="incomeVsExpenseChart.options"
            :series="incomeVsExpenseChart.series"
            height="250"
          />
        </Card>

        <!-- Category-wise Bar Chart -->
        <Card v-if="categoryBreakdownChart" class="chart-card">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">Category Breakdown</h3>
          <VueApexCharts
            type="bar"
            :options="categoryBreakdownChart.options"
            :series="categoryBreakdownChart.series"
            height="250"
          />
        </Card>

        <!-- Paid vs Pending Stacked Bar -->
        <Card v-if="paidVsPendingChart" class="chart-card">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">Paid vs Pending</h3>
          <VueApexCharts
            type="bar"
            :options="paidVsPendingChart.options"
            :series="paidVsPendingChart.series"
            height="250"
          />
        </Card>
      </div>

      <!-- INCOME SECTION -->
      <div v-if="reportData.income.bookings.data.length || reportData.income.donations.financial.data.length || reportData.income.donations.asset.data.length" class="mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
          <ArrowTrendingUpIcon class="w-6 h-6 text-green-600" />
          Income
          <span class="text-green-600">({{ reportData.income.total_formatted }})</span>
        </h2>

        <!-- Bookings -->
        <Card v-if="reportData.income.bookings.data.length" class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <DocumentTextIcon class="w-5 h-5 text-indigo-600" />
              Bookings (Pooja-wise)
              <span class="text-sm font-normal text-gray-500">({{ reportData.income.bookings.count }} poojas)</span>
            </h3>
            <span class="text-lg font-bold text-green-600">₹{{ reportData.income.bookings.total_amount.toLocaleString() }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Pooja</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-500">Qty</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-500">Bookings</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Paid</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Pending</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in reportData.income.bookings.data" :key="item.pooja_name" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ item.pooja_name }}</td>
                  <td class="px-3 py-2 text-center text-gray-600">{{ item.quantity }}</td>
                  <td class="px-3 py-2 text-center text-gray-500">{{ item.bookings_count }}</td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ item.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="item.paid_amount > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ item.paid_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="item.pending_amount > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ item.pending_amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="3" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-semibold text-gray-700">₹{{ reportData.income.bookings.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.income.bookings.total_paid > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ reportData.income.bookings.total_paid.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.income.bookings.total_pending > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ reportData.income.bookings.total_pending.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </Card>

        <!-- Financial Donations (Head-wise) -->
        <Card v-if="reportData.income.donations.financial.data.length" class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <CurrencyRupeeIcon class="w-5 h-5 text-purple-600" />
              Financial Donations (Head-wise)
              <span class="text-sm font-normal text-gray-500">({{ reportData.income.donations.financial.count }} donations)</span>
            </h3>
            <span class="text-lg font-bold text-green-600">₹{{ reportData.income.donations.financial.total_amount.toLocaleString() }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Donation Head</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-500">Count</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Amount</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in reportData.income.donations.financial.data" :key="item.head_name" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ item.head_name }}</td>
                  <td class="px-3 py-2 text-center text-gray-600">{{ item.count }}</td>
                  <td class="px-3 py-2 text-right font-medium text-green-600">₹{{ item.total_amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-center font-semibold text-gray-700">{{ reportData.income.donations.financial.count }}</td>
                  <td class="px-3 py-2 text-right font-bold text-green-600">₹{{ reportData.income.donations.financial.total_amount.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </Card>

        <!-- Asset Donations (Type-wise) -->
        <Card v-if="reportData.income.donations.asset.data.length">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <GiftIcon class="w-5 h-5 text-amber-600" />
              Asset Donations (Type-wise)
              <span class="text-sm font-normal text-gray-500">({{ reportData.income.donations.asset.count }} donations)</span>
            </h3>
            <span class="text-lg font-bold text-amber-600">₹{{ reportData.income.donations.asset.total_value.toLocaleString() }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Asset Type</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-500">Count</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Est. Value</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in reportData.income.donations.asset.data" :key="item.asset_type" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ item.asset_type }}</td>
                  <td class="px-3 py-2 text-center text-gray-600">{{ item.count }}</td>
                  <td class="px-3 py-2 text-right font-medium text-amber-600">₹{{ item.total_value.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-center font-semibold text-gray-700">{{ reportData.income.donations.asset.count }}</td>
                  <td class="px-3 py-2 text-right font-bold text-amber-600">₹{{ reportData.income.donations.asset.total_value.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </Card>
      </div>

      <!-- EXPENSE SECTION -->
      <div v-if="reportData.expenses.purchases.data.length || reportData.expenses.expenses.data.length || reportData.expenses.salaries.data.length || reportData.expenses.employee_payments.data.length" class="mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
          <ArrowTrendingDownIcon class="w-6 h-6 text-red-600" />
          Expenses
          <span class="text-red-600">({{ reportData.expenses.total_formatted }})</span>
        </h2>

        <!-- Purchases (Category-wise) -->
        <Card v-if="reportData.expenses.purchases.data.length" class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
              Purchases (Category-wise)
              <span class="text-sm font-normal text-gray-500">({{ reportData.expenses.purchases.count }} categories)</span>
            </h3>
            <span class="text-lg font-bold text-red-600">₹{{ reportData.expenses.purchases.total_paid.toLocaleString() }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Category</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-500">Count</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Paid</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Pending</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in reportData.expenses.purchases.data" :key="item.category_name" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ item.category_name }}</td>
                  <td class="px-3 py-2 text-center text-gray-600">{{ item.count }}</td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ item.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="item.paid_amount > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ item.paid_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="item.pending_amount > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ item.pending_amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="2" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-semibold text-gray-700">₹{{ reportData.expenses.purchases.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.expenses.purchases.total_paid > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ reportData.expenses.purchases.total_paid.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.expenses.purchases.total_pending > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ reportData.expenses.purchases.total_pending.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </Card>

        <!-- Other Expenses (Category-wise) -->
        <Card v-if="reportData.expenses.expenses.data.length" class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
              Other Expenses (Category-wise)
              <span class="text-sm font-normal text-gray-500">({{ reportData.expenses.expenses.count }} categories)</span>
            </h3>
            <span class="text-lg font-bold text-red-600">₹{{ reportData.expenses.expenses.total_paid.toLocaleString() }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Category</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-500">Count</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Paid</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Pending</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in reportData.expenses.expenses.data" :key="item.category_name" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ item.category_name }}</td>
                  <td class="px-3 py-2 text-center text-gray-600">{{ item.count }}</td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ item.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="item.paid_amount > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ item.paid_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="item.pending_amount > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ item.pending_amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="2" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-semibold text-gray-700">₹{{ reportData.expenses.expenses.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.expenses.expenses.total_paid > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ reportData.expenses.expenses.total_paid.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.expenses.expenses.total_pending > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ reportData.expenses.expenses.total_pending.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </Card>

        <!-- Salaries (Employee-wise) -->
        <Card v-if="reportData.expenses.salaries.data.length" class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
              Salaries Paid (Employee-wise)
              <span class="text-sm font-normal text-gray-500">({{ reportData.expenses.salaries.count }} employees)</span>
            </h3>
            <span class="text-lg font-bold text-red-600">₹{{ reportData.expenses.salaries.total_paid.toLocaleString() }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Employee</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Code</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-500">Months</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Total Paid</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="salary in reportData.expenses.salaries.data" :key="salary.employee_name" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ salary.employee_name }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ salary.employee_code }}</td>
                  <td class="px-3 py-2 text-center text-gray-600">{{ salary.count }}</td>
                  <td class="px-3 py-2 text-right font-medium text-red-600">₹{{ salary.total_net_salary.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="3" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-bold text-red-600">₹{{ reportData.expenses.salaries.total_paid.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </Card>

        <!-- Employee Payments -->
        <Card v-if="reportData.expenses.employee_payments.data.length">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
              Other Employee Payments
              <span class="text-sm font-normal text-gray-500">({{ reportData.expenses.employee_payments.count }})</span>
            </h3>
            <span class="text-lg font-bold text-red-600">₹{{ reportData.expenses.employee_payments.total_paid.toLocaleString() }}</span>
          </div>

          <div class="overflow-x-auto">
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
        </Card>
      </div>

      <!-- PENDING AMOUNTS SECTION -->
      <div v-if="reportData.pending && (reportData.pending.receivables.bookings.data.length || reportData.pending.payables.purchases.data.length || reportData.pending.payables.expenses.data.length || reportData.pending.payables.salaries.data.length)" class="mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
          <ClockIcon class="w-6 h-6 text-amber-600" />
          Pending Amounts
        </h2>

        <!-- Pending Receivables (Bookings) -->
        <Card v-if="reportData.pending.receivables.bookings.data.length" class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <ArrowTrendingUpIcon class="w-5 h-5 text-red-600" />
              Pending Receivables (Bookings)
              <span class="text-sm font-normal text-gray-500">({{ reportData.pending.receivables.bookings.count }})</span>
            </h3>
            <span class="text-lg font-bold text-red-600">{{ reportData.pending.receivables.total_formatted }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Booking #</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Date</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Status</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Paid</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Pending</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="booking in reportData.pending.receivables.bookings.data" :key="booking.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ booking.booking_number }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ booking.booking_date }}</td>
                  <td class="px-3 py-2">
                    <span
                      class="px-2 py-0.5 text-xs rounded-full capitalize"
                      :class="{
                        'bg-red-100 text-red-700': booking.payment_status === 'pending',
                        'bg-amber-100 text-amber-700': booking.payment_status === 'partial',
                        'bg-green-100 text-green-700': booking.payment_status === 'paid'
                      }"
                    >
                      {{ booking.payment_status }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ booking.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="booking.paid_amount > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ booking.paid_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="booking.balance_amount > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ booking.balance_amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="3" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-semibold text-gray-700">₹{{ reportData.pending.receivables.bookings.data.reduce((sum, b) => sum + b.total_amount, 0).toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.pending.receivables.bookings.data.reduce((sum, b) => sum + b.paid_amount, 0) > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ reportData.pending.receivables.bookings.data.reduce((sum, b) => sum + b.paid_amount, 0).toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.pending.receivables.total > 0 ? 'text-red-600' : 'text-gray-400'">{{ reportData.pending.receivables.total_formatted }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </Card>

        <!-- Pending Payables Header -->
        <div v-if="reportData.pending.payables.purchases.data.length || reportData.pending.payables.expenses.data.length || reportData.pending.payables.salaries.data.length" class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <ExclamationTriangleIcon class="w-5 h-5 text-red-600" />
            Pending Payables
          </h3>
          <span class="text-lg font-bold text-red-600">{{ reportData.pending.payables.total_formatted }}</span>
        </div>

        <!-- Pending Purchases -->
        <Card v-if="reportData.pending.payables.purchases.data.length" class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">
              Pending Purchase Payments
              <span class="text-sm font-normal text-gray-500">({{ reportData.pending.payables.purchases.count }})</span>
            </h3>
            <span class="font-bold text-red-600">₹{{ reportData.pending.payables.purchases.total.toLocaleString() }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Purchase #</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Date</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Vendor</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Status</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Paid</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Pending</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="purchase in reportData.pending.payables.purchases.data" :key="purchase.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ purchase.purchase_number }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ purchase.purchase_date }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ purchase.vendor_name }}</td>
                  <td class="px-3 py-2">
                    <span
                      class="px-2 py-0.5 text-xs rounded-full capitalize"
                      :class="{
                        'bg-red-100 text-red-700': purchase.payment_status === 'pending',
                        'bg-amber-100 text-amber-700': purchase.payment_status === 'partial',
                        'bg-green-100 text-green-700': purchase.payment_status === 'paid'
                      }"
                    >
                      {{ purchase.payment_status }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ purchase.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="purchase.paid_amount > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ purchase.paid_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="purchase.balance_amount > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ purchase.balance_amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="5" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.pending.payables.purchases.data.reduce((sum, p) => sum + p.paid_amount, 0) > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ reportData.pending.payables.purchases.data.reduce((sum, p) => sum + p.paid_amount, 0).toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.pending.payables.purchases.total > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ reportData.pending.payables.purchases.total.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </Card>

        <!-- Pending Expenses -->
        <Card v-if="reportData.pending.payables.expenses.data.length" class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">
              Pending Expense Payments
              <span class="text-sm font-normal text-gray-500">({{ reportData.pending.payables.expenses.count }})</span>
            </h3>
            <span class="font-bold text-red-600">₹{{ reportData.pending.payables.expenses.total.toLocaleString() }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Expense #</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Date</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Category</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Status</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Paid</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Pending</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="expense in reportData.pending.payables.expenses.data" :key="expense.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ expense.expense_number }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ expense.expense_date }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ expense.category }}</td>
                  <td class="px-3 py-2">
                    <span
                      class="px-2 py-0.5 text-xs rounded-full capitalize"
                      :class="{
                        'bg-red-100 text-red-700': expense.payment_status === 'pending',
                        'bg-amber-100 text-amber-700': expense.payment_status === 'partial',
                        'bg-green-100 text-green-700': expense.payment_status === 'paid'
                      }"
                    >
                      {{ expense.payment_status }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ expense.total_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="expense.paid_amount > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ expense.paid_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="expense.balance_amount > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ expense.balance_amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="5" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.pending.payables.expenses.data.reduce((sum, e) => sum + e.paid_amount, 0) > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ reportData.pending.payables.expenses.data.reduce((sum, e) => sum + e.paid_amount, 0).toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.pending.payables.expenses.total > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ reportData.pending.payables.expenses.total.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </Card>

        <!-- Pending Salaries -->
        <Card v-if="reportData.pending.payables.salaries.data.length">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">
              Pending Salary Payments
              <span class="text-sm font-normal text-gray-500">({{ reportData.pending.payables.salaries.count }})</span>
            </h3>
            <span class="font-bold text-red-600">₹{{ reportData.pending.payables.salaries.total.toLocaleString() }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Employee</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Code</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Month/Year</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-500">Status</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Net Salary</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Paid</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-500">Pending</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="salary in reportData.pending.payables.salaries.data" :key="salary.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-900">{{ salary.employee_name }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ salary.employee_code }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ salary.month_year }}</td>
                  <td class="px-3 py-2">
                    <span
                      class="px-2 py-0.5 text-xs rounded-full capitalize"
                      :class="{
                        'bg-red-100 text-red-700': salary.payment_status === 'pending',
                        'bg-amber-100 text-amber-700': salary.payment_status === 'partial',
                        'bg-green-100 text-green-700': salary.payment_status === 'paid'
                      }"
                    >
                      {{ salary.payment_status }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-right text-gray-900">₹{{ salary.net_salary.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="salary.paid_amount > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ salary.paid_amount.toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-medium" :class="salary.balance_amount > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ salary.balance_amount.toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="5" class="px-3 py-2 font-semibold text-gray-700">Total</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.pending.payables.salaries.data.reduce((sum, s) => sum + s.paid_amount, 0) > 0 ? 'text-green-600' : 'text-gray-400'">₹{{ reportData.pending.payables.salaries.data.reduce((sum, s) => sum + s.paid_amount, 0).toLocaleString() }}</td>
                  <td class="px-3 py-2 text-right font-bold" :class="reportData.pending.payables.salaries.total > 0 ? 'text-red-600' : 'text-gray-400'">₹{{ reportData.pending.payables.salaries.total.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </Card>
      </div>

      <!-- Final Summary (Print Footer) -->
      <Card class="print:mt-8">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Summary - {{ reportData.period.display }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
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

        <!-- Pending Summary -->
        <div v-if="reportData.pending && (reportData.pending.receivables.total > 0 || reportData.pending.payables.total > 0)" class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-200">
          <div class="p-4 bg-red-50 rounded-lg border border-red-200">
            <p class="text-sm text-red-700">Pending Receivables</p>
            <p class="text-xl font-bold text-red-600">{{ reportData.pending.receivables.total_formatted }}</p>
            <div class="text-xs text-red-600 mt-1">
              <div>Bookings: ₹{{ reportData.pending.receivables.bookings.total.toLocaleString() }} ({{ reportData.pending.receivables.bookings.count }})</div>
            </div>
          </div>
          <div class="p-4 bg-red-50 rounded-lg border border-red-200">
            <p class="text-sm text-red-700">Pending Payables</p>
            <p class="text-xl font-bold text-red-600">{{ reportData.pending.payables.total_formatted }}</p>
            <div class="text-xs text-red-600 mt-1">
              <div>Purchases: ₹{{ reportData.pending.payables.purchases.total.toLocaleString() }} ({{ reportData.pending.payables.purchases.count }})</div>
              <div>Expenses: ₹{{ reportData.pending.payables.expenses.total.toLocaleString() }} ({{ reportData.pending.payables.expenses.count }})</div>
              <div>Salaries: ₹{{ reportData.pending.payables.salaries.total.toLocaleString() }} ({{ reportData.pending.payables.salaries.count }})</div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Print Footer - Powered By -->
      <div class="hidden print:block mt-8 pt-4 border-t border-gray-200 text-center text-sm text-gray-500">
        Powered by Prajnja Softech LLP
      </div>
    </template>
  </div>
</template>

<style>
@media print {
  /* Hide browser default headers and footers */
  @page {
    margin: 10mm;
    margin-top: 5mm;
    margin-bottom: 5mm;
  }

  .no-print {
    display: none !important;
  }

  .print-header {
    display: block !important;
  }

  .report-container {
    padding: 0;
    max-width: 100%;
    font-size: 14px;
    color: #000;
    font-weight: 500;
  }

  /* Prevent tfoot from repeating on every page */
  tfoot {
    display: table-row-group;
  }

  /* Charts grid - force 3 columns in print */
  .charts-container {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 8px !important;
    page-break-inside: avoid;
  }

  .chart-card {
    padding: 8px !important;
    break-inside: avoid;
  }

  /* Reduce chart size for print */
  .chart-card .apexcharts-canvas {
    max-height: 200px !important;
  }

  body {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
}
</style>
