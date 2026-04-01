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
  ArrowLeftIcon,
  PencilIcon,
  NoSymbolIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const loading = ref(true);
const categories = ref([]);
const meta = ref({});
const search = ref('');

const columns = [
  { key: 'name', label: 'Category Name' },
  { key: 'description', label: 'Description' },
  { key: 'expenses_count', label: 'Expenses' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: '' },
];

const fetchCategories = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      search: search.value || undefined,
    };
    const response = await api.get('/expense-categories', { params });
    categories.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch categories', 'error');
  } finally {
    loading.value = false;
  }
};

const toggleStatus = async (category) => {
  try {
    await api.put(`/expense-categories/${category.id}`, {
      is_active: !category.is_active,
    });
    category.is_active = !category.is_active;
    uiStore.showToast(
      `Category ${category.is_active ? 'activated' : 'deactivated'} successfully`,
      'success'
    );
  } catch (error) {
    uiStore.showToast('Failed to update category status', 'error');
  }
};

watch(search, () => {
  fetchCategories(1);
});

onMounted(() => {
  fetchCategories();
});
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <Button variant="ghost" @click="router.push('/expenses')">
          <ArrowLeftIcon class="w-5 h-5" />
        </Button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Expense Categories</h1>
          <p class="text-gray-500">Manage expense categories for your temple</p>
        </div>
      </div>
      <Button
        v-if="authStore.hasPermission('expenses.create')"
        variant="primary"
        @click="router.push('/expenses/categories/new')"
      >
        <PlusIcon class="w-5 h-5 mr-1" />
        New Category
      </Button>
    </div>

    <Card>
      <!-- Search -->
      <div class="mb-4">
        <input
          v-model="search"
          type="text"
          placeholder="Search categories..."
          class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
        />
      </div>

      <Table :columns="columns" :data="categories" :loading="loading">
        <template #name="{ row }">
          <span class="font-medium text-gray-900">{{ row.name }}</span>
        </template>
        <template #description="{ row }">
          <span class="text-gray-500">{{ row.description || '-' }}</span>
        </template>
        <template #expenses_count="{ row }">
          <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
            {{ row.expenses_count || 0 }} expenses
          </span>
        </template>
        <template #is_active="{ row }">
          <span
            :class="[
              'px-2 py-1 text-xs font-medium rounded-full',
              row.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800',
            ]"
          >
            {{ row.is_active ? 'Active' : 'Inactive' }}
          </span>
        </template>
        <template #actions="{ row }">
          <div class="flex items-center gap-1">
            <button
              v-if="authStore.hasPermission('expenses.update')"
              @click="router.push(`/expenses/categories/${row.id}/edit`)"
              class="p-2 text-gray-500 hover:text-primary-600 hover:bg-gray-100 rounded-lg"
              title="Edit"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              v-if="authStore.hasPermission('expenses.update')"
              @click="toggleStatus(row)"
              :class="[
                'p-2 rounded-lg',
                row.is_active
                  ? 'text-gray-500 hover:text-red-600 hover:bg-red-50'
                  : 'text-gray-500 hover:text-green-600 hover:bg-green-50',
              ]"
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
          @click="fetchCategories(page)"
        >
          {{ page }}
        </Button>
      </div>
    </Card>
  </div>
</template>
