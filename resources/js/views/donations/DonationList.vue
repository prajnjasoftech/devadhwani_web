<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Table from '@/components/ui/Table.vue';
import { PlusIcon, FunnelIcon, PencilIcon, CurrencyRupeeIcon, GiftIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const uiStore = useUiStore();

const loading = ref(true);
const donations = ref([]);
const stats = ref(null);
const meta = ref({});
const search = ref('');
const headFilter = ref('');
const typeFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const showFilters = ref(false);

// Dropdown data
const donationHeads = ref([]);

const columns = [
  { key: 'donation_number', label: 'Donation #' },
  { key: 'donation_date', label: 'Date' },
  { key: 'donor_name', label: 'Donor' },
  { key: 'donation_head', label: 'Head' },
  { key: 'donation_type', label: 'Type' },
  { key: 'value', label: 'Value' },
  { key: 'actions', label: '' },
];

const fetchDonations = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      search: search.value || undefined,
      donation_head_id: headFilter.value || undefined,
      donation_type: typeFilter.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    };
    const response = await api.get('/donations', { params });
    donations.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch donations', 'error');
  } finally {
    loading.value = false;
  }
};

const fetchStats = async () => {
  try {
    const response = await api.get('/donations/stats');
    stats.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch stats:', error);
  }
};

const fetchDropdowns = async () => {
  try {
    const response = await api.get('/donation-heads/all');
    donationHeads.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch donation heads:', error);
  }
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatAmount = (amount) => {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};

const getDisplayValue = (row) => {
  if (row.donation_type === 'financial') {
    return formatAmount(row.amount);
  }
  let value = `${row.quantity}`;
  if (row.asset_type?.unit) {
    value += ` ${row.asset_type.unit}`;
  }
  value += ` - ${row.asset_description}`;
  if (row.estimated_value) {
    value += ` (~${formatAmount(row.estimated_value)})`;
  }
  return value;
};

const clearFilters = () => {
  headFilter.value = '';
  typeFilter.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  search.value = '';
};

watch([search, headFilter, typeFilter, dateFrom, dateTo], () => {
  fetchDonations(1);
});

onMounted(() => {
  fetchDonations();
  fetchStats();
  fetchDropdowns();
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Donation Management</h1>
        <p class="text-gray-500">Track financial and asset donations</p>
      </div>
      <div class="flex gap-3">
        <Button variant="outline" @click="router.push('/donations/assets')">
          Asset Register
        </Button>
        <Button variant="outline" @click="router.push('/donations/heads')">
          Donation Heads
        </Button>
        <Button variant="outline" @click="router.push('/donations/asset-types')">
          Asset Types
        </Button>
        <Button variant="primary" @click="router.push('/donations/new')">
          <PlusIcon class="w-5 h-5 mr-1" />
          New Donation
        </Button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <Card class="bg-green-50 border-green-200">
        <div class="text-center">
          <p class="text-sm text-green-600">Today's Collection</p>
          <p class="text-2xl font-bold text-green-700">{{ formatAmount(stats.today.financial) }}</p>
          <p class="text-xs text-green-600">{{ stats.today.count }} donations</p>
        </div>
      </Card>
      <Card class="bg-blue-50 border-blue-200">
        <div class="text-center">
          <p class="text-sm text-blue-600">This Month (Financial)</p>
          <p class="text-2xl font-bold text-blue-700">{{ formatAmount(stats.month.financial) }}</p>
          <p class="text-xs text-blue-600">{{ stats.month.financial_count }} donations</p>
        </div>
      </Card>
      <Card class="bg-purple-50 border-purple-200">
        <div class="text-center">
          <p class="text-sm text-purple-600">Asset Donations</p>
          <p class="text-2xl font-bold text-purple-700">{{ stats.month.asset_count }}</p>
          <p class="text-xs text-purple-600">this month</p>
        </div>
      </Card>
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">Total This Month</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.month.total_count }}</p>
          <p class="text-xs text-gray-500">all donations</p>
        </div>
      </Card>
    </div>

    <!-- Filters -->
    <Card class="mb-6">
      <div class="flex items-center justify-between mb-4">
        <div class="flex-1 max-w-md">
          <input
            v-model="search"
            type="text"
            placeholder="Search by donor name, contact, donation #..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </div>
        <Button variant="ghost" @click="showFilters = !showFilters">
          <FunnelIcon class="w-5 h-5 mr-1" />
          Filters
        </Button>
      </div>

      <div v-if="showFilters" class="grid grid-cols-2 md:grid-cols-5 gap-4 pt-4 border-t">
        <select v-model="headFilter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
          <option value="">All Heads</option>
          <option v-for="h in donationHeads" :key="h.id" :value="h.id">{{ h.name }}</option>
        </select>
        <select v-model="typeFilter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
          <option value="">All Types</option>
          <option value="financial">Financial</option>
          <option value="asset">Asset</option>
        </select>
        <input v-model="dateFrom" type="date" class="px-3 py-2 border border-gray-300 rounded-md text-sm" />
        <input v-model="dateTo" type="date" class="px-3 py-2 border border-gray-300 rounded-md text-sm" />
        <Button variant="ghost" size="sm" @click="clearFilters">Clear</Button>
      </div>
    </Card>

    <!-- Table -->
    <Card>
      <Table :columns="columns" :data="donations" :loading="loading">
        <template #donation_date="{ row }">
          {{ formatDate(row.donation_date) }}
        </template>
        <template #donor_name="{ row }">
          <div>
            <div class="font-medium text-gray-900">{{ row.donor_name }}</div>
            <div v-if="row.donor_contact" class="text-xs text-gray-500">{{ row.donor_contact }}</div>
          </div>
        </template>
        <template #donation_head="{ row }">
          <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700">
            {{ row.donation_head?.name || '-' }}
          </span>
        </template>
        <template #donation_type="{ row }">
          <span :class="[
            'px-2 py-1 text-xs font-medium rounded-full flex items-center gap-1 w-fit',
            row.donation_type === 'financial' ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700'
          ]">
            <CurrencyRupeeIcon v-if="row.donation_type === 'financial'" class="w-3 h-3" />
            <GiftIcon v-else class="w-3 h-3" />
            {{ row.donation_type === 'financial' ? 'Financial' : 'Asset' }}
          </span>
        </template>
        <template #value="{ row }">
          <div>
            <div class="font-medium">{{ getDisplayValue(row) }}</div>
            <div v-if="row.donation_type === 'financial' && row.account" class="text-xs text-gray-500">
              via {{ row.account.account_name }}
            </div>
          </div>
        </template>
        <template #actions="{ row }">
          <button
            @click="router.push(`/donations/${row.id}/edit`)"
            class="p-2 text-gray-500 hover:text-primary-600 hover:bg-gray-100 rounded-lg"
            title="Edit"
          >
            <PencilIcon class="w-4 h-4" />
          </button>
        </template>
      </Table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-4 pt-4 border-t">
        <Button
          v-for="page in meta.last_page"
          :key="page"
          :variant="page === meta.current_page ? 'primary' : 'outline'"
          size="sm"
          @click="fetchDonations(page)"
        >
          {{ page }}
        </Button>
      </div>
    </Card>
  </div>
</template>
