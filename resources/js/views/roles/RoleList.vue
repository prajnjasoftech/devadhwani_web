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

const roles = ref([]);
const loading = ref(true);
const search = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const deleteModal = ref(false);
const roleToDelete = ref(null);

const columns = [
  { key: 'role_name', label: 'Role Name' },
  { key: 'description', label: 'Description' },
  { key: 'users_count', label: 'Users' },
  { key: 'is_system_role', label: 'Type' },
  { key: 'actions', label: 'Actions', class: 'text-right' },
];

const fetchRoles = async (page = 1) => {
  loading.value = true;
  try {
    const response = await api.get('/roles', {
      params: { page, search: search.value, per_page: 15 },
    });
    roles.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch roles', 'error');
  } finally {
    loading.value = false;
  }
};

const confirmDelete = (role) => {
  roleToDelete.value = role;
  deleteModal.value = true;
};

const deleteRole = async () => {
  try {
    await api.delete(`/roles/${roleToDelete.value.id}`);
    uiStore.showToast('Role deleted successfully', 'success');
    deleteModal.value = false;
    fetchRoles(meta.value.current_page);
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Failed to delete role', 'error');
  }
};

watch(search, () => {
  fetchRoles(1);
});

onMounted(fetchRoles);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Roles</h1>
        <p class="text-gray-500">Manage roles and permissions</p>
      </div>
      <Button
        v-if="authStore.hasPermission('roles.create')"
        variant="primary"
        @click="router.push('/roles/create')"
      >
        <PlusIcon class="w-5 h-5 mr-2" />
        Add Role
      </Button>
    </div>

    <Card :padding="false">
      <template #header>
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold">All Roles</h3>
          <div class="w-64">
            <Input v-model="search" placeholder="Search roles..." />
          </div>
        </div>
      </template>

      <Table :columns="columns" :data="roles" :loading="loading">
        <template #description="{ value }">
          <span class="text-gray-500">{{ value || '-' }}</span>
        </template>
        <template #is_system_role="{ value }">
          <span
            class="px-2 py-1 text-xs font-medium rounded-full"
            :class="value ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'"
          >
            {{ value ? 'System' : 'Custom' }}
          </span>
        </template>
        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-2">
            <Button
              v-if="authStore.hasPermission('roles.update') && !row.is_system_role"
              variant="ghost"
              size="sm"
              @click="router.push(`/roles/${row.id}/edit`)"
            >
              <PencilIcon class="w-4 h-4" />
            </Button>
            <Button
              v-if="authStore.hasPermission('roles.delete') && !row.is_system_role && row.users_count === 0"
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
        @page-change="fetchRoles"
      />
    </Card>

    <!-- Delete Confirmation Modal -->
    <Modal :show="deleteModal" title="Delete Role" @close="deleteModal = false">
      <p class="text-gray-600">
        Are you sure you want to delete <strong>{{ roleToDelete?.role_name }}</strong>?
        This action cannot be undone.
      </p>
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="deleteModal = false">Cancel</Button>
          <Button variant="danger" @click="deleteRole">Delete</Button>
        </div>
      </template>
    </Modal>
  </div>
</template>
