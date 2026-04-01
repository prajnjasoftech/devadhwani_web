<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Modal from '@/components/ui/Modal.vue';
import { CheckCircleIcon, CurrencyRupeeIcon, GiftIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const route = useRoute();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(true);
const saving = ref(false);

// Dropdown data
const donationHeads = ref([]);
const assetTypes = ref([]);
const accounts = ref([]);

// Quick add modals
const showHeadModal = ref(false);
const showAssetTypeModal = ref(false);
const quickAddForm = ref({ name: '', description: '', unit: '' });
const quickAddSaving = ref(false);

// Success modal
const showSuccessModal = ref(false);
const createdDonation = ref(null);

// Form
const form = ref({
  donation_date: new Date().toISOString().split('T')[0],
  donation_head_id: '',
  donation_type: 'financial',
  donor_name: '',
  donor_contact: '',
  donor_address: '',
  // Financial fields
  amount: 0,
  payment_method: 'cash',
  account_id: '',
  reference_number: '',
  // Asset fields
  asset_type_id: '',
  asset_description: '',
  quantity: 1,
  estimated_value: null,
  notes: '',
});

const errors = ref({});

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
    { value: 'cheque', label: 'Cheque' },
    { value: 'other', label: 'Other' }
  );
  return methods;
});

// Filter accounts based on payment method
const filteredAccounts = computed(() => {
  const method = form.value.payment_method;
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

const selectedAssetType = computed(() => {
  return assetTypes.value.find(t => t.id === form.value.asset_type_id);
});

// Flag to skip auto-select when loading existing data
const skipAutoSelect = ref(false);

const fetchDropdowns = async () => {
  try {
    const [headsRes, assetTypesRes, accountsRes] = await Promise.all([
      api.get('/donation-heads/all'),
      api.get('/asset-types/all'),
      api.get('/accounts/all'),
    ]);
    donationHeads.value = headsRes.data.data;
    assetTypes.value = assetTypesRes.data.data;
    accounts.value = accountsRes.data.data;

    // Auto-select appropriate account based on payment method (only for new donations)
    if (!isEdit.value) {
      autoSelectAccount();
    }
  } catch (error) {
    console.error('Failed to fetch dropdowns:', error);
  }
};

const autoSelectAccount = () => {
  // Don't auto-select for asset donations or if accounts not loaded
  if (form.value.donation_type !== 'financial' || !accounts.value.length) return;

  // Skip if flag is set (loading existing data)
  if (skipAutoSelect.value) {
    skipAutoSelect.value = false;
    return;
  }

  const method = form.value.payment_method;
  if (method === 'cash') {
    const cashAccount = accounts.value.find(a => a.account_type === 'cash');
    form.value.account_id = cashAccount?.id || '';
  } else if (method === 'upi') {
    const upiAccount = accounts.value.find(a => a.is_upi_account);
    form.value.account_id = upiAccount?.id || '';
  } else if (method === 'card') {
    const cardAccount = accounts.value.find(a => a.is_card_account);
    form.value.account_id = cardAccount?.id || '';
  } else {
    // bank_transfer, cheque, other - show bank accounts
    const bankAccounts = accounts.value.filter(a => a.account_type === 'bank');
    form.value.account_id = bankAccounts.length === 1 ? bankAccounts[0].id : '';
  }
};

const fetchDonation = async () => {
  if (!isEdit.value) {
    loading.value = false;
    return;
  }

  try {
    const response = await api.get(`/donations/${route.params.id}`);
    const data = response.data.data;
    // Skip auto-select when loading existing data
    skipAutoSelect.value = true;
    form.value = {
      donation_date: data.donation_date.split('T')[0],
      donation_head_id: data.donation_head_id,
      donation_type: data.donation_type,
      donor_name: data.donor_name,
      donor_contact: data.donor_contact || '',
      donor_address: data.donor_address || '',
      amount: data.amount || 0,
      payment_method: data.payment_method || 'cash',
      account_id: data.account_id || '',
      reference_number: data.reference_number || '',
      asset_type_id: data.asset_type_id || '',
      asset_description: data.asset_description || '',
      quantity: data.quantity || 1,
      estimated_value: data.estimated_value,
      notes: data.notes || '',
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch donation', 'error');
    router.push('/donations');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    const submitData = { ...form.value };

    // Clear irrelevant fields based on donation type
    if (submitData.donation_type === 'asset') {
      submitData.amount = null;
      submitData.payment_method = null;
      submitData.account_id = null;
      submitData.reference_number = null;
    } else {
      submitData.asset_type_id = null;
      submitData.asset_description = null;
      submitData.quantity = null;
      submitData.estimated_value = null;
    }

    if (isEdit.value) {
      await api.put(`/donations/${route.params.id}`, submitData);
      uiStore.showToast('Donation updated successfully', 'success');
      router.push('/donations');
    } else {
      const response = await api.post('/donations', submitData);
      createdDonation.value = response.data.data;
      showSuccessModal.value = true;
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save donation', 'error');
    }
  } finally {
    saving.value = false;
  }
};

const quickAddHead = async () => {
  if (!quickAddForm.value.name.trim()) return;
  quickAddSaving.value = true;
  try {
    const response = await api.post('/donation-heads', {
      name: quickAddForm.value.name.trim(),
      description: quickAddForm.value.description.trim() || null,
    });
    donationHeads.value.push(response.data.data);
    form.value.donation_head_id = response.data.data.id;
    showHeadModal.value = false;
    quickAddForm.value = { name: '', description: '', unit: '' };
    uiStore.showToast('Donation head added', 'success');
  } catch (error) {
    uiStore.showToast('Failed to add donation head', 'error');
  } finally {
    quickAddSaving.value = false;
  }
};

const quickAddAssetType = async () => {
  if (!quickAddForm.value.name.trim()) return;
  quickAddSaving.value = true;
  try {
    const response = await api.post('/asset-types', {
      name: quickAddForm.value.name.trim(),
      unit: quickAddForm.value.unit.trim() || null,
      description: quickAddForm.value.description.trim() || null,
    });
    assetTypes.value.push(response.data.data);
    form.value.asset_type_id = response.data.data.id;
    showAssetTypeModal.value = false;
    quickAddForm.value = { name: '', description: '', unit: '' };
    uiStore.showToast('Asset type added', 'success');
  } catch (error) {
    uiStore.showToast('Failed to add asset type', 'error');
  } finally {
    quickAddSaving.value = false;
  }
};

const resetForm = () => {
  form.value = {
    donation_date: new Date().toISOString().split('T')[0],
    donation_head_id: '',
    donation_type: 'financial',
    donor_name: '',
    donor_contact: '',
    donor_address: '',
    amount: 0,
    payment_method: 'cash',
    account_id: '',
    reference_number: '',
    asset_type_id: '',
    asset_description: '',
    quantity: 1,
    estimated_value: null,
    notes: '',
  };
  errors.value = {};
  showSuccessModal.value = false;
  createdDonation.value = null;
  autoSelectAccount();
};

// Watch for payment method changes to auto-select account
watch(() => form.value.payment_method, autoSelectAccount);

onMounted(async () => {
  await fetchDropdowns();
  await fetchDonation();
});
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isEdit ? 'Edit Donation' : 'New Donation' }}
      </h1>
      <p class="text-gray-500">
        {{ isEdit ? 'Update donation details' : 'Record a new donation' }}
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Donation Type Selection -->
      <Card v-if="!isEdit">
        <template #header>
          <span class="font-semibold">Donation Type</span>
        </template>
        <div class="flex gap-4">
          <label
            :class="[
              'flex-1 p-4 border-2 rounded-lg cursor-pointer transition-all',
              form.donation_type === 'financial'
                ? 'border-green-500 bg-green-50'
                : 'border-gray-200 hover:border-gray-300'
            ]"
          >
            <input
              v-model="form.donation_type"
              type="radio"
              value="financial"
              class="sr-only"
            />
            <div class="flex items-center gap-3">
              <div :class="['p-2 rounded-full', form.donation_type === 'financial' ? 'bg-green-100' : 'bg-gray-100']">
                <CurrencyRupeeIcon class="w-6 h-6" :class="form.donation_type === 'financial' ? 'text-green-600' : 'text-gray-500'" />
              </div>
              <div>
                <p class="font-medium" :class="form.donation_type === 'financial' ? 'text-green-700' : 'text-gray-700'">
                  Financial Donation
                </p>
                <p class="text-sm text-gray-500">Cash, UPI, Bank Transfer, Cheque</p>
              </div>
            </div>
          </label>

          <label
            :class="[
              'flex-1 p-4 border-2 rounded-lg cursor-pointer transition-all',
              form.donation_type === 'asset'
                ? 'border-purple-500 bg-purple-50'
                : 'border-gray-200 hover:border-gray-300'
            ]"
          >
            <input
              v-model="form.donation_type"
              type="radio"
              value="asset"
              class="sr-only"
            />
            <div class="flex items-center gap-3">
              <div :class="['p-2 rounded-full', form.donation_type === 'asset' ? 'bg-purple-100' : 'bg-gray-100']">
                <GiftIcon class="w-6 h-6" :class="form.donation_type === 'asset' ? 'text-purple-600' : 'text-gray-500'" />
              </div>
              <div>
                <p class="font-medium" :class="form.donation_type === 'asset' ? 'text-purple-700' : 'text-gray-700'">
                  Asset Donation
                </p>
                <p class="text-sm text-gray-500">Gold, Silver, Land, Vehicle, etc.</p>
              </div>
            </div>
          </label>
        </div>
      </Card>

      <!-- Donor Details -->
      <Card title="Donor Details">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Input
            v-model="form.donation_date"
            label="Donation Date"
            type="date"
            required
            :error="errors.donation_date?.[0]"
          />

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-sm font-medium text-gray-700">Donation Head *</label>
              <button type="button" @click="showHeadModal = true; quickAddForm = { name: '', description: '', unit: '' }" class="text-xs text-primary-600 hover:underline">
                + Add New
              </button>
            </div>
            <select
              v-model="form.donation_head_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option value="">Select Head</option>
              <option v-for="h in donationHeads" :key="h.id" :value="h.id">{{ h.name }}</option>
            </select>
          </div>

          <Input
            v-model="form.donor_name"
            label="Donor Name"
            placeholder="Enter donor name"
            required
            :error="errors.donor_name?.[0]"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
          <Input
            v-model="form.donor_contact"
            label="Contact Number"
            placeholder="Optional"
          />
          <Input
            v-model="form.donor_address"
            label="Address"
            placeholder="Optional"
          />
        </div>
      </Card>

      <!-- Financial Details -->
      <Card v-if="form.donation_type === 'financial'" title="Financial Details">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <Input
            v-model.number="form.amount"
            label="Amount"
            type="number"
            min="0.01"
            step="0.01"
            required
            :error="errors.amount?.[0]"
          />

          <Select
            v-model="form.payment_method"
            label="Payment Method"
            :options="paymentMethods"
            required
          />

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Account *</label>
            <select
              v-model="form.account_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option value="">Select Account</option>
              <option v-for="a in filteredAccounts" :key="a.id" :value="a.id">
                {{ a.account_name }} (₹{{ parseFloat(a.current_balance).toLocaleString() }})
              </option>
            </select>
          </div>

          <Input
            v-model="form.reference_number"
            label="Reference Number"
            placeholder="Cheque/Transaction ID"
          />
        </div>
      </Card>

      <!-- Asset Details -->
      <Card v-if="form.donation_type === 'asset'" title="Asset Details">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-sm font-medium text-gray-700">Asset Type *</label>
              <button type="button" @click="showAssetTypeModal = true; quickAddForm = { name: '', description: '', unit: '' }" class="text-xs text-primary-600 hover:underline">
                + Add New
              </button>
            </div>
            <select
              v-model="form.asset_type_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option value="">Select Type</option>
              <option v-for="t in assetTypes" :key="t.id" :value="t.id">
                {{ t.name }}{{ t.unit ? ` (${t.unit})` : '' }}
              </option>
            </select>
          </div>

          <Input
            v-model="form.asset_description"
            label="Description"
            placeholder="e.g., Gold Chain, Silver Lamp"
            required
            :error="errors.asset_description?.[0]"
          />

          <div>
            <Input
              v-model.number="form.quantity"
              :label="`Quantity${selectedAssetType?.unit ? ` (${selectedAssetType.unit})` : ''}`"
              type="number"
              min="0.001"
              step="0.001"
              required
              :error="errors.quantity?.[0]"
            />
          </div>

          <Input
            v-model.number="form.estimated_value"
            label="Estimated Value (Optional)"
            type="number"
            min="0"
            step="0.01"
            placeholder="Approximate value in ₹"
          />
        </div>
      </Card>

      <!-- Notes -->
      <Card title="Additional Information">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea
            v-model="form.notes"
            rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Any additional notes..."
          ></textarea>
        </div>
      </Card>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-4">
        <Button type="button" variant="outline" @click="router.push('/donations')">
          Cancel
        </Button>
        <Button type="submit" variant="primary" :loading="saving">
          {{ isEdit ? 'Update Donation' : 'Save Donation' }}
        </Button>
      </div>
    </form>

    <!-- Quick Add Head Modal -->
    <Modal :show="showHeadModal" title="Add Donation Head" @close="showHeadModal = false">
      <div class="space-y-4">
        <Input v-model="quickAddForm.name" label="Head Name" placeholder="e.g., General Donation, Annadanam" />
        <Input v-model="quickAddForm.description" label="Description (Optional)" placeholder="Brief description" />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showHeadModal = false">Cancel</Button>
          <Button variant="primary" :loading="quickAddSaving" @click="quickAddHead">Add</Button>
        </div>
      </div>
    </Modal>

    <!-- Quick Add Asset Type Modal -->
    <Modal :show="showAssetTypeModal" title="Add Asset Type" @close="showAssetTypeModal = false">
      <div class="space-y-4">
        <Input v-model="quickAddForm.name" label="Asset Type Name" placeholder="e.g., Gold, Silver, Land" />
        <Input v-model="quickAddForm.unit" label="Unit (Optional)" placeholder="e.g., grams, kg, sq.ft" />
        <Input v-model="quickAddForm.description" label="Description (Optional)" placeholder="Brief description" />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showAssetTypeModal = false">Cancel</Button>
          <Button variant="primary" :loading="quickAddSaving" @click="quickAddAssetType">Add</Button>
        </div>
      </div>
    </Modal>

    <!-- Success Modal -->
    <Modal :show="showSuccessModal" title="Donation Recorded" @close="router.push('/donations')">
      <div class="text-center py-4">
        <div class="mx-auto flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
          <CheckCircleIcon class="w-10 h-10 text-green-600" />
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Donation Recorded Successfully!</h3>
        <p v-if="createdDonation" class="text-gray-600">
          {{ createdDonation.donation_number }}
        </p>
      </div>
      <div class="flex justify-center gap-3 pt-4 border-t">
        <Button variant="outline" @click="resetForm">Add Another</Button>
        <Button variant="primary" @click="router.push('/donations')">View All</Button>
      </div>
    </Modal>
  </div>
</template>
