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

const deities = ref([]);
const loading = ref(true);
const search = ref('');
const deityType = ref('');
const currentPage = ref(1);
const meta = ref({});

const deleteModal = ref(false);
const deityToDelete = ref(null);
const deleting = ref(false);

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'sanskrit_name', label: 'Sanskrit Name' },
  { key: 'deity_type_label', label: 'Type' },
  { key: 'display_order', label: 'Order' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: 'Actions' },
];

const deityTypes = [
  { value: '', label: 'All Types' },
  { value: 'main', label: 'Main Deity' },
  { value: 'sub', label: 'Sub Deity' },
  { value: 'upadevata', label: 'Upadevata' },
];

const fetchDeities = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      search: search.value || undefined,
      deity_type: deityType.value || undefined,
    };

    const response = await api.get('/deities', { params });
    deities.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch deities', 'error');
  } finally {
    loading.value = false;
  }
};

const confirmDelete = (deity) => {
  deityToDelete.value = deity;
  deleteModal.value = true;
};

const deleteDeity = async () => {
  deleting.value = true;
  try {
    await api.delete(`/deities/${deityToDelete.value.id}`);
    uiStore.showToast('Deity deleted successfully', 'success');
    deleteModal.value = false;
    fetchDeities();
  } catch (error) {
    uiStore.showToast('Failed to delete deity', 'error');
  } finally {
    deleting.value = false;
  }
};

watch([search, deityType], () => {
  currentPage.value = 1;
  fetchDeities();
});

watch(currentPage, fetchDeities);

onMounted(fetchDeities);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Deities</h1>
        <p class="text-gray-500">Manage temple deities</p>
      </div>
      <Button
        v-if="authStore.hasPermission('deities.create')"
        @click="router.push('/deities/create')"
      >
        <PlusIcon class="w-5 h-5 mr-2" />
        Add Deity
      </Button>
    </div>

    <Card>
      <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <input
          v-model="search"
          type="text"
          placeholder="Search deities..."
          class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        />
        <select
          v-model="deityType"
          class="px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <option v-for="type in deityTypes" :key="type.value" :value="type.value">
            {{ type.label }}
          </option>
        </select>
      </div>

      <Table :columns="columns" :data="deities" :loading="loading">
        <template #name="{ row }">
          <div class="flex items-center">
            <img
              v-if="row.image_url"
              :src="row.image_url"
              :alt="row.name"
              class="w-10 h-10 rounded-full object-cover mr-3"
            />
            <div
              v-else
              class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center mr-3"
            >
              <span class="text-primary-600 font-medium">{{ row.name.charAt(0) }}</span>
            </div>
            <span class="font-medium text-gray-900">{{ row.name }}</span>
          </div>
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
              v-if="authStore.hasPermission('deities.update')"
              variant="ghost"
              size="sm"
              @click="router.push(`/deities/${row.id}/edit`)"
            >
              <PencilIcon class="w-4 h-4" />
            </Button>
            <Button
              v-if="authStore.hasPermission('deities.delete')"
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
    <Modal :show="deleteModal" @close="deleteModal = false" title="Delete Deity">
      <p class="text-gray-600">
        Are you sure you want to delete <strong>{{ deityToDelete?.name }}</strong>? This action cannot be undone.
      </p>
      <div class="mt-6 flex justify-end gap-3">
        <Button variant="outline" @click="deleteModal = false">Cancel</Button>
        <Button variant="danger" @click="deleteDeity" :loading="deleting">Delete</Button>
      </div>
    </Modal>
  </div>
</template>
