<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Table from '@/components/ui/Table.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { EyeIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const uiStore = useUiStore();

const devotees = ref([]);
const loading = ref(true);
const search = ref('');
const currentPage = ref(1);
const meta = ref({});

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'nakshathra', label: 'Nakshathra' },
  { key: 'gothram', label: 'Gothram' },
  { key: 'bookings_count', label: 'Bookings' },
  { key: 'actions', label: 'Actions', class: 'text-right' },
];

const fetchDevotees = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      search: search.value || undefined,
    };

    const response = await api.get('/devotees', { params });
    devotees.value = response.data.data;
    meta.value = response.data.meta;
  } catch (error) {
    uiStore.showToast('Failed to fetch devotees', 'error');
  } finally {
    loading.value = false;
  }
};

watch(search, () => {
  currentPage.value = 1;
  fetchDevotees();
});

watch(currentPage, fetchDevotees);

onMounted(fetchDevotees);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Devotees</h1>
        <p class="text-gray-500">View devotees and their booking history</p>
      </div>
    </div>

    <Card>
      <div class="mb-6">
        <input
          v-model="search"
          type="text"
          placeholder="Search by name, gothram..."
          class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        />
      </div>

      <Table :columns="columns" :data="devotees" :loading="loading">
        <template #name="{ row }">
          <span class="font-medium">{{ row.name }}</span>
        </template>

        <template #nakshathra="{ row }">
          <span v-if="row.nakshathra" class="text-gray-700">
            {{ row.nakshathra.malayalam_name }}
          </span>
          <span v-else class="text-gray-400">-</span>
        </template>

        <template #gothram="{ row }">
          <span v-if="row.gothram" class="text-gray-700">{{ row.gothram }}</span>
          <span v-else class="text-gray-400">-</span>
        </template>

        <template #bookings_count="{ row }">
          <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">
            {{ row.bookings_count || 0 }} bookings
          </span>
        </template>

        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-2">
            <Button
              variant="ghost"
              size="sm"
              title="View History"
              @click="router.push(`/devotees/${row.id}`)"
            >
              <EyeIcon class="w-4 h-4" />
            </Button>
          </div>
        </template>
      </Table>

      <Pagination
        v-if="meta.last_page > 1"
        :current-page="currentPage"
        :last-page="meta.last_page"
        :total="meta.total"
        @page-change="currentPage = $event"
        class="mt-6"
      />
    </Card>
  </div>
</template>
