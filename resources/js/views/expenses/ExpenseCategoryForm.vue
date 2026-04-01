<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const route = useRoute();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(true);
const saving = ref(false);

const form = ref({
  name: '',
  description: '',
});

const errors = ref({});

const fetchCategory = async () => {
  if (!isEdit.value) {
    loading.value = false;
    return;
  }

  try {
    const response = await api.get(`/expense-categories/${route.params.id}`);
    const data = response.data.data;
    form.value = {
      name: data.name,
      description: data.description || '',
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch category', 'error');
    router.push('/expenses/categories');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    if (isEdit.value) {
      await api.put(`/expense-categories/${route.params.id}`, form.value);
      uiStore.showToast('Category updated successfully', 'success');
    } else {
      await api.post('/expense-categories', form.value);
      uiStore.showToast('Category created successfully', 'success');
    }
    router.push('/expenses/categories');
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save category', 'error');
    }
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchCategory();
});
</script>

<template>
  <div>
    <div class="flex items-center gap-4 mb-6">
      <Button variant="ghost" @click="router.push('/expenses/categories')">
        <ArrowLeftIcon class="w-5 h-5" />
      </Button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          {{ isEdit ? 'Edit Category' : 'New Category' }}
        </h1>
        <p class="text-gray-500">
          {{ isEdit ? 'Update expense category details' : 'Create a new expense category' }}
        </p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="max-w-2xl">
      <Card title="Category Details">
        <div class="space-y-6">
          <Input
            v-model="form.name"
            label="Category Name"
            placeholder="e.g., Electricity, Salary, Maintenance"
            required
            :error="errors.name?.[0]"
          />

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              placeholder="Brief description of this expense category..."
            ></textarea>
            <p v-if="errors.description?.[0]" class="mt-1 text-sm text-red-600">
              {{ errors.description[0] }}
            </p>
          </div>
        </div>
      </Card>

      <div class="flex items-center justify-end gap-4 mt-6">
        <Button type="button" variant="outline" @click="router.push('/expenses/categories')">
          Cancel
        </Button>
        <Button type="submit" variant="primary" :loading="saving">
          {{ isEdit ? 'Update Category' : 'Create Category' }}
        </Button>
      </div>
    </form>
  </div>
</template>
