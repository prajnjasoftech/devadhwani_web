<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Table from '@/components/ui/Table.vue';
import {
  PlusIcon,
  FunnelIcon,
  ArrowLeftIcon,
  PencilIcon,
  ArchiveBoxIcon,
  CubeIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const loading = ref(true);
const assets = ref([]);
const stats = ref(null);
const meta = ref({});
const search = ref('');
const typeFilter = ref('');
const acquisitionFilter = ref('');
const conditionFilter = ref('');
const showFilters = ref(false);

// Dropdown data
const assetTypes = ref([]);

const columns = [
  { key: 'asset_number', label: 'Asset #' },
  { key: 'name', label: 'Name' },
  { key: 'asset_type', label: 'Type' },
  { key: 'quantity', label: 'Quantity' },
  { key: 'estimated_value', label: 'Value' },
  { key: 'acquisition_type', label: 'Source' },
  { key: 'condition', label: 'Condition' },
  { key: 'location', label: 'Location' },
  { key: 'actions', label: '' },
];

const fetchAssets = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      search: search.value || undefined,
      asset_type_id: typeFilter.value || undefined,
      acquisition_type: acquisitionFilter.value || undefined,
      condition: conditionFilter.value || undefined,
    };
    const response = await api.get('/assets', { params });
    assets.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch assets', 'error');
  } finally {
    loading.value = false;
  }
};

const fetchStats = async () => {
  try {
    const response = await api.get('/assets/stats');
    stats.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch stats:', error);
  }
};

const fetchDropdowns = async () => {
  try {
    const response = await api.get('/asset-types/all');
    assetTypes.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch asset types:', error);
  }
};

const formatAmount = (amount) => {
  return amount ? new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount) : '-';
};

const getConditionClass = (condition) => {
  switch (condition) {
    case 'excellent': return 'bg-green-100 text-green-800';
    case 'good': return 'bg-blue-100 text-blue-800';
    case 'fair': return 'bg-yellow-100 text-yellow-800';
    case 'poor': return 'bg-red-100 text-red-800';
    default: return 'bg-gray-100 text-gray-800';
  }
};

const getAcquisitionLabel = (type) => {
  switch (type) {
    case 'existing': return 'Existing';
    case 'donation': return 'Donation';
    case 'purchase': return 'Purchase';
    default: return type;
  }
};

const getAcquisitionClass = (type) => {
  switch (type) {
    case 'existing': return 'bg-gray-100 text-gray-700';
    case 'donation': return 'bg-purple-100 text-purple-700';
    case 'purchase': return 'bg-blue-100 text-blue-700';
    default: return 'bg-gray-100 text-gray-700';
  }
};

const clearFilters = () => {
  typeFilter.value = '';
  acquisitionFilter.value = '';
  conditionFilter.value = '';
  search.value = '';
};

watch([search, typeFilter, acquisitionFilter, conditionFilter], () => {
  fetchAssets(1);
});

onMounted(() => {
  fetchAssets();
  fetchStats();
  fetchDropdowns();
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <Button variant="ghost" @click="router.push('/donations')">
          <ArrowLeftIcon class="w-5 h-5" />
        </Button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Asset Register</h1>
          <p class="text-gray-500">Track all temple assets (existing & received)</p>
        </div>
      </div>
      <Button
        v-if="authStore.hasPermission('donations.create')"
        variant="primary"
        @click="router.push('/donations/assets/new')"
      >
        <PlusIcon class="w-5 h-5 mr-1" />
        Add Asset
      </Button>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <Card class="bg-primary-50 border-primary-200">
        <div class="flex items-center gap-4">
          <div class="p-3 bg-primary-100 rounded-full">
            <CubeIcon class="w-6 h-6 text-primary-600" />
          </div>
          <div>
            <p class="text-sm text-primary-600">Total Assets</p>
            <p class="text-2xl font-bold text-primary-700">{{ stats.total_assets }}</p>
          </div>
        </div>
      </Card>
      <Card class="bg-green-50 border-green-200">
        <div class="flex items-center gap-4">
          <div class="p-3 bg-green-100 rounded-full">
            <ArchiveBoxIcon class="w-6 h-6 text-green-600" />
          </div>
          <div>
            <p class="text-sm text-green-600">Total Value</p>
            <p class="text-2xl font-bold text-green-700">{{ formatAmount(stats.total_value) }}</p>
          </div>
        </div>
      </Card>
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">Existing</p>
          <p class="text-2xl font-bold text-gray-700">{{ stats.by_acquisition?.existing || 0 }}</p>
        </div>
      </Card>
      <Card>
        <div class="text-center">
          <p class="text-sm text-gray-500">From Donations</p>
          <p class="text-2xl font-bold text-purple-600">{{ stats.by_acquisition?.donation || 0 }}</p>
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
            placeholder="Search assets..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </div>
        <Button variant="ghost" @click="showFilters = !showFilters">
          <FunnelIcon class="w-5 h-5 mr-1" />
          Filters
        </Button>
      </div>

      <div v-if="showFilters" class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t">
        <select v-model="typeFilter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
          <option value="">All Types</option>
          <option v-for="t in assetTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <select v-model="acquisitionFilter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
          <option value="">All Sources</option>
          <option value="existing">Existing</option>
          <option value="donation">Donation</option>
          <option value="purchase">Purchase</option>
        </select>
        <select v-model="conditionFilter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
          <option value="">All Conditions</option>
          <option value="excellent">Excellent</option>
          <option value="good">Good</option>
          <option value="fair">Fair</option>
          <option value="poor">Poor</option>
        </select>
        <Button variant="ghost" size="sm" @click="clearFilters">Clear</Button>
      </div>
    </Card>

    <!-- Table -->
    <Card>
      <Table :columns="columns" :data="assets" :loading="loading">
        <template #name="{ row }">
          <div>
            <div class="font-medium text-gray-900">{{ row.name }}</div>
            <div v-if="row.description" class="text-xs text-gray-500 truncate max-w-xs">{{ row.description }}</div>
          </div>
        </template>
        <template #asset_type="{ row }">
          <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700">
            {{ row.asset_type?.name || '-' }}
          </span>
        </template>
        <template #quantity="{ row }">
          {{ row.quantity }}{{ row.asset_type?.unit ? ` ${row.asset_type.unit}` : '' }}
        </template>
        <template #estimated_value="{ row }">
          {{ formatAmount(row.estimated_value) }}
        </template>
        <template #acquisition_type="{ row }">
          <span :class="['px-2 py-1 text-xs font-medium rounded-full', getAcquisitionClass(row.acquisition_type)]">
            {{ getAcquisitionLabel(row.acquisition_type) }}
          </span>
        </template>
        <template #condition="{ row }">
          <span :class="['px-2 py-1 text-xs font-medium rounded-full capitalize', getConditionClass(row.condition)]">
            {{ row.condition }}
          </span>
        </template>
        <template #location="{ row }">
          <span class="text-gray-600">{{ row.location || '-' }}</span>
        </template>
        <template #actions="{ row }">
          <button
            v-if="authStore.hasPermission('donations.update')"
            @click="router.push(`/donations/assets/${row.id}/edit`)"
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
          @click="fetchAssets(page)"
        >
          {{ page }}
        </Button>
      </div>
    </Card>
  </div>
</template>
