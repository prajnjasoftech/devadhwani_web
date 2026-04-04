<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import VueApexCharts from 'vue3-apexcharts';
import {
  BuildingLibraryIcon,
  UsersIcon,
  BanknotesIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  ClockIcon,
  CalendarIcon,
  CurrencyRupeeIcon,
  ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();

// State
const loading = ref(true);
const stats = ref(null);
const summary = ref(null);
const charts = ref(null);
const poojas = ref([]);
const todaySchedule = ref([]);
const recentBookings = ref([]);

// Date filter
const currentMonth = new Date().toISOString().slice(0, 7); // YYYY-MM
const selectedMonth = ref(currentMonth);

// Computed date range
const dateRange = computed(() => {
  const [year, month] = selectedMonth.value.split('-');
  const start = new Date(year, month - 1, 1);
  const end = new Date(year, month, 0);
  return {
    start_date: start.toISOString().split('T')[0],
    end_date: end.toISOString().split('T')[0],
  };
});

// Chart options
const trendChartOptions = computed(() => ({
  chart: {
    type: 'area',
    height: 350,
    toolbar: { show: false },
    zoom: { enabled: false },
  },
  colors: ['#10B981', '#EF4444'],
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.1,
    },
  },
  xaxis: {
    categories: charts.value?.daily_trend?.dates?.map(d => {
      const date = new Date(d);
      return date.getDate();
    }) || [],
    labels: { style: { fontSize: '11px' } },
  },
  yaxis: {
    labels: {
      formatter: (val) => '₹' + (val >= 1000 ? (val / 1000).toFixed(1) + 'K' : val),
    },
  },
  tooltip: {
    y: { formatter: (val) => '₹' + val.toLocaleString() },
  },
  legend: { position: 'top' },
}));

const trendChartSeries = computed(() => [
  { name: 'Income', data: charts.value?.daily_trend?.income || [] },
  { name: 'Expense', data: charts.value?.daily_trend?.expense || [] },
]);

const incomeSourceChartOptions = computed(() => ({
  chart: { type: 'donut', height: 280 },
  labels: ['Bookings', 'Donations', 'Other'],
  colors: ['#6366F1', '#8B5CF6', '#A78BFA'],
  legend: { position: 'bottom' },
  plotOptions: {
    pie: {
      donut: { size: '60%' },
    },
  },
  tooltip: {
    y: { formatter: (val) => '₹' + val.toLocaleString() },
  },
}));

const incomeSourceSeries = computed(() => [
  summary.value?.income_by_source?.booking || 0,
  summary.value?.income_by_source?.donation || 0,
  summary.value?.income_by_source?.other || 0,
]);

const expenseSourceChartOptions = computed(() => ({
  chart: { type: 'donut', height: 280 },
  labels: ['Purchases', 'Expenses', 'Salaries', 'Other Payments'],
  colors: ['#F59E0B', '#EF4444', '#EC4899', '#F97316'],
  legend: { position: 'bottom' },
  plotOptions: {
    pie: {
      donut: { size: '60%' },
    },
  },
  tooltip: {
    y: { formatter: (val) => '₹' + val.toLocaleString() },
  },
}));

const expenseSourceSeries = computed(() => [
  summary.value?.expense_by_source?.purchase || 0,
  summary.value?.expense_by_source?.expense || 0,
  summary.value?.expense_by_source?.salary || 0,
  (summary.value?.expense_by_source?.employee_payment || 0) + (summary.value?.expense_by_source?.other || 0),
]);

const accountChartOptions = computed(() => ({
  chart: { type: 'bar', height: 280, toolbar: { show: false } },
  plotOptions: {
    bar: { horizontal: true, borderRadius: 4 },
  },
  colors: ['#6366F1'],
  xaxis: {
    categories: charts.value?.income_by_account?.map(a => a.name) || [],
    labels: {
      formatter: (val) => '₹' + (val >= 1000 ? (val / 1000).toFixed(1) + 'K' : val),
    },
  },
  tooltip: {
    y: { formatter: (val) => '₹' + val.toLocaleString() },
  },
}));

const accountChartSeries = computed(() => [{
  name: 'Income',
  data: charts.value?.income_by_account?.map(a => a.total) || [],
}]);

// Fetch data
const fetchData = async () => {
  loading.value = true;
  try {
    if (authStore.isPlatformAdmin) {
      const response = await api.get('/dashboard/stats');
      stats.value = response.data.data;
    } else {
      const params = dateRange.value;
      const [summaryRes, chartsRes, poojasRes, todayRes, recentRes] = await Promise.all([
        api.get('/dashboard/summary', { params }),
        api.get('/dashboard/charts', { params }),
        api.get('/dashboard/poojas', { params }),
        api.get('/dashboard/today'),
        api.get('/dashboard/recent-bookings'),
      ]);
      summary.value = summaryRes.data.data;
      charts.value = chartsRes.data.data;
      poojas.value = poojasRes.data.data;
      todaySchedule.value = todayRes.data.data;
      recentBookings.value = recentRes.data.data;
    }
  } catch (error) {
    console.error('Failed to fetch dashboard data:', error);
  } finally {
    loading.value = false;
  }
};

// Watch for month change
watch(selectedMonth, fetchData);

onMounted(fetchData);
</script>

<template>
  <div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-500">Welcome back, {{ authStore.user?.name }}</p>
      </div>

      <!-- Month Filter (Temple Users Only) -->
      <div v-if="!authStore.isPlatformAdmin" class="flex items-center gap-2">
        <CalendarIcon class="w-5 h-5 text-gray-400" />
        <input
          v-model="selectedMonth"
          type="month"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        />
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <!-- Platform Admin Dashboard -->
    <template v-else-if="authStore.isPlatformAdmin">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card>
          <div class="flex items-center">
            <div class="p-3 bg-primary-100 rounded-lg">
              <BuildingLibraryIcon class="w-6 h-6 text-primary-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Total Temples</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats?.temples?.total || 0 }}</p>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg">
              <BuildingLibraryIcon class="w-6 h-6 text-green-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Active Temples</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats?.temples?.active || 0 }}</p>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center">
            <div class="p-3 bg-gray-100 rounded-lg">
              <BuildingLibraryIcon class="w-6 h-6 text-gray-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Inactive Temples</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats?.temples?.inactive || 0 }}</p>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center">
            <div class="p-3 bg-red-100 rounded-lg">
              <BuildingLibraryIcon class="w-6 h-6 text-red-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Suspended Temples</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats?.temples?.suspended || 0 }}</p>
            </div>
          </div>
        </Card>
      </div>
    </template>

    <!-- Temple User Dashboard -->
    <template v-else>
      <!-- Financial Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Total Income</p>
              <p class="text-2xl font-bold text-green-600">{{ summary?.financial?.total_income_formatted || '₹0' }}</p>
            </div>
            <div class="p-3 bg-green-100 rounded-lg">
              <ArrowTrendingUpIcon class="w-6 h-6 text-green-600" />
            </div>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
            Bookings: ₹{{ (summary?.income_by_source?.booking || 0).toLocaleString() }} |
            Donations: ₹{{ (summary?.income_by_source?.donation || 0).toLocaleString() }}
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Total Expense</p>
              <p class="text-2xl font-bold text-red-600">{{ summary?.financial?.total_expense_formatted || '₹0' }}</p>
            </div>
            <div class="p-3 bg-red-100 rounded-lg">
              <ArrowTrendingDownIcon class="w-6 h-6 text-red-600" />
            </div>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
            Purchases: ₹{{ (summary?.expense_by_source?.purchase || 0).toLocaleString() }} |
            Salaries: ₹{{ (summary?.expense_by_source?.salary || 0).toLocaleString() }}
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Net Balance</p>
              <p class="text-2xl font-bold" :class="(summary?.financial?.net_balance || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ summary?.financial?.net_balance_formatted || '₹0' }}
              </p>
            </div>
            <div class="p-3 bg-blue-100 rounded-lg">
              <BanknotesIcon class="w-6 h-6 text-blue-600" />
            </div>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
            Income - Expense for {{ summary?.period?.month_name }}
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500">Pending Receivables</p>
              <p class="text-2xl font-bold text-orange-600">{{ summary?.financial?.pending_receivables_formatted || '₹0' }}</p>
            </div>
            <div class="p-3 bg-orange-100 rounded-lg">
              <ExclamationCircleIcon class="w-6 h-6 text-orange-600" />
            </div>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
            Outstanding booking balances
          </div>
        </Card>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <Card>
          <div class="flex items-center gap-4">
            <div class="p-3 bg-indigo-100 rounded-lg">
              <CurrencyRupeeIcon class="w-6 h-6 text-indigo-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Bookings This Month</p>
              <p class="text-xl font-bold text-gray-900">{{ summary?.counts?.bookings || 0 }}</p>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center gap-4">
            <div class="p-3 bg-purple-100 rounded-lg">
              <BanknotesIcon class="w-6 h-6 text-purple-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Donations This Month</p>
              <p class="text-xl font-bold text-gray-900">{{ summary?.counts?.donations || 0 }}</p>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center gap-4">
            <div class="p-3 bg-amber-100 rounded-lg">
              <ClockIcon class="w-6 h-6 text-amber-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Today's Pending Poojas</p>
              <p class="text-xl font-bold text-gray-900">{{ summary?.counts?.today_poojas || 0 }}</p>
            </div>
          </div>
        </Card>
      </div>

      <!-- Charts Row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Income/Expense Trend -->
        <Card title="Income & Expense Trend">
          <VueApexCharts
            v-if="charts?.daily_trend?.dates?.length"
            type="area"
            height="300"
            :options="trendChartOptions"
            :series="trendChartSeries"
          />
          <div v-else class="flex items-center justify-center h-64 text-gray-400">
            No data for this period
          </div>
        </Card>

        <!-- Income by Account -->
        <Card title="Income by Account">
          <VueApexCharts
            v-if="charts?.income_by_account?.length"
            type="bar"
            height="300"
            :options="accountChartOptions"
            :series="accountChartSeries"
          />
          <div v-else class="flex items-center justify-center h-64 text-gray-400">
            No data for this period
          </div>
        </Card>
      </div>

      <!-- Pie Charts Row -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Income Sources -->
        <Card title="Income Sources">
          <VueApexCharts
            v-if="incomeSourceSeries.some(v => v > 0)"
            type="donut"
            height="280"
            :options="incomeSourceChartOptions"
            :series="incomeSourceSeries"
          />
          <div v-else class="flex items-center justify-center h-64 text-gray-400">
            No income data for this period
          </div>
        </Card>

        <!-- Expense Sources -->
        <Card title="Expense Sources">
          <VueApexCharts
            v-if="expenseSourceSeries.some(v => v > 0)"
            type="donut"
            height="280"
            :options="expenseSourceChartOptions"
            :series="expenseSourceSeries"
          />
          <div v-else class="flex items-center justify-center h-64 text-gray-400">
            No expense data for this period
          </div>
        </Card>
      </div>

      <!-- Pooja Performance Table -->
      <Card title="Pooja Performance" class="mb-6">
        <div class="overflow-x-auto">
          <table v-if="poojas.length" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pooja Name</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bookings</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Completed</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Income</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pending</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="pooja in poojas" :key="pooja.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ pooja.name }}</td>
                <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ pooja.total_bookings }}</td>
                <td class="px-4 py-3 text-sm text-right">
                  <span class="text-green-600">{{ pooja.completed_count }}</span>
                  <span class="text-gray-400">/{{ pooja.total_schedules }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-green-600 font-medium text-right">{{ pooja.total_income_formatted }}</td>
                <td class="px-4 py-3 text-sm text-right">
                  <span :class="pooja.pending_amount > 0 ? 'text-orange-600' : 'text-gray-400'">
                    {{ pooja.pending_amount_formatted }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="text-center py-8 text-gray-400">
            No pooja bookings for this period
          </div>
        </div>
      </Card>

      <!-- Bottom Row: Today's Schedule & Recent Bookings -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Today's Schedule -->
        <Card title="Today's Schedule">
          <div v-if="todaySchedule.length" class="space-y-3">
            <div
              v-for="(item, index) in todaySchedule"
              :key="index"
              class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
            >
              <div>
                <p class="font-medium text-gray-900">{{ item.pooja_name }}</p>
                <p class="text-xs text-gray-500">{{ item.deity_name }}</p>
              </div>
              <div class="text-right">
                <span class="inline-flex items-center gap-1">
                  <span class="text-green-600 font-medium">{{ item.completed_count }}</span>
                  <span class="text-gray-400">/</span>
                  <span class="text-gray-600">{{ item.total_count }}</span>
                </span>
                <p class="text-xs text-orange-600" v-if="item.pending_count > 0">
                  {{ item.pending_count }} pending
                </p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-gray-400">
            No poojas scheduled for today
          </div>
          <div class="mt-4 pt-4 border-t">
            <button
              @click="router.push('/daily-poojas')"
              class="text-sm text-primary-600 hover:text-primary-700 font-medium"
            >
              View All Daily Poojas →
            </button>
          </div>
        </Card>

        <!-- Recent Bookings -->
        <Card title="Recent Bookings">
          <div v-if="recentBookings.length" class="space-y-3">
            <div
              v-for="booking in recentBookings"
              :key="booking.id"
              @click="router.push(`/bookings/${booking.id}`)"
              class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100"
            >
              <div>
                <p class="font-medium text-gray-900">{{ booking.booking_number }}</p>
                <p class="text-xs text-gray-500">{{ booking.contact_name || 'No contact' }} • {{ booking.booking_date }}</p>
              </div>
              <div class="text-right">
                <p class="font-medium text-gray-900">{{ booking.total_amount_formatted }}</p>
                <span
                  class="text-xs px-2 py-0.5 rounded-full"
                  :class="{
                    'bg-green-100 text-green-700': booking.payment_status === 'paid',
                    'bg-orange-100 text-orange-700': booking.payment_status === 'partial',
                    'bg-red-100 text-red-700': booking.payment_status === 'pending',
                  }"
                >
                  {{ booking.payment_status }}
                </span>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-gray-400">
            No recent bookings
          </div>
          <div class="mt-4 pt-4 border-t">
            <button
              @click="router.push('/bookings')"
              class="text-sm text-primary-600 hover:text-primary-700 font-medium"
            >
              View All Bookings →
            </button>
          </div>
        </Card>
      </div>
    </template>
  </div>
</template>
