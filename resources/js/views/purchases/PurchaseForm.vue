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
import { PlusIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const route = useRoute();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(true);
const saving = ref(false);

// Dropdown data
const vendors = ref([]);
const categories = ref([]);
const purposes = ref([]);
const accounts = ref([]);

// Quick add modals
const showVendorModal = ref(false);
const showCategoryModal = ref(false);
const showPurposeModal = ref(false);
const quickAddName = ref('');
const quickAddSaving = ref(false);

// Success modal
const showSuccessModal = ref(false);
const createdPurchase = ref(null);

// Form
const form = ref({
  purchase_date: new Date().toISOString().split('T')[0],
  vendor_id: '',
  category_id: '',
  purpose_id: '',
  item_description: '',
  quantity: 1,
  unit: '',
  unit_price: 0,
  payment_status: 'pending',
  paid_amount: 0,
  payment_method: 'cash',
  account_id: '',
  bill_number: '',
  notes: '',
});

const errors = ref({});

const units = [
  { value: '', label: 'Select Unit' },
  { value: 'kg', label: 'Kilogram (kg)' },
  { value: 'g', label: 'Gram (g)' },
  { value: 'litre', label: 'Litre' },
  { value: 'ml', label: 'Millilitre (ml)' },
  { value: 'nos', label: 'Numbers (nos)' },
  { value: 'packet', label: 'Packet' },
  { value: 'box', label: 'Box' },
  { value: 'bundle', label: 'Bundle' },
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

const totalAmount = computed(() => {
  return (form.value.quantity || 0) * (form.value.unit_price || 0);
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
    // bank_transfer, credit - show all bank accounts
    return accounts.value.filter(a => a.account_type === 'bank');
  }
});

const balanceAmount = computed(() => {
  return totalAmount.value - (form.value.paid_amount || 0);
});

const fetchDropdowns = async () => {
  try {
    const [vendorsRes, categoriesRes, purposesRes, accountsRes] = await Promise.all([
      api.get('/vendors/all'),
      api.get('/purchase-categories/all'),
      api.get('/purchase-purposes/all'),
      api.get('/accounts/all'),
    ]);
    vendors.value = vendorsRes.data.data;
    categories.value = categoriesRes.data.data;
    purposes.value = purposesRes.data.data;
    accounts.value = accountsRes.data.data;
    // Set default to cash account
    const cashAccount = accounts.value.find(a => a.account_type === 'cash');
    if (cashAccount && !form.value.account_id) {
      form.value.account_id = cashAccount.id;
    }
  } catch (error) {
    console.error('Failed to fetch dropdowns:', error);
  }
};

// Flag to skip auto-select when loading existing data
const skipAutoSelect = ref(false);

// Auto-select account when payment method changes
watch(() => form.value.payment_method, (method) => {
  if (skipAutoSelect.value) {
    skipAutoSelect.value = false;
    return;
  }
  // Don't auto-select if accounts not loaded yet
  if (!accounts.value.length) return;

  if (method === 'cash') {
    const cashAccount = accounts.value.find(a => a.account_type === 'cash');
    form.value.account_id = cashAccount?.id || '';
  } else if (method === 'upi') {
    const upiAccount = accounts.value.find(a => a.is_upi_account);
    form.value.account_id = upiAccount?.id || '';
  } else if (method === 'card') {
    const cardAccount = accounts.value.find(a => a.is_card_account);
    form.value.account_id = cardAccount?.id || '';
  } else if (method === 'credit') {
    // Credit purchases don't need account selection
    form.value.account_id = '';
  } else {
    // For bank_transfer - select first bank account or clear
    const bankAccounts = accounts.value.filter(a => a.account_type === 'bank');
    form.value.account_id = bankAccounts.length === 1 ? bankAccounts[0].id : '';
  }
});

const fetchPurchase = async () => {
  if (!isEdit.value) {
    loading.value = false;
    return;
  }

  try {
    const response = await api.get(`/purchases/${route.params.id}`);
    const data = response.data.data;
    // Skip auto-select when loading existing data
    skipAutoSelect.value = true;
    form.value = {
      purchase_date: data.purchase_date.split('T')[0],
      vendor_id: data.vendor_id,
      category_id: data.category_id,
      purpose_id: data.purpose_id,
      item_description: data.item_description,
      quantity: data.quantity,
      unit: data.unit || '',
      unit_price: data.unit_price,
      payment_status: data.payment_status,
      paid_amount: data.paid_amount,
      payment_method: data.payment_method || 'cash',
      account_id: data.account_id || '',
      bill_number: data.bill_number || '',
      notes: data.notes || '',
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch purchase', 'error');
    router.push('/purchases');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    const submitData = {
      ...form.value,
      total_amount: totalAmount.value,
    };

    // Set payment status based on paid amount
    if (submitData.paid_amount >= totalAmount.value) {
      submitData.payment_status = 'paid';
    } else if (submitData.paid_amount > 0) {
      submitData.payment_status = 'partial';
    } else {
      submitData.payment_status = 'pending';
    }

    if (isEdit.value) {
      await api.put(`/purchases/${route.params.id}`, submitData);
      uiStore.showToast('Purchase updated successfully', 'success');
      router.push('/purchases');
    } else {
      const response = await api.post('/purchases', submitData);
      createdPurchase.value = response.data.data;
      showSuccessModal.value = true;
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save purchase', 'error');
    }
  } finally {
    saving.value = false;
  }
};

const quickAddVendor = async () => {
  if (!quickAddName.value.trim()) return;
  quickAddSaving.value = true;
  try {
    const response = await api.post('/vendors', { name: quickAddName.value.trim() });
    vendors.value.push(response.data.data);
    form.value.vendor_id = response.data.data.id;
    showVendorModal.value = false;
    quickAddName.value = '';
    uiStore.showToast('Vendor added', 'success');
  } catch (error) {
    uiStore.showToast('Failed to add vendor', 'error');
  } finally {
    quickAddSaving.value = false;
  }
};

const quickAddCategory = async () => {
  if (!quickAddName.value.trim()) return;
  quickAddSaving.value = true;
  try {
    const response = await api.post('/purchase-categories', { name: quickAddName.value.trim() });
    categories.value.push(response.data.data);
    form.value.category_id = response.data.data.id;
    showCategoryModal.value = false;
    quickAddName.value = '';
    uiStore.showToast('Category added', 'success');
  } catch (error) {
    uiStore.showToast('Failed to add category', 'error');
  } finally {
    quickAddSaving.value = false;
  }
};

const quickAddPurpose = async () => {
  if (!quickAddName.value.trim()) return;
  quickAddSaving.value = true;
  try {
    const response = await api.post('/purchase-purposes', { name: quickAddName.value.trim() });
    purposes.value.push(response.data.data);
    form.value.purpose_id = response.data.data.id;
    showPurposeModal.value = false;
    quickAddName.value = '';
    uiStore.showToast('Purpose added', 'success');
  } catch (error) {
    uiStore.showToast('Failed to add purpose', 'error');
  } finally {
    quickAddSaving.value = false;
  }
};

const resetForm = () => {
  form.value = {
    purchase_date: new Date().toISOString().split('T')[0],
    vendor_id: '',
    category_id: '',
    purpose_id: '',
    item_description: '',
    quantity: 1,
    unit: '',
    unit_price: 0,
    payment_status: 'pending',
    paid_amount: 0,
    payment_method: 'cash',
    account_id: accounts.value.find(a => a.account_type === 'cash')?.id || '',
    bill_number: '',
    notes: '',
  };
  errors.value = {};
  showSuccessModal.value = false;
  createdPurchase.value = null;
};

onMounted(async () => {
  await fetchDropdowns();
  await fetchPurchase();
});
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isEdit ? 'Edit Purchase' : 'New Purchase' }}
      </h1>
      <p class="text-gray-500">
        {{ isEdit ? 'Update purchase details' : 'Record a new purchase entry' }}
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
      <!-- Basic Info -->
      <Card title="Purchase Details">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Input
            v-model="form.purchase_date"
            label="Purchase Date"
            type="date"
            required
            :error="errors.purchase_date?.[0]"
          />

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-sm font-medium text-gray-700">Vendor *</label>
              <button type="button" @click="showVendorModal = true; quickAddName = ''" class="text-xs text-primary-600 hover:underline">
                + Add New
              </button>
            </div>
            <select
              v-model="form.vendor_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option value="">Select Vendor</option>
              <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
            </select>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-sm font-medium text-gray-700">Category *</label>
              <button type="button" @click="showCategoryModal = true; quickAddName = ''" class="text-xs text-primary-600 hover:underline">
                + Add New
              </button>
            </div>
            <select
              v-model="form.category_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option value="">Select Category</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-sm font-medium text-gray-700">Purpose *</label>
              <button type="button" @click="showPurposeModal = true; quickAddName = ''" class="text-xs text-primary-600 hover:underline">
                + Add New
              </button>
            </div>
            <select
              v-model="form.purpose_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option value="">Select Purpose</option>
              <option v-for="p in purposes" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
        </div>

        <div class="mt-6">
          <Input
            v-model="form.item_description"
            label="Item Description"
            placeholder="e.g., Fresh flowers for daily pooja"
            required
            :error="errors.item_description?.[0]"
          />
        </div>
      </Card>

      <!-- Quantity & Price -->
      <Card title="Quantity & Price">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <Input
            v-model.number="form.quantity"
            label="Quantity"
            type="number"
            min="0.01"
            step="0.01"
            required
            :error="errors.quantity?.[0]"
          />

          <Select
            v-model="form.unit"
            label="Unit"
            :options="units"
          />

          <Input
            v-model.number="form.unit_price"
            label="Unit Price"
            type="number"
            min="0"
            step="0.01"
            required
            :error="errors.unit_price?.[0]"
          />

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount</label>
            <div class="px-3 py-2 bg-primary-50 border border-primary-200 rounded-md text-lg font-bold text-primary-700">
              {{ new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(totalAmount) }}
            </div>
          </div>
        </div>

        <div class="mt-4">
          <Input
            v-model="form.bill_number"
            label="Bill/Invoice Number"
            placeholder="Optional"
          />
        </div>
      </Card>

      <!-- Payment -->
      <Card title="Payment">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <Input
            v-model.number="form.paid_amount"
            label="Amount Paid"
            type="number"
            min="0"
            :max="totalAmount"
            step="0.01"
          />

          <Select
            v-model="form.payment_method"
            label="Payment Method"
            :options="paymentMethods"
          />

          <div v-if="form.paid_amount > 0 && form.payment_method !== 'credit'">
            <label class="block text-sm font-medium text-gray-700 mb-1">Debit From Account *</label>
            <select
              v-model="form.account_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              :class="{ 'border-red-500': errors.account_id }"
            >
              <option value="">Select Account</option>
              <option v-for="acc in filteredAccounts" :key="acc.id" :value="acc.id">
                {{ acc.account_name }} (₹{{ parseFloat(acc.current_balance).toLocaleString() }})
              </option>
            </select>
            <p v-if="errors.account_id" class="mt-1 text-sm text-red-600">{{ errors.account_id[0] }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Balance</label>
            <div :class="['px-3 py-2 rounded-md text-lg font-bold', balanceAmount > 0 ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700']">
              {{ new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(balanceAmount) }}
            </div>
          </div>
        </div>

        <div class="mt-4">
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
        <Button type="button" variant="outline" @click="router.push('/purchases')">
          Cancel
        </Button>
        <Button type="submit" variant="primary" :loading="saving">
          {{ isEdit ? 'Update Purchase' : 'Save Purchase' }}
        </Button>
      </div>
    </form>

    <!-- Quick Add Vendor Modal -->
    <Modal :show="showVendorModal" title="Add New Vendor" @close="showVendorModal = false">
      <div class="space-y-4">
        <Input v-model="quickAddName" label="Vendor Name" placeholder="Enter vendor name" />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showVendorModal = false">Cancel</Button>
          <Button variant="primary" :loading="quickAddSaving" @click="quickAddVendor">Add</Button>
        </div>
      </div>
    </Modal>

    <!-- Quick Add Category Modal -->
    <Modal :show="showCategoryModal" title="Add New Category" @close="showCategoryModal = false">
      <div class="space-y-4">
        <Input v-model="quickAddName" label="Category Name" placeholder="e.g., Flowers, Ghee, etc." />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showCategoryModal = false">Cancel</Button>
          <Button variant="primary" :loading="quickAddSaving" @click="quickAddCategory">Add</Button>
        </div>
      </div>
    </Modal>

    <!-- Quick Add Purpose Modal -->
    <Modal :show="showPurposeModal" title="Add New Purpose" @close="showPurposeModal = false">
      <div class="space-y-4">
        <Input v-model="quickAddName" label="Purpose Name" placeholder="e.g., Daily Use, Festival, etc." />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showPurposeModal = false">Cancel</Button>
          <Button variant="primary" :loading="quickAddSaving" @click="quickAddPurpose">Add</Button>
        </div>
      </div>
    </Modal>

    <!-- Success Modal -->
    <Modal :show="showSuccessModal" title="Purchase Recorded" @close="router.push('/purchases')">
      <div class="text-center py-4">
        <div class="mx-auto flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
          <CheckCircleIcon class="w-10 h-10 text-green-600" />
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Purchase Recorded Successfully!</h3>
        <p v-if="createdPurchase" class="text-gray-600">
          {{ createdPurchase.purchase_number }}
        </p>
      </div>
      <div class="flex justify-center gap-3 pt-4 border-t">
        <Button variant="outline" @click="resetForm">Add Another</Button>
        <Button variant="primary" @click="router.push('/purchases')">View All</Button>
      </div>
    </Modal>
  </div>
</template>
