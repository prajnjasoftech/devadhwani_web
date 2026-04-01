<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';

const route = useRoute();
const router = useRouter();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(false);
const saving = ref(false);
const deities = ref([]);

const form = ref({
  deity_id: '',
  name: '',
  description: '',
  frequency: 'once',
  next_pooja_date: '',
  amount: 0,
  devotee_required: false,
  is_active: true,
});

const errors = ref({});

const frequencies = [
  { value: 'once', label: 'One Time' },
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
];

const fetchDeities = async () => {
  try {
    const response = await api.get('/deities/all');
    deities.value = response.data.data.map(d => ({
      value: d.id,
      label: d.name,
    }));
  } catch (error) {
    console.error('Failed to fetch deities:', error);
  }
};

const fetchPooja = async () => {
  if (!isEdit.value) return;

  loading.value = true;
  try {
    const response = await api.get(`/poojas/${route.params.id}`);
    const data = response.data.data;
    form.value = {
      deity_id: data.deity_id,
      name: data.name,
      description: data.description || '',
      frequency: data.frequency,
      next_pooja_date: data.next_pooja_date || '',
      amount: data.amount,
      devotee_required: data.devotee_required,
      is_active: data.is_active,
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch pooja', 'error');
    router.push('/poojas');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    const submitData = { ...form.value };
    if (!submitData.next_pooja_date) {
      delete submitData.next_pooja_date;
    }
    // Convert empty deity_id to null
    if (!submitData.deity_id) {
      submitData.deity_id = null;
    }

    if (isEdit.value) {
      await api.put(`/poojas/${route.params.id}`, submitData);
      uiStore.showToast('Pooja updated successfully', 'success');
    } else {
      await api.post('/poojas', submitData);
      uiStore.showToast('Pooja created successfully', 'success');
    }
    router.push('/poojas');
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save pooja', 'error');
    }
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchDeities();
  fetchPooja();
});
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isEdit ? 'Edit Pooja' : 'Add New Pooja' }}
      </h1>
      <p class="text-gray-500">
        {{ isEdit ? 'Update pooja information' : 'Add a new pooja to the temple' }}
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <Card title="Pooja Details">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Input
            v-model="form.name"
            label="Pooja Name"
            required
            :error="errors.name?.[0]"
          />
          <Select
            v-model="form.deity_id"
            label="Deity"
            :options="[{ value: '', label: 'No specific deity' }, ...deities]"
            :error="errors.deity_id?.[0]"
          />
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="Brief description of the pooja..."
            ></textarea>
          </div>
        </div>
      </Card>

      <Card title="Schedule & Pricing">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Select
            v-model="form.frequency"
            label="Frequency"
            :options="frequencies"
            required
            :error="errors.frequency?.[0]"
          />
          <Input
            v-model="form.next_pooja_date"
            label="Next Pooja Date"
            type="date"
            :error="errors.next_pooja_date?.[0]"
          />
          <Input
            v-model.number="form.amount"
            label="Amount (₹)"
            type="number"
            min="0"
            step="0.01"
            required
            :error="errors.amount?.[0]"
          />
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Options</label>
            <div class="space-y-2 mt-2">
              <label class="flex items-center">
                <input
                  v-model="form.devotee_required"
                  type="checkbox"
                  class="w-4 h-4 text-primary-500 border-gray-300 rounded focus:ring-primary-500"
                />
                <span class="ml-2 text-sm text-gray-600">Devotee Required</span>
              </label>
              <label class="flex items-center">
                <input
                  v-model="form.is_active"
                  type="checkbox"
                  class="w-4 h-4 text-primary-500 border-gray-300 rounded focus:ring-primary-500"
                />
                <span class="ml-2 text-sm text-gray-600">Active</span>
              </label>
            </div>
          </div>
        </div>
      </Card>

      <div class="flex items-center justify-end gap-4">
        <Button variant="outline" type="button" @click="router.push('/poojas')">
          Cancel
        </Button>
        <Button variant="primary" type="submit" :loading="saving">
          {{ isEdit ? 'Update Pooja' : 'Create Pooja' }}
        </Button>
      </div>
    </form>
  </div>
</template>
