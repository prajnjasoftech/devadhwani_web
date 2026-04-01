<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import {
  BuildingLibraryIcon,
  UsersIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const stats = ref(null);
const loading = ref(true);

const fetchStats = async () => {
  try {
    const response = await api.get('/dashboard/stats');
    stats.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch stats:', error);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchStats);
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
      <p class="text-gray-500">Welcome back, {{ authStore.user?.name }}</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <!-- Platform Admin Stats - Temples Only -->
      <template v-if="authStore.isPlatformAdmin">
        <Card>
          <div class="flex items-center">
            <div class="p-3 bg-primary-100 rounded-lg">
              <BuildingLibraryIcon class="w-6 h-6 text-primary-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Total Temples</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats?.temples?.total || 0 }}</p>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg">
              <BuildingLibraryIcon class="w-6 h-6 text-green-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Active Temples</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats?.temples?.active || 0 }}</p>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center">
            <div class="p-3 bg-gray-100 rounded-lg">
              <BuildingLibraryIcon class="w-6 h-6 text-gray-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Inactive Temples</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats?.temples?.inactive || 0 }}</p>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center">
            <div class="p-3 bg-red-100 rounded-lg">
              <BuildingLibraryIcon class="w-6 h-6 text-red-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Suspended Temples</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats?.temples?.suspended || 0 }}</p>
            </div>
          </div>
        </Card>
      </template>

      <!-- Temple User Stats -->
      <template v-else>
        <Card>
          <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg">
              <UsersIcon class="w-6 h-6 text-blue-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Total Users</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats?.users?.total || 0 }}</p>
            </div>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-100">
            <span class="text-sm text-green-600">{{ stats?.users?.active || 0 }} active</span>
          </div>
        </Card>

        <Card>
          <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-lg">
              <ShieldCheckIcon class="w-6 h-6 text-purple-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Total Roles</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats?.roles?.total || 0 }}</p>
            </div>
          </div>
        </Card>
      </template>
    </div>
  </div>
</template>
