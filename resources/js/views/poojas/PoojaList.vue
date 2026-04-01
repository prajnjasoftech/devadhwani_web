<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Table from '@/components/ui/Table.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const poojas = ref([]);
const deities = ref([]);
const loading = ref(true);
const search = ref('');
const deityFilter = ref('');
const frequencyFilter = ref('');
const currentPage = ref(1);
const meta = ref({});

const deleteModal = ref(false);
const poojaToDelete = ref(null);
const deleting = ref(false);

const columns = [
  { key: 'name', label: 'Pooja Name' },
  { key: 'deity', label: 'Deity' },
  { key: 'frequency', label: 'Frequency' },
  { key: 'next_pooja_date', label: 'Next Date' },
  { key: 'amount', label: 'Amount' },
  { key: 'devotee_required', label: 'Devotee Required' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: 'Actions', class: 'text-right' },
];

const frequencies = [
  { value: '', label: 'All Frequencies' },
  { value: 'once', label: 'One Time' },
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
];

const fetchDeities = async () => {
  try {
    const response = await api.get('/deities/all');
    deities.value = [{ id: '', name: 'All Deities' }, ...response.data.data];
  } catch (error) {
    console.error('Failed to fetch deities:', error);
  }
};

const fetchPoojas = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      search: search.value || undefined,
      deity_id: deityFilter.value || undefined,
      frequency: frequencyFilter.value || undefined,
    };

    const response = await api.get('/poojas', { params });
    poojas.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch poojas', 'error');
  } finally {
    loading.value = false;
  }
};

const confirmDelete = (pooja) => {
  poojaToDelete.value = pooja;
  deleteModal.value = true;
};

const deletePooja = async () => {
  deleting.value = true;
  try {
    await api.delete(`/poojas/${poojaToDelete.value.id}`);
    uiStore.showToast('Pooja deleted successfully', 'success');
    deleteModal.value = false;
    fetchPoojas();
  } catch (error) {
    uiStore.showToast('Failed to delete pooja', 'error');
  } finally {
    deleting.value = false;
  }
};

watch([search, deityFilter, frequencyFilter], () => {
  currentPage.value = 1;
  fetchPoojas();
});

watch(currentPage, fetchPoojas);

onMounted(() => {
  fetchDeities();
  fetchPoojas();
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Poojas</h1>
        <p class="text-gray-500">Manage temple poojas and rituals</p>
      </div>
      <Button
        v-if="authStore.hasPermission('poojas.create')"
        @click="router.push('/poojas/create')"
      >
        <PlusIcon class="w-5 h-5 mr-2" />
        Add Pooja
      </Button>
    </div>

    <Card>
      <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <input
          v-model="search"
          type="text"
          placeholder="Search poojas..."
          class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        />
        <select
          v-model="deityFilter"
          class="px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <option v-for="deity in deities" :key="deity.id" :value="deity.id">
            {{ deity.name }}
          </option>
        </select>
        <select
          v-model="frequencyFilter"
          class="px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <option v-for="freq in frequencies" :key="freq.value" :value="freq.value">
            {{ freq.label }}
          </option>
        </select>
      </div>

      <Table :columns="columns" :data="poojas" :loading="loading">
        <template #name="{ row }">
          <span class="font-medium text-gray-900">{{ row.name }}</span>
        </template>

        <template #deity="{ row }">
          {{ row.deity?.name || '-' }}
        </template>

        <template #frequency="{ row }">
          <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">
            {{ row.frequency_label }}
          </span>
        </template>

        <template #next_pooja_date="{ row }">
          {{ row.next_pooja_date_formatted || '-' }}
        </template>

        <template #amount="{ row }">
          <span class="font-medium">{{ row.amount_formatted }}</span>
        </template>

        <template #devotee_required="{ row }">
          <span
            :class="[
              'px-2 py-1 text-xs font-medium rounded-full',
              row.devotee_required
                ? 'bg-purple-100 text-purple-700'
                : 'bg-gray-100 text-gray-700',
            ]"
          >
            {{ row.devotee_required ? 'Yes' : 'No' }}
          </span>
        </template>

        <template #is_active="{ row }">
          <span
            :class="[
              'px-2 py-1 text-xs font-medium rounded-full',
              row.is_active
                ? 'bg-green-100 text-green-700'
                : 'bg-gray-100 text-gray-700',
            ]"
          >
            {{ row.is_active ? 'Active' : 'Inactive' }}
          </span>
        </template>

        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-2">
            <Button
              v-if="authStore.hasPermission('poojas.update')"
              variant="ghost"
              size="sm"
              @click="router.push(`/poojas/${row.id}/edit`)"
            >
              <PencilIcon class="w-4 h-4" />
            </Button>
            <Button
              v-if="authStore.hasPermission('poojas.delete')"
              variant="ghost"
              size="sm"
              @click="confirmDelete(row)"
            >
              <TrashIcon class="w-4 h-4 text-red-500" />
            </Button>
          </div>
        </template>
      </Table>

      <Pagination
        v-if="meta.last_page > 1"
        :current-page="currentPage"
        :last-page="meta.last_page"
        :total="meta.total"
        @update:current-page="currentPage = $event"
        class="mt-6"
      />
    </Card>

    <!-- Delete Confirmation Modal -->
    <Modal :show="deleteModal" @close="deleteModal = false" title="Delete Pooja">
      <p class="text-gray-600">
        Are you sure you want to delete <strong>{{ poojaToDelete?.name }}</strong>? This action cannot be undone.
      </p>
      <div class="mt-6 flex justify-end gap-3">
        <Button variant="outline" @click="deleteModal = false">Cancel</Button>
        <Button variant="danger" @click="deletePooja" :loading="deleting">Delete</Button>
      </div>
    </Modal>
  </div>
</template>
