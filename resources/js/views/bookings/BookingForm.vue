<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Modal from '@/components/ui/Modal.vue';
import { PlusIcon, TrashIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const uiStore = useUiStore();

// Data loading
const loading = ref(true);
const saving = ref(false);
const poojas = ref([]);
const deities = ref([]);
const nakshathras = ref([]);
const accounts = ref([]);

// Success state
const showSuccessModal = ref(false);
const createdBooking = ref(null);

// Autocomplete state
const searchResults = ref([]);
const activeSearchIndex = ref(-1);
const showDropdown = ref({});

// Form data
const form = ref({
  booking_date: new Date().toISOString().split('T')[0],
  pooja_id: '',
  deity_id: '',
  frequency: 'once',
  weekly_day: null,
  start_date: new Date().toISOString().split('T')[0],
  end_date: '',
  quantity: 1, // For poojas that don't require devotee details
  beneficiaries: [{ name: '', nakshathra_id: '', gothram: '', searching: false }],
  prasadam_required: false,
  contact_name: '',
  contact_number: '',
  contact_address: '',
  notes: '',
  payment_amount: 0,
  payment_method: 'cash',
  account_id: '',
});

const errors = ref({});

// Frequency options
const frequencies = [
  { value: 'once', label: 'One Time' },
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
];

// Malayalam days of week
const malayalamDays = [
  { value: 0, label: 'ഞായറാഴ്ച (Sunday)' },
  { value: 1, label: 'തിങ്കളാഴ്ച (Monday)' },
  { value: 2, label: 'ചൊവ്വാഴ്ച (Tuesday)' },
  { value: 3, label: 'ബുധനാഴ്ച (Wednesday)' },
  { value: 4, label: 'വ്യാഴാഴ്ച (Thursday)' },
  { value: 5, label: 'വെള്ളിയാഴ്ച (Friday)' },
  { value: 6, label: 'ശനിയാഴ്ച (Saturday)' },
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
  methods.push({ value: 'bank_transfer', label: 'Bank Transfer' });
  return methods;
});

// Get selected pooja info
const selectedPooja = computed(() => {
  return poojas.value.find(p => p.id == form.value.pooja_id);
});

// Calculate occurrences based on frequency and date range
const occurrences = computed(() => {
  if (!form.value.start_date || form.value.frequency === 'once') return 1;
  if (!form.value.end_date) return 1;

  const start = new Date(form.value.start_date);
  const end = new Date(form.value.end_date);

  if (end < start) return 1;

  const diffTime = end - start;
  const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

  switch (form.value.frequency) {
    case 'daily':
      return diffDays + 1;
    case 'weekly':
      return Math.floor(diffDays / 7) + 1;
    case 'monthly':
      const months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
      return months + 1;
    default:
      return 1;
  }
});

// Count - either quantity (for no devotee required) or actual devotee count
const itemCount = computed(() => {
  if (!selectedPooja.value?.devotee_required) {
    return form.value.quantity || 1;
  }
  const count = form.value.beneficiaries.filter(b => b.name?.trim()).length;
  return count > 0 ? count : 1;
});

// Calculate total amount (amount * occurrences * count)
const totalAmount = computed(() => {
  if (!selectedPooja.value) return 0;
  return selectedPooja.value.amount * occurrences.value * itemCount.value;
});

// Calculate balance
const balance = computed(() => {
  return totalAmount.value - (form.value.payment_amount || 0);
});

// Check if booking has recurring poojas
const hasRecurringPooja = computed(() => {
  return form.value.frequency !== 'once';
});

// Check if contact details are required
const contactRequired = computed(() => {
  return balance.value > 0 || hasRecurringPooja.value || form.value.prasadam_required;
});

// Check if at least one devotee has a valid name
const hasValidDevotee = computed(() => {
  return form.value.beneficiaries.some(b => b.name?.trim());
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
    // bank_transfer, other - show all bank accounts
    return accounts.value.filter(a => a.account_type === 'bank');
  }
});

// Build reason text for contact requirement
const contactRequiredReason = computed(() => {
  const reasons = [];
  if (balance.value > 0) reasons.push('partial payment');
  if (hasRecurringPooja.value) reasons.push('recurring poojas');
  if (form.value.prasadam_required) reasons.push('sending prasadam');
  return reasons.join(', ');
});

// Form validation
const isValid = computed(() => {
  if (!form.value.pooja_id || !form.value.start_date) {
    return false;
  }

  // Check beneficiaries if required
  if (selectedPooja.value?.devotee_required) {
    const hasValidBeneficiary = form.value.beneficiaries.some(b => b.name?.trim());
    if (!hasValidBeneficiary) return false;
  }

  // Check contact details if required
  if (contactRequired.value) {
    if (!form.value.contact_name || !form.value.contact_number) {
      return false;
    }
    if (form.value.prasadam_required && !form.value.contact_address) {
      return false;
    }
  }

  return true;
});

// Fetch data
const fetchPoojas = async () => {
  try {
    const response = await api.get('/poojas/all');
    poojas.value = response.data.data.filter(p => p.is_active);
  } catch (error) {
    console.error('Failed to fetch poojas:', error);
  }
};

const fetchDeities = async () => {
  try {
    const response = await api.get('/deities/all');
    deities.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch deities:', error);
  }
};

const fetchNakshathras = async () => {
  try {
    const response = await api.get('/nakshathras');
    nakshathras.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch nakshathras:', error);
  }
};

const fetchAccounts = async () => {
  try {
    const response = await api.get('/accounts/all');
    accounts.value = response.data.data;
    // Set default to cash account if exists
    const cashAccount = accounts.value.find(a => a.account_type === 'cash');
    if (cashAccount) {
      form.value.account_id = cashAccount.id;
    }
  } catch (error) {
    console.error('Failed to fetch accounts:', error);
  }
};

// Auto-fill payment amount with total amount
watch(totalAmount, (newTotal) => {
  form.value.payment_amount = newTotal;
});

// Auto-select account when payment method changes
watch(() => form.value.payment_method, (method) => {
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
    // For bank_transfer, other - select first bank account or clear
    const bankAccounts = accounts.value.filter(a => a.account_type === 'bank');
    form.value.account_id = bankAccounts.length === 1 ? bankAccounts[0].id : '';
  }
});

// On pooja select - pre-fill deity and frequency
const onPoojaSelect = () => {
  const pooja = selectedPooja.value;
  if (pooja) {
    form.value.deity_id = pooja.deity_id || '';  // Empty string for "No specific deity"
    form.value.frequency = pooja.frequency || 'once';

    // If devotee required and no beneficiaries, add one
    if (pooja.devotee_required && form.value.beneficiaries.length === 0) {
      form.value.beneficiaries.push({ name: '', nakshathra_id: '', gothram: '', searching: false });
    }
  }
};

// On frequency change
const onFrequencyChange = () => {
  if (form.value.frequency === 'once') {
    form.value.end_date = '';
    form.value.weekly_day = null;
  } else if (form.value.frequency === 'weekly' && form.value.start_date) {
    // Auto-select weekly_day from start_date
    form.value.weekly_day = new Date(form.value.start_date).getDay();
  }
};

// Auto-update weekly_day when start_date changes (for weekly frequency)
watch(() => form.value.start_date, (newDate) => {
  if (form.value.frequency === 'weekly' && newDate) {
    form.value.weekly_day = new Date(newDate).getDay();
  }
});

// Devotee autocomplete
let searchTimeout = null;
const searchDevotees = async (index, term) => {
  if (term.length < 3) {
    searchResults.value = [];
    showDropdown.value[index] = false;
    return;
  }

  form.value.beneficiaries[index].searching = true;

  // Debounce
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(async () => {
    try {
      const response = await api.get('/devotees/search', { params: { q: term } });
      searchResults.value = response.data.data;
      showDropdown.value[index] = searchResults.value.length > 0;
      activeSearchIndex.value = index;
    } catch (error) {
      console.error('Failed to search devotees:', error);
    } finally {
      form.value.beneficiaries[index].searching = false;
    }
  }, 300);
};

const selectDevotee = (index, devotee) => {
  form.value.beneficiaries[index].name = devotee.name;
  form.value.beneficiaries[index].nakshathra_id = devotee.nakshathra_id ? String(devotee.nakshathra_id) : '';
  form.value.beneficiaries[index].gothram = devotee.gothram || '';
  showDropdown.value[index] = false;
  searchResults.value = [];
};

const hideDropdown = (index) => {
  setTimeout(() => {
    showDropdown.value[index] = false;
  }, 200);
};

// Beneficiary management
const addBeneficiary = () => {
  form.value.beneficiaries.push({ name: '', nakshathra_id: '', gothram: '', searching: false });
};

const removeBeneficiary = (index) => {
  if (form.value.beneficiaries.length > 1) {
    form.value.beneficiaries.splice(index, 1);
  }
};

// Reset form for new booking
const resetForm = (keepContact = false) => {
  const contactInfo = keepContact ? {
    contact_name: form.value.contact_name,
    contact_number: form.value.contact_number,
    contact_address: form.value.contact_address,
  } : {
    contact_name: '',
    contact_number: '',
    contact_address: '',
  };

  form.value = {
    booking_date: new Date().toISOString().split('T')[0],
    pooja_id: '',
    deity_id: '',
    frequency: 'once',
    weekly_day: null,
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    quantity: 1,
    beneficiaries: [{ name: '', nakshathra_id: '', gothram: '', searching: false }],
    prasadam_required: false,
    ...contactInfo,
    notes: '',
    payment_amount: 0,
    payment_method: 'cash',
  };
  errors.value = {};
  searchResults.value = [];
  showDropdown.value = {};
};

// View created booking
const viewBooking = () => {
  if (createdBooking.value) {
    router.push(`/bookings/${createdBooking.value.id}`);
  }
};

// Create another booking (keep contact details for convenience)
const createAnother = (keepContact = true) => {
  showSuccessModal.value = false;
  createdBooking.value = null;
  resetForm(keepContact);
};

// Submit
const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    // Backend automatically creates/links devotees via BookingService
    const submitData = {
      booking_date: form.value.booking_date,
      contact_name: form.value.contact_name || null,
      contact_number: form.value.contact_number || null,
      contact_address: form.value.contact_address || null,
      prasadam_required: form.value.prasadam_required,
      notes: form.value.notes || null,
      items: [{
        pooja_id: form.value.pooja_id,
        deity_id: form.value.deity_id || null,
        frequency: form.value.frequency,
        weekly_day: form.value.frequency === 'weekly' ? form.value.weekly_day : null,
        start_date: form.value.start_date,
        end_date: form.value.frequency !== 'once' ? form.value.end_date : null,
        // If devotee not required, send quantity; otherwise send beneficiaries
        quantity: !selectedPooja.value?.devotee_required ? (form.value.quantity || 1) : null,
        beneficiaries: selectedPooja.value?.devotee_required
          ? form.value.beneficiaries.filter(b => b.name?.trim()).map(b => ({
              name: b.name.trim(),
              nakshathra_id: b.nakshathra_id ? parseInt(b.nakshathra_id) : null,
              gothram: b.gothram?.trim() || null,
            }))
          : [],
      }],
      payment_amount: form.value.payment_amount || null,
      payment_method: form.value.payment_amount > 0 ? form.value.payment_method : null,
      account_id: form.value.payment_amount > 0 ? form.value.account_id : null,
    };

    const response = await api.post('/bookings', submitData);
    createdBooking.value = response.data.data;
    showSuccessModal.value = true;
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast(error.response?.data?.message || 'Failed to create booking', 'error');
    }
  } finally {
    saving.value = false;
  }
};

onMounted(async () => {
  loading.value = true;
  await Promise.all([fetchPoojas(), fetchDeities(), fetchNakshathras(), fetchAccounts()]);
  loading.value = false;
});
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">New Booking</h1>
      <p class="text-gray-500">Create a new pooja booking</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Pooja Selection -->
      <Card title="Pooja Details">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <Input
            v-model="form.booking_date"
            label="Booking Date"
            type="date"
            required
            :error="errors.booking_date?.[0]"
          />
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Pooja *</label>
            <select
              v-model="form.pooja_id"
              @change="onPoojaSelect"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              required
            >
              <option value="">Select Pooja</option>
              <option v-for="pooja in poojas" :key="pooja.id" :value="pooja.id">
                {{ pooja.name }} - {{ pooja.amount_formatted }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Deity</label>
            <select
              v-model="form.deity_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option value="">No specific deity</option>
              <option v-for="deity in deities" :key="deity.id" :value="deity.id">
                {{ deity.name }}
              </option>
            </select>
          </div>
        </div>

        <!-- Frequency and Dates -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Frequency</label>
            <select
              v-model="form.frequency"
              @change="onFrequencyChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option v-for="freq in frequencies" :key="freq.value" :value="freq.value">
                {{ freq.label }}
              </option>
            </select>
          </div>
          <Input
            v-model="form.start_date"
            label="From Date"
            type="date"
            required
          />
          <div v-if="form.frequency !== 'once'">
            <Input
              v-model="form.end_date"
              label="To Date"
              type="date"
              :required="form.frequency !== 'once'"
            />
          </div>
        </div>

        <!-- Weekly Day Selector -->
        <div v-if="form.frequency === 'weekly'" class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">ആഴ്ചയിലെ ദിവസം (Day of Week) *</label>
          <select
            v-model="form.weekly_day"
            class="w-full md:w-1/3 px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            required
          >
            <option v-for="day in malayalamDays" :key="day.value" :value="day.value">
              {{ day.label }}
            </option>
          </select>
          <p class="mt-1 text-xs text-gray-500">
            ആഴ്ചതോറും ഈ ദിവസം പൂജ നടത്തും (Pooja will be performed on this day every week)
          </p>
        </div>

        <!-- Quantity for poojas without devotee requirement -->
        <div v-if="selectedPooja && !selectedPooja.devotee_required" class="mt-6">
          <Input
            v-model.number="form.quantity"
            label="Quantity / Count"
            type="number"
            min="1"
            :error="errors.quantity?.[0]"
          />
        </div>

        <!-- Amount Display -->
        <div v-if="selectedPooja" class="mt-6 p-4 bg-primary-50 rounded-lg flex items-center justify-between">
          <div>
            <span class="text-sm text-gray-600">
              {{ selectedPooja.amount_formatted }} x {{ occurrences }} occurrence(s) x {{ itemCount }} {{ selectedPooja.devotee_required ? 'devotee(s)' : 'count' }}
            </span>
          </div>
          <div class="text-right">
            <span class="text-lg font-bold text-primary-600">₹{{ totalAmount.toLocaleString() }}</span>
          </div>
        </div>
      </Card>

      <!-- Beneficiaries / Devotees - Only show when devotee details are required -->
      <Card v-if="selectedPooja?.devotee_required" title="Devotee Details">
        <!-- Show warning only if devotee required AND no valid name entered yet -->
        <div v-if="selectedPooja?.devotee_required && !hasValidDevotee" class="mb-4 p-3 bg-orange-50 rounded-lg text-sm text-orange-700">
          Devotee details are required for this pooja
        </div>
        <!-- Show success indicator when devotee is filled -->
        <div v-else-if="selectedPooja?.devotee_required && hasValidDevotee" class="mb-4 p-3 bg-green-50 rounded-lg text-sm text-green-700">
          ✓ Devotee details added
        </div>

        <div class="space-y-4">
          <div
            v-for="(ben, index) in form.beneficiaries"
            :key="index"
            class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg"
          >
            <!-- Name with Autocomplete -->
            <div class="relative">
              <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <div class="relative">
                <input
                  v-model="ben.name"
                  @input="searchDevotees(index, ben.name)"
                  @focus="ben.name?.length >= 3 && searchDevotees(index, ben.name)"
                  @blur="hideDropdown(index)"
                  type="text"
                  placeholder="Type 3+ letters to search"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                  :required="selectedPooja?.devotee_required"
                />
                <div v-if="ben.searching" class="absolute right-3 top-1/2 -translate-y-1/2">
                  <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                  </svg>
                </div>
              </div>
              <!-- Autocomplete Dropdown -->
              <div
                v-if="showDropdown[index] && activeSearchIndex === index && searchResults.length > 0"
                class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-auto"
              >
                <button
                  v-for="devotee in searchResults"
                  :key="devotee.id"
                  type="button"
                  @mousedown.prevent="selectDevotee(index, devotee)"
                  class="w-full px-4 py-2 text-left hover:bg-gray-100 focus:bg-gray-100 text-sm"
                >
                  <div class="font-medium">{{ devotee.name }}</div>
                  <div class="text-xs text-gray-500">
                    <span v-if="devotee.nakshathra">{{ devotee.nakshathra.malayalam_name }}</span>
                    <span v-if="devotee.nakshathra && devotee.gothram"> | </span>
                    <span v-if="devotee.gothram">{{ devotee.gothram }}</span>
                  </div>
                </button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">നക്ഷത്രം (Nakshathra)</label>
              <select
                v-model="ben.nakshathra_id"
                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                <option value="">നക്ഷത്രം തിരഞ്ഞെടുക്കുക</option>
                <option v-for="n in nakshathras" :key="n.id" :value="n.id">
                  {{ n.malayalam_name }}
                </option>
              </select>
            </div>
            <Input
              v-model="ben.gothram"
              label="ഗോത്രം (Gothram)"
              placeholder="Gothram"
            />
            <div class="flex items-end">
              <button
                v-if="form.beneficiaries.length > 1"
                type="button"
                @click="removeBeneficiary(index)"
                class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded"
              >
                <TrashIcon class="w-5 h-5" />
              </button>
            </div>
          </div>
        </div>

        <Button
          type="button"
          variant="outline"
          size="sm"
          class="mt-4"
          @click="addBeneficiary"
        >
          <PlusIcon class="w-4 h-4 mr-1" />
          Add Devotee
        </Button>
      </Card>

      <!-- Payment & Contact -->
      <Card title="Payment & Contact">
        <!-- Payment -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <Input
            v-model.number="form.payment_amount"
            label="Payment Amount"
            type="number"
            min="0"
            :max="totalAmount"
          />
          <Select
            v-if="form.payment_amount > 0"
            v-model="form.payment_method"
            label="Payment Method"
            :options="paymentMethods"
          />
          <Select
            v-if="form.payment_amount > 0"
            v-model="form.account_id"
            label="Credit To Account"
            :options="filteredAccounts.map(a => ({ value: a.id, label: `${a.account_name} (₹${parseFloat(a.current_balance).toLocaleString()})` }))"
            required
          />
          <div class="flex items-end">
            <div class="w-full p-3 rounded-lg" :class="balance > 0 ? 'bg-red-50' : 'bg-green-50'">
              <span class="text-sm text-gray-600">Balance: </span>
              <span class="font-bold" :class="balance > 0 ? 'text-red-600' : 'text-green-600'">
                ₹{{ balance.toLocaleString() }}
              </span>
            </div>
          </div>
        </div>

        <!-- Prasadam Option -->
        <div class="mt-6 flex items-center gap-3 p-4 bg-orange-50 rounded-lg">
          <input
            v-model="form.prasadam_required"
            type="checkbox"
            id="prasadam"
            class="w-5 h-5 text-primary-500 border-gray-300 rounded focus:ring-primary-500"
          />
          <label for="prasadam" class="text-sm font-medium text-gray-700">
            Prasadam needs to be sent to devotee
          </label>
        </div>

        <!-- Contact Details -->
        <div class="mt-6 pt-6 border-t">
          <div class="flex items-center justify-between mb-4">
            <h4 class="font-medium text-gray-900">Contact Details</h4>
            <span v-if="contactRequired" class="text-xs text-orange-600">
              Required for {{ contactRequiredReason }}
            </span>
            <span v-else class="text-xs text-gray-500">Optional</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <Input
              v-model="form.contact_name"
              :label="contactRequired ? 'Contact Name *' : 'Contact Name'"
              placeholder="Full name"
              :required="contactRequired"
              :error="errors.contact_name?.[0]"
            />
            <Input
              v-model="form.contact_number"
              :label="contactRequired ? 'Contact Number *' : 'Contact Number'"
              placeholder="Mobile number"
              :required="contactRequired"
              :error="errors.contact_number?.[0]"
            />
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ form.prasadam_required ? 'Delivery Address *' : 'Address' }}
              </label>
              <textarea
                v-model="form.contact_address"
                rows="2"
                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                :placeholder="form.prasadam_required ? 'Full delivery address for prasadam' : 'Address (optional)'"
                :required="form.prasadam_required"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div class="mt-6">
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
      <div class="flex items-center justify-between">
        <Button type="button" variant="ghost" @click="resetForm(false)" class="text-gray-500">
          Clear Form
        </Button>
        <div class="flex items-center gap-4">
          <Button type="button" variant="outline" @click="router.push('/bookings')">
            Cancel
          </Button>
          <Button type="submit" variant="primary" :loading="saving" :disabled="!isValid">
            Create Booking
          </Button>
        </div>
      </div>
    </form>

    <!-- Success Modal -->
    <Modal :show="showSuccessModal" title="Booking Created" @close="viewBooking">
      <div class="text-center py-4">
        <div class="mx-auto flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
          <CheckCircleIcon class="w-10 h-10 text-green-600" />
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Booking Created Successfully!</h3>
        <p v-if="createdBooking" class="text-gray-600 mb-1">
          Booking Number: <span class="font-mono font-medium text-primary-600">{{ createdBooking.booking_number }}</span>
        </p>
        <p v-if="createdBooking" class="text-gray-600">
          Total: <span class="font-medium">{{ createdBooking.total_amount_formatted }}</span>
          <span v-if="createdBooking.balance_amount > 0" class="text-red-600">
            (Balance: {{ createdBooking.balance_amount_formatted }})
          </span>
        </p>
      </div>
      <div class="flex flex-col gap-3 pt-4 border-t">
        <div class="flex justify-center gap-3">
          <Button variant="outline" @click="createAnother(true)" class="flex-1">
            Same Contact
          </Button>
          <Button variant="outline" @click="createAnother(false)" class="flex-1">
            New Booking
          </Button>
        </div>
        <Button variant="primary" @click="viewBooking" class="w-full">
          View Booking
        </Button>
      </div>
    </Modal>
  </div>
</template>
