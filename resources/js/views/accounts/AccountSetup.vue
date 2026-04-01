<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import { PlusIcon, TrashIcon, CheckCircleIcon, BanknotesIcon, BuildingLibraryIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const uiStore = useUiStore();

const loading = ref(true);
const saving = ref(false);
const setupCompleted = ref(false);
const showSuccessModal = ref(false);

// Form data
const cashOpeningBalance = ref(0);
const bankAccounts = ref([
  {
    account_name: '',
    bank_name: '',
    account_number: '',
    ifsc_code: '',
    branch: '',
    opening_balance: 0,
    is_upi_account: false,
  },
]);

const errors = ref({});

const totalOpeningBalance = computed(() => {
  const cashBalance = parseFloat(cashOpeningBalance.value) || 0;
  const bankBalance = bankAccounts.value.reduce((sum, acc) => {
    return sum + (parseFloat(acc.opening_balance) || 0);
  }, 0);
  return cashBalance + bankBalance;
});

const formatAmount = (amount) => {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};

const addBankAccount = () => {
  bankAccounts.value.push({
    account_name: '',
    bank_name: '',
    account_number: '',
    ifsc_code: '',
    branch: '',
    opening_balance: 0,
    is_upi_account: false,
  });
};

const removeBankAccount = (index) => {
  if (bankAccounts.value.length > 1) {
    bankAccounts.value.splice(index, 1);
  }
};

const setAsUpiAccount = (index) => {
  bankAccounts.value.forEach((acc, i) => {
    acc.is_upi_account = i === index;
  });
};

const checkSetup = async () => {
  try {
    const response = await api.get('/accounts/check-setup');
    setupCompleted.value = response.data.data.setup_completed;
    if (setupCompleted.value) {
      // Redirect to accounts list if setup is already completed
      router.push('/accounts');
    }
  } catch (error) {
    console.error('Failed to check setup:', error);
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};

  // Validate at least one bank account has required fields
  const invalidBank = bankAccounts.value.findIndex(
    (acc) => !acc.account_name || !acc.bank_name || !acc.account_number
  );
  if (invalidBank !== -1) {
    uiStore.showToast(`Please fill all required fields for Bank Account ${invalidBank + 1}`, 'error');
    return;
  }

  saving.value = true;
  try {
    await api.post('/accounts/setup', {
      cash_opening_balance: cashOpeningBalance.value,
      bank_accounts: bankAccounts.value,
    });
    showSuccessModal.value = true;
  } catch (error) {
    if (error.response?.status === 422) {
      const errorData = error.response.data;
      if (errorData.message) {
        uiStore.showToast(errorData.message, 'error');
      }
      errors.value = errorData.errors || {};
    } else {
      uiStore.showToast('Failed to setup accounts', 'error');
    }
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  checkSetup();
});
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Accounts Setup</h1>
      <p class="text-gray-500">Set up your temple's opening balances for Cash and Bank accounts</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Important Notice -->
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-yellow-800">One-time Setup</h3>
            <p class="mt-1 text-sm text-yellow-700">
              This is a one-time setup. Once submitted, the opening balances cannot be changed.
              Please verify all details carefully before submitting.
            </p>
          </div>
        </div>
      </div>

      <!-- Cash Account -->
      <Card>
        <template #header>
          <div class="flex items-center gap-2">
            <BanknotesIcon class="w-5 h-5 text-green-600" />
            <span class="font-semibold">Cash Account</span>
          </div>
        </template>
        <div class="max-w-md">
          <Input
            v-model.number="cashOpeningBalance"
            label="Opening Balance"
            type="number"
            min="0"
            step="0.01"
            required
            :error="errors.cash_opening_balance?.[0]"
          />
          <p class="mt-1 text-sm text-gray-500">Enter the current cash balance in hand</p>
        </div>
      </Card>

      <!-- Bank Accounts -->
      <Card>
        <template #header>
          <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-2">
              <BuildingLibraryIcon class="w-5 h-5 text-blue-600" />
              <span class="font-semibold">Bank Accounts</span>
            </div>
            <Button type="button" variant="outline" size="sm" @click="addBankAccount">
              <PlusIcon class="w-4 h-4 mr-1" />
              Add Bank Account
            </Button>
          </div>
        </template>

        <div class="space-y-6">
          <div
            v-for="(account, index) in bankAccounts"
            :key="index"
            class="p-4 border border-gray-200 rounded-lg"
          >
            <div class="flex items-center justify-between mb-4">
              <h4 class="font-medium text-gray-900">Bank Account {{ index + 1 }}</h4>
              <div class="flex items-center gap-2">
                <label class="flex items-center gap-2 text-sm">
                  <input
                    type="radio"
                    :checked="account.is_upi_account"
                    @change="setAsUpiAccount(index)"
                    class="text-primary-600 focus:ring-primary-500"
                  />
                  <span :class="account.is_upi_account ? 'text-primary-600 font-medium' : 'text-gray-600'">
                    UPI Account
                  </span>
                </label>
                <button
                  v-if="bankAccounts.length > 1"
                  type="button"
                  @click="removeBankAccount(index)"
                  class="p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <Input
                v-model="account.account_name"
                label="Account Name *"
                placeholder="e.g., Temple Savings"
                required
              />
              <Input
                v-model="account.bank_name"
                label="Bank Name *"
                placeholder="e.g., State Bank of India"
                required
              />
              <Input
                v-model="account.account_number"
                label="Account Number *"
                placeholder="Enter account number"
                required
              />
              <Input
                v-model="account.ifsc_code"
                label="IFSC Code"
                placeholder="e.g., SBIN0001234"
              />
              <Input
                v-model="account.branch"
                label="Branch"
                placeholder="e.g., Main Branch"
              />
              <Input
                v-model.number="account.opening_balance"
                label="Opening Balance *"
                type="number"
                min="0"
                step="0.01"
                required
              />
            </div>
          </div>
        </div>
      </Card>

      <!-- Summary -->
      <Card>
        <template #header>
          <span class="font-semibold">Opening Balance Summary</span>
        </template>
        <div class="space-y-3">
          <div class="flex justify-between items-center py-2 border-b">
            <span class="text-gray-600">Cash Balance</span>
            <span class="font-medium">{{ formatAmount(cashOpeningBalance || 0) }}</span>
          </div>
          <div
            v-for="(account, index) in bankAccounts"
            :key="index"
            class="flex justify-between items-center py-2 border-b"
          >
            <span class="text-gray-600">
              {{ account.account_name || `Bank Account ${index + 1}` }}
              <span v-if="account.is_upi_account" class="text-xs text-primary-600 ml-1">(UPI)</span>
            </span>
            <span class="font-medium">{{ formatAmount(account.opening_balance || 0) }}</span>
          </div>
          <div class="flex justify-between items-center py-3 border-t-2 border-gray-300">
            <span class="font-semibold text-gray-900">Total Opening Balance</span>
            <span class="text-xl font-bold text-primary-600">{{ formatAmount(totalOpeningBalance) }}</span>
          </div>
        </div>
      </Card>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-4">
        <Button type="button" variant="outline" @click="router.push('/')">
          Cancel
        </Button>
        <Button type="submit" variant="primary" :loading="saving">
          Complete Setup
        </Button>
      </div>
    </form>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <div class="text-center">
            <div class="mx-auto flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
              <CheckCircleIcon class="w-10 h-10 text-green-600" />
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Accounts Setup Complete!</h3>
            <p class="text-gray-600 mb-4">
              Your opening balances have been recorded successfully.
            </p>
            <p class="text-sm text-gray-500 mb-6">
              Total Opening Balance: <span class="font-bold text-primary-600">{{ formatAmount(totalOpeningBalance) }}</span>
            </p>
            <Button variant="primary" @click="router.push('/accounts')" class="w-full">
              View Accounts
            </Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
