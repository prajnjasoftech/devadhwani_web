<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import Input from '@/components/ui/Input.vue';
import {
  BanknotesIcon,
  BuildingLibraryIcon,
  PencilIcon,
  CheckCircleIcon,
  NoSymbolIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  ArrowsRightLeftIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const loading = ref(true);
const accounts = ref([]);
const setupCompleted = ref(false);

// Edit modal
const showEditModal = ref(false);
const editingAccount = ref(null);
const editForm = ref({
  account_name: '',
  bank_name: '',
  account_number: '',
  ifsc_code: '',
  branch: '',
  is_upi_account: false,
  is_card_account: false,
});
const saving = ref(false);

// Add new account modal
const showAddModal = ref(false);
const addForm = ref({
  account_name: '',
  bank_name: '',
  account_number: '',
  ifsc_code: '',
  branch: '',
  opening_balance: 0,
  is_upi_account: false,
  is_card_account: false,
});
const adding = ref(false);

// Transfer modal
const showTransferModal = ref(false);
const transferForm = ref({
  from_account_id: '',
  to_account_id: '',
  amount: 0,
  narration: '',
  entry_date: new Date().toISOString().split('T')[0],
});
const transferring = ref(false);
const transferType = ref('deposit'); // 'deposit' or 'withdraw'

// Computed
const cashAccount = computed(() => accounts.value.find((a) => a.account_type === 'cash'));
const bankAccounts = computed(() => accounts.value.filter((a) => a.account_type === 'bank'));
const activeBankAccounts = computed(() => bankAccounts.value.filter((a) => a.is_active));

const totalCashBalance = computed(() => parseFloat(cashAccount.value?.current_balance) || 0);
const totalBankBalance = computed(() =>
  bankAccounts.value.reduce((sum, acc) => sum + parseFloat(acc.current_balance || 0), 0)
);
const totalBalance = computed(() => totalCashBalance.value + totalBankBalance.value);

const formatAmount = (amount) => {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};

const fetchAccounts = async () => {
  loading.value = true;
  try {
    const response = await api.get('/accounts');
    accounts.value = response.data.data.accounts;
    setupCompleted.value = response.data.data.setup_completed;

    // Redirect to setup if not completed
    if (!setupCompleted.value) {
      router.push('/accounts/setup');
    }
  } catch (error) {
    uiStore.showToast('Failed to fetch accounts', 'error');
  } finally {
    loading.value = false;
  }
};

const openEditModal = (account) => {
  editingAccount.value = account;
  editForm.value = {
    account_name: account.account_name,
    bank_name: account.bank_name || '',
    account_number: account.account_number || '',
    ifsc_code: account.ifsc_code || '',
    branch: account.branch || '',
    is_upi_account: account.is_upi_account || false,
    is_card_account: account.is_card_account || false,
  };
  showEditModal.value = true;
};

const saveAccount = async () => {
  saving.value = true;
  try {
    await api.put(`/accounts/${editingAccount.value.id}`, editForm.value);
    uiStore.showToast('Account updated successfully', 'success');
    showEditModal.value = false;
    fetchAccounts();
  } catch (error) {
    uiStore.showToast('Failed to update account', 'error');
  } finally {
    saving.value = false;
  }
};

const toggleAccountStatus = async (account) => {
  try {
    await api.put(`/accounts/${account.id}`, {
      is_active: !account.is_active,
    });
    account.is_active = !account.is_active;
    uiStore.showToast(
      `Account ${account.is_active ? 'activated' : 'deactivated'} successfully`,
      'success'
    );
  } catch (error) {
    uiStore.showToast('Failed to update account status', 'error');
  }
};

const openAddModal = () => {
  addForm.value = {
    account_name: '',
    bank_name: '',
    account_number: '',
    ifsc_code: '',
    branch: '',
    opening_balance: 0,
    is_upi_account: false,
    is_card_account: false,
  };
  showAddModal.value = true;
};

const saveNewAccount = async () => {
  adding.value = true;
  try {
    await api.post('/accounts', addForm.value);
    uiStore.showToast('Bank account added successfully', 'success');
    showAddModal.value = false;
    fetchAccounts();
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Failed to add bank account', 'error');
  } finally {
    adding.value = false;
  }
};

const openTransferModal = (type) => {
  transferType.value = type;
  const cash = cashAccount.value;
  const activeBank = activeBankAccounts.value.length === 1 ? activeBankAccounts.value[0] : null;

  if (type === 'deposit') {
    // Cash to Bank
    transferForm.value = {
      from_account_id: cash?.id || '',
      to_account_id: activeBank?.id || '',
      amount: 0,
      narration: 'Cash deposit to bank',
      entry_date: new Date().toISOString().split('T')[0],
    };
  } else {
    // Bank to Cash
    transferForm.value = {
      from_account_id: activeBank?.id || '',
      to_account_id: cash?.id || '',
      amount: 0,
      narration: 'Cash withdrawal from bank',
      entry_date: new Date().toISOString().split('T')[0],
    };
  }
  showTransferModal.value = true;
};

const getFromAccountBalance = computed(() => {
  const acc = accounts.value.find(a => a.id === transferForm.value.from_account_id);
  return acc ? parseFloat(acc.current_balance) : 0;
});

const submitTransfer = async () => {
  if (transferForm.value.amount <= 0) {
    uiStore.showToast('Please enter a valid amount', 'error');
    return;
  }
  if (transferForm.value.amount > getFromAccountBalance.value) {
    uiStore.showToast('Insufficient balance in source account', 'error');
    return;
  }

  transferring.value = true;
  try {
    await api.post('/ledger/transfer', transferForm.value);
    uiStore.showToast('Transfer completed successfully', 'success');
    showTransferModal.value = false;
    fetchAccounts();
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Failed to complete transfer', 'error');
  } finally {
    transferring.value = false;
  }
};

onMounted(() => {
  fetchAccounts();
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Accounts</h1>
        <p class="text-gray-500">View and manage your temple's accounts</p>
      </div>
      <div class="flex items-center gap-3">
        <router-link to="/ledger" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
          View Ledger &rarr;
        </router-link>
        <Button variant="outline" @click="openTransferModal('deposit')">
          <ArrowsRightLeftIcon class="w-4 h-4 mr-2" />
          Cash to Bank
        </Button>
        <Button variant="outline" @click="openTransferModal('withdraw')">
          <ArrowsRightLeftIcon class="w-4 h-4 mr-2" />
          Bank to Cash
        </Button>
        <Button variant="primary" @click="openAddModal">
          <PlusIcon class="w-4 h-4 mr-2" />
          Add Bank Account
        </Button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <div v-else class="space-y-6">
      <!-- Balance Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card class="bg-green-50 border-green-200">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-green-100 rounded-full">
              <BanknotesIcon class="w-8 h-8 text-green-600" />
            </div>
            <div>
              <p class="text-sm text-green-600 font-medium">Cash Balance</p>
              <p class="text-2xl font-bold text-green-700">{{ formatAmount(totalCashBalance) }}</p>
            </div>
          </div>
        </Card>

        <Card class="bg-blue-50 border-blue-200">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-blue-100 rounded-full">
              <BuildingLibraryIcon class="w-8 h-8 text-blue-600" />
            </div>
            <div>
              <p class="text-sm text-blue-600 font-medium">Bank Balance</p>
              <p class="text-2xl font-bold text-blue-700">{{ formatAmount(totalBankBalance) }}</p>
            </div>
          </div>
        </Card>

        <Card class="bg-primary-50 border-primary-200">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-primary-100 rounded-full">
              <BanknotesIcon class="w-8 h-8 text-primary-600" />
            </div>
            <div>
              <p class="text-sm text-primary-600 font-medium">Total Balance</p>
              <p class="text-2xl font-bold text-primary-700">{{ formatAmount(totalBalance) }}</p>
            </div>
          </div>
        </Card>
      </div>

      <!-- Cash Account -->
      <Card v-if="cashAccount">
        <template #header>
          <div class="flex items-center gap-2">
            <BanknotesIcon class="w-5 h-5 text-green-600" />
            <span class="font-semibold">Cash Account</span>
          </div>
        </template>

        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-600">Opening Balance: {{ formatAmount(cashAccount.opening_balance) }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-2">
              Current Balance: {{ formatAmount(cashAccount.current_balance) }}
            </p>
          </div>
          <span
            :class="[
              'px-3 py-1 text-sm font-medium rounded-full',
              cashAccount.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800',
            ]"
          >
            {{ cashAccount.is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>
      </Card>

      <!-- Bank Accounts -->
      <Card>
        <template #header>
          <div class="flex items-center gap-2">
            <BuildingLibraryIcon class="w-5 h-5 text-blue-600" />
            <span class="font-semibold">Bank Accounts</span>
          </div>
        </template>

        <div class="space-y-4">
          <div
            v-for="account in bankAccounts"
            :key="account.id"
            :class="[
              'p-4 border rounded-lg',
              account.is_active ? 'border-gray-200' : 'border-gray-100 bg-gray-50 opacity-60',
            ]"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <h4 class="font-semibold text-gray-900">{{ account.account_name }}</h4>
                  <span
                    v-if="account.is_upi_account"
                    class="px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-700 rounded-full"
                  >
                    UPI
                  </span>
                  <span
                    v-if="account.is_card_account"
                    class="px-2 py-0.5 text-xs font-medium bg-orange-100 text-orange-700 rounded-full"
                  >
                    Card
                  </span>
                  <span
                    :class="[
                      'px-2 py-0.5 text-xs font-medium rounded-full',
                      account.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800',
                    ]"
                  >
                    {{ account.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>
                <p class="text-sm text-gray-500 mt-1">
                  {{ account.bank_name }} | A/C: {{ account.account_number }}
                </p>
                <p v-if="account.ifsc_code || account.branch" class="text-xs text-gray-400 mt-1">
                  <span v-if="account.ifsc_code">IFSC: {{ account.ifsc_code }}</span>
                  <span v-if="account.ifsc_code && account.branch"> | </span>
                  <span v-if="account.branch">Branch: {{ account.branch }}</span>
                </p>
                <div class="mt-3 flex items-center gap-6">
                  <div>
                    <p class="text-xs text-gray-500">Opening Balance</p>
                    <p class="font-medium text-gray-700">{{ formatAmount(account.opening_balance) }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500">Current Balance</p>
                    <p class="font-bold text-lg text-gray-900">{{ formatAmount(account.current_balance) }}</p>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-1">
                <button
                  @click="openEditModal(account)"
                  class="p-2 text-gray-500 hover:text-primary-600 hover:bg-gray-100 rounded-lg"
                  title="Edit"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click="toggleAccountStatus(account)"
                  :class="[
                    'p-2 rounded-lg',
                    account.is_active
                      ? 'text-gray-500 hover:text-red-600 hover:bg-red-50'
                      : 'text-gray-500 hover:text-green-600 hover:bg-green-50',
                  ]"
                  :title="account.is_active ? 'Deactivate' : 'Activate'"
                >
                  <NoSymbolIcon v-if="account.is_active" class="w-4 h-4" />
                  <CheckCircleIcon v-else class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="bankAccounts.length === 0" class="text-center py-8 text-gray-500">
            No bank accounts found
          </div>
        </div>
      </Card>

      <!-- Note -->
      <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <div class="flex">
          <ExclamationTriangleIcon class="w-5 h-5 text-gray-400 flex-shrink-0" />
          <div class="ml-3 text-sm text-gray-600">
            <p>
              <strong>Note:</strong> Opening balances cannot be modified after initial setup.
              Current balances will be automatically updated as transactions are recorded.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <Modal :show="showEditModal" title="Edit Bank Account" @close="showEditModal = false">
      <div v-if="editingAccount" class="space-y-4">
        <Input
          v-model="editForm.account_name"
          label="Account Name"
          required
        />
        <Input
          v-model="editForm.bank_name"
          label="Bank Name"
          required
        />
        <Input
          v-model="editForm.account_number"
          label="Account Number"
          required
        />
        <Input
          v-model="editForm.ifsc_code"
          label="IFSC Code"
        />
        <Input
          v-model="editForm.branch"
          label="Branch"
        />
        <div class="space-y-3">
          <label class="flex items-center gap-2">
            <input
              v-model="editForm.is_upi_account"
              type="checkbox"
              class="rounded text-primary-600 focus:ring-primary-500"
            />
            <span class="text-sm text-gray-700">This is the UPI account</span>
          </label>
          <label class="flex items-center gap-2">
            <input
              v-model="editForm.is_card_account"
              type="checkbox"
              class="rounded text-primary-600 focus:ring-primary-500"
            />
            <span class="text-sm text-gray-700">This is the Card/POS account</span>
          </label>
          <p class="text-xs text-gray-500">
            Note: Only one bank account can be marked for each payment type
          </p>
        </div>

        <div class="flex justify-end gap-3 pt-4">
          <Button variant="outline" @click="showEditModal = false">Cancel</Button>
          <Button variant="primary" :loading="saving" @click="saveAccount">Save Changes</Button>
        </div>
      </div>
    </Modal>

    <!-- Add Bank Account Modal -->
    <Modal :show="showAddModal" title="Add New Bank Account" @close="showAddModal = false">
      <div class="space-y-4">
        <Input
          v-model="addForm.account_name"
          label="Account Name"
          placeholder="e.g., SBI Main Account"
          required
        />
        <Input
          v-model="addForm.bank_name"
          label="Bank Name"
          placeholder="e.g., State Bank of India"
          required
        />
        <Input
          v-model="addForm.account_number"
          label="Account Number"
          required
        />
        <Input
          v-model="addForm.ifsc_code"
          label="IFSC Code"
        />
        <Input
          v-model="addForm.branch"
          label="Branch"
        />
        <Input
          v-model.number="addForm.opening_balance"
          label="Opening Balance"
          type="number"
          min="0"
          step="0.01"
          required
        />
        <div class="space-y-3">
          <label class="flex items-center gap-2">
            <input
              v-model="addForm.is_upi_account"
              type="checkbox"
              class="rounded text-primary-600 focus:ring-primary-500"
            />
            <span class="text-sm text-gray-700">This is the UPI account</span>
          </label>
          <label class="flex items-center gap-2">
            <input
              v-model="addForm.is_card_account"
              type="checkbox"
              class="rounded text-primary-600 focus:ring-primary-500"
            />
            <span class="text-sm text-gray-700">This is the Card/POS account</span>
          </label>
          <p class="text-xs text-gray-500">
            Note: Only one bank account can be marked for each payment type
          </p>
        </div>

        <div class="flex justify-end gap-3 pt-4">
          <Button variant="outline" @click="showAddModal = false">Cancel</Button>
          <Button variant="primary" :loading="adding" @click="saveNewAccount">Add Account</Button>
        </div>
      </div>
    </Modal>

    <!-- Transfer Modal -->
    <Modal
      :show="showTransferModal"
      :title="transferType === 'deposit' ? 'Cash Deposit to Bank' : 'Cash Withdrawal from Bank'"
      @close="showTransferModal = false"
    >
      <div class="space-y-4">
        <div class="p-4 rounded-lg" :class="transferType === 'deposit' ? 'bg-blue-50' : 'bg-green-50'">
          <p class="text-sm text-gray-600">
            {{ transferType === 'deposit' ? 'Transfer cash to bank account' : 'Withdraw cash from bank' }}
          </p>
        </div>

        <!-- From Account -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">From Account *</label>
          <select
            v-model="transferForm.from_account_id"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">Select Account</option>
            <option v-if="transferType === 'deposit' && cashAccount" :value="cashAccount.id">
              {{ cashAccount.account_name }} ({{ formatAmount(cashAccount.current_balance) }})
            </option>
            <template v-if="transferType === 'withdraw'">
              <option v-for="acc in activeBankAccounts" :key="acc.id" :value="acc.id">
                {{ acc.account_name }} ({{ formatAmount(acc.current_balance) }})
              </option>
            </template>
          </select>
          <p v-if="transferForm.from_account_id" class="mt-1 text-xs text-gray-500">
            Available: {{ formatAmount(getFromAccountBalance) }}
          </p>
        </div>

        <!-- To Account -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">To Account *</label>
          <select
            v-model="transferForm.to_account_id"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">Select Account</option>
            <template v-if="transferType === 'deposit'">
              <option v-for="acc in activeBankAccounts" :key="acc.id" :value="acc.id">
                {{ acc.account_name }}
              </option>
            </template>
            <option v-if="transferType === 'withdraw' && cashAccount" :value="cashAccount.id">
              {{ cashAccount.account_name }}
            </option>
          </select>
        </div>

        <!-- Amount -->
        <Input
          v-model.number="transferForm.amount"
          label="Amount"
          type="number"
          min="0.01"
          :max="getFromAccountBalance"
          step="0.01"
          required
        />

        <!-- Date -->
        <Input
          v-model="transferForm.entry_date"
          label="Date"
          type="date"
          required
        />

        <!-- Narration -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Narration *</label>
          <textarea
            v-model="transferForm.narration"
            rows="2"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            :placeholder="transferType === 'deposit' ? 'e.g., Daily collection deposit' : 'e.g., Cash withdrawal for expenses'"
          ></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-4">
          <Button variant="outline" @click="showTransferModal = false">Cancel</Button>
          <Button
            variant="primary"
            :loading="transferring"
            @click="submitTransfer"
          >
            {{ transferType === 'deposit' ? 'Deposit to Bank' : 'Withdraw Cash' }}
          </Button>
        </div>
      </div>
    </Modal>
  </div>
</template>
