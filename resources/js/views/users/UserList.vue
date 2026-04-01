<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
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
const authStore = useAuthStore();
const uiStore = useUiStore();

const users = ref([]);
const loading = ref(true);
const search = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const deleteModal = ref(false);
const userToDelete = ref(null);

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'contact_number', label: 'Contact' },
  { key: 'role', label: 'Role' },
  { key: 'is_active', label: 'Status' },
  { key: 'last_login_at', label: 'Last Login' },
  { key: 'actions', label: 'Actions', class: 'text-right' },
];

const fetchUsers = async (page = 1) => {
  loading.value = true;
  try {
    const response = await api.get('/users', {
      params: { page, search: search.value, per_page: 15 },
    });
    users.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch users', 'error');
  } finally {
    loading.value = false;
  }
};

const confirmDelete = (user) => {
  userToDelete.value = user;
  deleteModal.value = true;
};

const deleteUser = async () => {
  try {
    await api.delete(`/users/${userToDelete.value.id}`);
    uiStore.showToast('User deactivated successfully', 'success');
    deleteModal.value = false;
    fetchUsers(meta.value.current_page);
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Failed to deactivate user', 'error');
  }
};

const formatDate = (date) => {
  if (!date) return 'Never';
  return new Date(date).toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

watch(search, () => {
  fetchUsers(1);
});

onMounted(fetchUsers);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Users</h1>
        <p class="text-gray-500">Manage users and their access</p>
      </div>
      <Button
        v-if="authStore.hasPermission('users.create')"
        variant="primary"
        @click="router.push('/users/create')"
      >
        <PlusIcon class="w-5 h-5 mr-2" />
        Add User
      </Button>
    </div>

    <Card :padding="false">
      <template #header>
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold">All Users</h3>
          <div class="w-64">
            <Input v-model="search" placeholder="Search users..." />
          </div>
        </div>
      </template>

      <Table :columns="columns" :data="users" :loading="loading">
        <template #role="{ row }">
          {{ row.role?.role_name || '-' }}
        </template>
        <template #is_active="{ value }">
          <span
            class="px-2 py-1 text-xs font-medium rounded-full"
            :class="value ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
          >
            {{ value ? 'Active' : 'Inactive' }}
          </span>
        </template>
        <template #last_login_at="{ value }">
          <span class="text-gray-500 text-sm">{{ formatDate(value) }}</span>
        </template>
        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-2">
            <Button
              v-if="authStore.hasPermission('users.update')"
              variant="ghost"
              size="sm"
              @click="router.push(`/users/${row.id}/edit`)"
            >
              <PencilIcon class="w-4 h-4" />
            </Button>
            <Button
              v-if="authStore.hasPermission('users.delete') && row.id !== authStore.user?.id"
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
        v-if="meta.total > 0"
        :current-page="meta.current_page"
        :last-page="meta.last_page"
        :total="meta.total"
        :per-page="meta.per_page"
        @page-change="fetchUsers"
      />
    </Card>

    <!-- Delete Confirmation Modal -->
    <Modal :show="deleteModal" title="Deactivate User" @close="deleteModal = false">
      <p class="text-gray-600">
        Are you sure you want to deactivate <strong>{{ userToDelete?.name }}</strong>?
        They will no longer be able to access the system.
      </p>
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="deleteModal = false">Cancel</Button>
          <Button variant="danger" @click="deleteUser">Deactivate</Button>
        </div>
      </template>
    </Modal>
  </div>
</template>
