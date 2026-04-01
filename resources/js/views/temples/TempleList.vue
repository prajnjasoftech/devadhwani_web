<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Table from '@/components/ui/Table.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const uiStore = useUiStore();

const temples = ref([]);
const loading = ref(true);
const search = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const deleteModal = ref(false);
const templeToDelete = ref(null);
const credentialsModal = ref(false);
const newTempleCredentials = ref(null);

const columns = [
  { key: 'temple_code', label: 'Code' },
  { key: 'temple_name', label: 'Temple Name' },
  { key: 'contact_person_name', label: 'Contact Person' },
  { key: 'contact_number', label: 'Contact' },
  { key: 'district', label: 'District' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Actions', class: 'text-right' },
];

const fetchTemples = async (page = 1) => {
  loading.value = true;
  try {
    const response = await api.get('/temples', {
      params: { page, search: search.value, per_page: 15 },
    });
    temples.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch temples', 'error');
  } finally {
    loading.value = false;
  }
};

const confirmDelete = (temple) => {
  templeToDelete.value = temple;
  deleteModal.value = true;
};

const deleteTemple = async () => {
  try {
    await api.delete(`/temples/${templeToDelete.value.id}`);
    uiStore.showToast('Temple deleted successfully', 'success');
    deleteModal.value = false;
    fetchTemples(meta.value.current_page);
  } catch (error) {
    uiStore.showToast('Failed to delete temple', 'error');
  }
};

const getStatusColor = (status) => {
  const colors = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    suspended: 'bg-red-100 text-red-800',
  };
  return colors[status] || 'bg-gray-100 text-gray-800';
};

watch(search, () => {
  fetchTemples(1);
});

onMounted(fetchTemples);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Temples</h1>
        <p class="text-gray-500">Manage all registered temples</p>
      </div>
      <Button variant="primary" @click="router.push('/temples/create')">
        <PlusIcon class="w-5 h-5 mr-2" />
        Add Temple
      </Button>
    </div>

    <Card :padding="false">
      <template #header>
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold">All Temples</h3>
          <div class="w-64">
            <Input v-model="search" placeholder="Search temples..." />
          </div>
        </div>
      </template>

      <Table :columns="columns" :data="temples" :loading="loading">
        <template #status="{ value }">
          <span class="px-2 py-1 text-xs font-medium rounded-full" :class="getStatusColor(value)">
            {{ value }}
          </span>
        </template>
        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-2">
            <Button variant="ghost" size="sm" @click="router.push(`/temples/${row.id}/edit`)">
              <PencilIcon class="w-4 h-4" />
            </Button>
            <Button variant="ghost" size="sm" @click="confirmDelete(row)">
              <TrashIcon class="w-4 h-4 text-red-500" />
            </Button>
          </div>
        </template>
      </Table>

      <Pagination
        v-if="meta.total > 0"
        :current-page="meta.current_page"
        :last-page="meta.last_page"
        :total="meta.total"
        :per-page="meta.per_page"
        @page-change="fetchTemples"
      />
    </Card>

    <!-- Delete Confirmation Modal -->
    <Modal :show="deleteModal" title="Delete Temple" @close="deleteModal = false">
      <p class="text-gray-600">
        Are you sure you want to delete <strong>{{ templeToDelete?.temple_name }}</strong>?
        This action cannot be undone and will also delete all associated users and data.
      </p>
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="deleteModal = false">Cancel</Button>
          <Button variant="danger" @click="deleteTemple">Delete</Button>
        </div>
      </template>
    </Modal>
  </div>
</template>
