<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import {
  ArrowLeftIcon,
  PrinterIcon,
  BanknotesIcon,
  BuildingLibraryIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const uiStore = useUiStore();

const loading = ref(false);
const balanceSheet = ref(null);
const asOfDate = ref(new Date().toISOString().slice(0, 10)); // Today

const formatAmount = (amount) => {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount || 0);
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
};

const fetchBalanceSheet = async () => {
  loading.value = true;
  try {
    const response = await api.get('/ledger/balance-sheet', {
      params: { as_of_date: asOfDate.value },
    });
    balanceSheet.value = response.data.data;
  } catch (error) {
    uiStore.showToast('Failed to fetch balance sheet', 'error');
  } finally {
    loading.value = false;
  }
};

const printSheet = () => {
  window.print();
};

const goBack = () => {
  router.push('/ledger');
};

onMounted(() => {
  fetchBalanceSheet();
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
          <h1 class="text-2xl font-bold text-gray-900">Balance Sheet</h1>
          <p class="text-gray-500">View account balances as of a specific date</p>
        </div>
      </div>
      <Button v-if="balanceSheet" variant="outline" @click="printSheet">
        <PrinterIcon class="w-4 h-4 mr-2" />
        Print
      </Button>
    </div>

    <!-- Date Selector -->
    <Card class="mb-6 print:hidden">
      <div class="flex items-end gap-4">
        <Input
          v-model="asOfDate"
          label="As of Date"
          type="date"
          class="w-48"
        />
        <Button variant="primary" :loading="loading" @click="fetchBalanceSheet">
          Generate
        </Button>
      </div>
    </Card>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <!-- Balance Sheet -->
    <div v-else-if="balanceSheet" class="space-y-6">
      <!-- Header for Print -->
      <div class="hidden print:block mb-6 text-center">
        <h2 class="text-xl font-bold">Balance Sheet</h2>
        <p class="text-gray-600">As of {{ formatDate(balanceSheet.as_of_date) }}</p>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card class="bg-green-50 border-green-200">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-green-100 rounded-full">
              <BanknotesIcon class="w-8 h-8 text-green-600" />
            </div>
            <div>
              <p class="text-sm text-green-600 font-medium">Total Cash</p>
              <p class="text-2xl font-bold text-green-700">{{ formatAmount(balanceSheet.total_cash) }}</p>
            </div>
          </div>
        </Card>

        <Card class="bg-blue-50 border-blue-200">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-blue-100 rounded-full">
              <BuildingLibraryIcon class="w-8 h-8 text-blue-600" />
            </div>
            <div>
              <p class="text-sm text-blue-600 font-medium">Total Bank</p>
              <p class="text-2xl font-bold text-blue-700">{{ formatAmount(balanceSheet.total_bank) }}</p>
            </div>
          </div>
        </Card>

        <Card class="bg-primary-50 border-primary-200">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-primary-100 rounded-full">
              <BanknotesIcon class="w-8 h-8 text-primary-600" />
            </div>
            <div>
              <p class="text-sm text-primary-600 font-medium">Grand Total</p>
              <p class="text-2xl font-bold text-primary-700">{{ formatAmount(balanceSheet.grand_total) }}</p>
            </div>
          </div>
        </Card>
      </div>

      <!-- Cash Account -->
      <Card v-if="balanceSheet.cash?.length">
        <template #header>
          <div class="flex items-center gap-2">
            <BanknotesIcon class="w-5 h-5 text-green-600" />
            <span class="font-semibold">Cash Account</span>
          </div>
        </template>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Opening Balance</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Current Balance</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="account in balanceSheet.cash" :key="account.id">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ account.name }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-600">{{ formatAmount(account.opening_balance) }}</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ formatAmount(account.current_balance) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-green-50">
              <tr>
                <td class="px-4 py-3 text-sm font-semibold text-green-700">Total Cash</td>
                <td class="px-4 py-3 text-sm text-right text-gray-500">-</td>
                <td class="px-4 py-3 text-sm text-right font-bold text-green-700">{{ formatAmount(balanceSheet.total_cash) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </Card>

      <!-- Bank Accounts -->
      <Card v-if="balanceSheet.bank?.length">
        <template #header>
          <div class="flex items-center gap-2">
            <BuildingLibraryIcon class="w-5 h-5 text-blue-600" />
            <span class="font-semibold">Bank Accounts</span>
          </div>
        </template>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Opening Balance</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Current Balance</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="account in balanceSheet.bank" :key="account.id">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                  {{ account.name }}
                  <span v-if="account.is_upi" class="ml-2 px-2 py-0.5 text-xs bg-purple-100 text-purple-700 rounded-full">UPI</span>
                </td>
                <td class="px-4 py-3 text-sm text-right text-gray-600">{{ formatAmount(account.opening_balance) }}</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ formatAmount(account.current_balance) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-blue-50">
              <tr>
                <td class="px-4 py-3 text-sm font-semibold text-blue-700">Total Bank</td>
                <td class="px-4 py-3 text-sm text-right text-gray-500">-</td>
                <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">{{ formatAmount(balanceSheet.total_bank) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </Card>

      <!-- Grand Total -->
      <Card class="bg-primary-50 border-primary-200">
        <div class="flex items-center justify-between">
          <span class="text-lg font-semibold text-primary-700">Grand Total (Cash + Bank)</span>
          <span class="text-2xl font-bold text-primary-700">{{ formatAmount(balanceSheet.grand_total) }}</span>
        </div>
      </Card>
    </div>

    <!-- No Data -->
    <Card v-else>
      <div class="text-center py-12 text-gray-500">
        Select a date to view balance sheet
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
