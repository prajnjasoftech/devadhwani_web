<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Table from '@/components/ui/Table.vue';
import { PlusIcon, ArrowLeftIcon, PencilIcon, NoSymbolIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const uiStore = useUiStore();

const loading = ref(true);
const vendors = ref([]);
const meta = ref({});
const search = ref('');

const columns = [
  { key: 'name', label: 'Vendor Name' },
  { key: 'contact_person', label: 'Contact Person' },
  { key: 'contact_number', label: 'Phone' },
  { key: 'purchases_count', label: 'Purchases' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: '' },
];

const fetchVendors = async (page = 1) => {
  loading.value = true;
  try {
    const response = await api.get('/vendors', {
      params: { page, search: search.value || undefined },
    });
    vendors.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch vendors', 'error');
  } finally {
    loading.value = false;
  }
};

const toggleStatus = async (vendor) => {
  try {
    await api.put(`/vendors/${vendor.id}`, { is_active: !vendor.is_active });
    vendor.is_active = !vendor.is_active;
    uiStore.showToast(`Vendor ${vendor.is_active ? 'activated' : 'deactivated'}`, 'success');
  } catch (error) {
    uiStore.showToast('Failed to update status', 'error');
  }
};

watch(search, () => {
  fetchVendors(1);
});

onMounted(() => {
  fetchVendors();
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <Button variant="ghost" @click="router.push('/purchases')">
          <ArrowLeftIcon class="w-5 h-5" />
        </Button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Vendor Management</h1>
          <p class="text-gray-500">Manage your temple's vendors and suppliers</p>
        </div>
      </div>
      <Button variant="primary" @click="router.push('/purchases/vendors/new')">
        <PlusIcon class="w-5 h-5 mr-1" />
        Add Vendor
      </Button>
    </div>

    <Card>
      <div class="mb-4">
        <input
          v-model="search"
          type="text"
          placeholder="Search vendors..."
          class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
        />
      </div>

      <Table :columns="columns" :data="vendors" :loading="loading">
        <template #name="{ row }">
          <div>
            <div class="font-medium">{{ row.name }}</div>
            <div v-if="row.description" class="text-xs text-gray-500">{{ row.description }}</div>
            <div v-else-if="row.email" class="text-xs text-gray-500">{{ row.email }}</div>
          </div>
        </template>
        <template #contact_person="{ row }">
          {{ row.contact_person || '-' }}
        </template>
        <template #contact_number="{ row }">
          {{ row.contact_number || '-' }}
        </template>
        <template #purchases_count="{ row }">
          <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
            {{ row.purchases_count || 0 }} purchases
          </span>
        </template>
        <template #is_active="{ row }">
          <span :class="['px-2 py-1 text-xs font-medium rounded-full', row.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
            {{ row.is_active ? 'Active' : 'Inactive' }}
          </span>
        </template>
        <template #actions="{ row }">
          <div class="flex items-center gap-1">
            <button
              @click="router.push(`/purchases/vendors/${row.id}/edit`)"
              class="p-2 text-gray-500 hover:text-primary-600 hover:bg-gray-100 rounded-lg"
              title="Edit"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              @click="toggleStatus(row)"
              :class="['p-2 rounded-lg', row.is_active ? 'text-gray-500 hover:text-red-600 hover:bg-red-50' : 'text-gray-500 hover:text-green-600 hover:bg-green-50']"
              :title="row.is_active ? 'Deactivate' : 'Activate'"
            >
              <NoSymbolIcon v-if="row.is_active" class="w-4 h-4" />
              <CheckCircleIcon v-else class="w-4 h-4" />
            </button>
          </div>
        </template>
      </Table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-4 pt-4 border-t">
        <Button
          v-for="page in meta.last_page"
          :key="page"
          :variant="page === meta.current_page ? 'primary' : 'outline'"
          size="sm"
          @click="fetchVendors(page)"
        >
          {{ page }}
        </Button>
      </div>
    </Card>
  </div>
</template>
