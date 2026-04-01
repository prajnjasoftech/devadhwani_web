<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Table from '@/components/ui/Table.vue';
import Modal from '@/components/ui/Modal.vue';
import Input from '@/components/ui/Input.vue';
import {
  PlusIcon,
  ArrowLeftIcon,
  PencilIcon,
  NoSymbolIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const loading = ref(true);
const heads = ref([]);
const meta = ref({});
const search = ref('');

// Modal
const showModal = ref(false);
const isEditMode = ref(false);
const editingHead = ref(null);
const form = ref({ name: '', description: '' });
const saving = ref(false);
const errors = ref({});

const columns = [
  { key: 'name', label: 'Head Name' },
  { key: 'description', label: 'Description' },
  { key: 'donations_count', label: 'Donations' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: '' },
];

const fetchHeads = async (page = 1) => {
  loading.value = true;
  try {
    const params = { page, search: search.value || undefined };
    const response = await api.get('/donation-heads', { params });
    heads.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch donation heads', 'error');
  } finally {
    loading.value = false;
  }
};

const openAddModal = () => {
  isEditMode.value = false;
  editingHead.value = null;
  form.value = { name: '', description: '' };
  errors.value = {};
  showModal.value = true;
};

const openEditModal = (head) => {
  isEditMode.value = true;
  editingHead.value = head;
  form.value = { name: head.name, description: head.description || '' };
  errors.value = {};
  showModal.value = true;
};

const saveHead = async () => {
  errors.value = {};
  saving.value = true;
  try {
    if (isEditMode.value) {
      await api.put(`/donation-heads/${editingHead.value.id}`, form.value);
      uiStore.showToast('Donation head updated', 'success');
    } else {
      await api.post('/donation-heads', form.value);
      uiStore.showToast('Donation head created', 'success');
    }
    showModal.value = false;
    fetchHeads(meta.value.current_page || 1);
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save donation head', 'error');
    }
  } finally {
    saving.value = false;
  }
};

const toggleStatus = async (head) => {
  try {
    await api.put(`/donation-heads/${head.id}`, { is_active: !head.is_active });
    head.is_active = !head.is_active;
    uiStore.showToast(`Donation head ${head.is_active ? 'activated' : 'deactivated'}`, 'success');
  } catch (error) {
    uiStore.showToast('Failed to update status', 'error');
  }
};

watch(search, () => fetchHeads(1));
onMounted(() => fetchHeads());
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <Button variant="ghost" @click="router.push('/donations')">
          <ArrowLeftIcon class="w-5 h-5" />
        </Button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Donation Heads</h1>
          <p class="text-gray-500">Manage donation categories</p>
        </div>
      </div>
      <Button
        v-if="authStore.hasPermission('donations.create')"
        variant="primary"
        @click="openAddModal"
      >
        <PlusIcon class="w-5 h-5 mr-1" />
        New Head
      </Button>
    </div>

    <Card>
      <div class="mb-4">
        <input
          v-model="search"
          type="text"
          placeholder="Search donation heads..."
          class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
        />
      </div>

      <Table :columns="columns" :data="heads" :loading="loading">
        <template #name="{ row }">
          <span class="font-medium text-gray-900">{{ row.name }}</span>
        </template>
        <template #description="{ row }">
          <span class="text-gray-500">{{ row.description || '-' }}</span>
        </template>
        <template #donations_count="{ row }">
          <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
            {{ row.donations_count || 0 }} donations
          </span>
        </template>
        <template #is_active="{ row }">
          <span :class="['px-2 py-1 text-xs font-medium rounded-full', row.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
            {{ row.is_active ? 'Active' : 'Inactive' }}
          </span>
        </template>
        <template #actions="{ row }">
          <div class="flex items-center gap-1">
            <button
              v-if="authStore.hasPermission('donations.update')"
              @click="openEditModal(row)"
              class="p-2 text-gray-500 hover:text-primary-600 hover:bg-gray-100 rounded-lg"
              title="Edit"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              v-if="authStore.hasPermission('donations.update')"
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

      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-4 pt-4 border-t">
        <Button
          v-for="page in meta.last_page"
          :key="page"
          :variant="page === meta.current_page ? 'primary' : 'outline'"
          size="sm"
          @click="fetchHeads(page)"
        >
          {{ page }}
        </Button>
      </div>
    </Card>

    <!-- Add/Edit Modal -->
    <Modal :show="showModal" :title="isEditMode ? 'Edit Donation Head' : 'New Donation Head'" @close="showModal = false">
      <div class="space-y-4">
        <Input
          v-model="form.name"
          label="Head Name"
          placeholder="e.g., General Donation, Annadanam"
          required
          :error="errors.name?.[0]"
        />
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea
            v-model="form.description"
            rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Brief description..."
          ></textarea>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <Button variant="outline" @click="showModal = false">Cancel</Button>
          <Button variant="primary" :loading="saving" @click="saveHead">
            {{ isEditMode ? 'Update' : 'Create' }}
          </Button>
        </div>
      </div>
    </Modal>
  </div>
</template>
