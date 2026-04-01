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

const form = ref({
  name: '',
  sanskrit_name: '',
  description: '',
  deity_type: 'sub',
  display_order: 0,
  is_active: true,
});

const imageFile = ref(null);
const imagePreview = ref(null);
const errors = ref({});

const deityTypes = [
  { value: 'main', label: 'Main Deity' },
  { value: 'sub', label: 'Sub Deity' },
  { value: 'upadevata', label: 'Upadevata' },
];

const fetchDeity = async () => {
  if (!isEdit.value) return;

  loading.value = true;
  try {
    const response = await api.get(`/deities/${route.params.id}`);
    const data = response.data.data;
    form.value = {
      name: data.name,
      sanskrit_name: data.sanskrit_name || '',
      description: data.description || '',
      deity_type: data.deity_type,
      display_order: data.display_order,
      is_active: data.is_active,
    };
    if (data.image_url) {
      imagePreview.value = data.image_url;
    }
  } catch (error) {
    uiStore.showToast('Failed to fetch deity', 'error');
    router.push('/deities');
  } finally {
    loading.value = false;
  }
};

const handleImageChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    imageFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    const formData = new FormData();
    formData.append('name', form.value.name);
    formData.append('sanskrit_name', form.value.sanskrit_name || '');
    formData.append('description', form.value.description || '');
    formData.append('deity_type', form.value.deity_type);
    formData.append('display_order', form.value.display_order);
    formData.append('is_active', form.value.is_active ? '1' : '0');

    if (imageFile.value) {
      formData.append('image', imageFile.value);
    }

    if (isEdit.value) {
      formData.append('_method', 'PUT');
      await api.post(`/deities/${route.params.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      uiStore.showToast('Deity updated successfully', 'success');
    } else {
      await api.post('/deities', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      uiStore.showToast('Deity created successfully', 'success');
    }
    router.push('/deities');
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save deity', 'error');
    }
  } finally {
    saving.value = false;
  }
};

onMounted(fetchDeity);
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isEdit ? 'Edit Deity' : 'Add New Deity' }}
      </h1>
      <p class="text-gray-500">
        {{ isEdit ? 'Update deity information' : 'Add a new deity to the temple' }}
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Card title="Basic Information" class="lg:col-span-2">
          <div class="space-y-4">
            <Input
              v-model="form.name"
              label="Deity Name"
              required
              :error="errors.name?.[0]"
            />
            <Input
              v-model="form.sanskrit_name"
              label="Sanskrit Name"
              :error="errors.sanskrit_name?.[0]"
            />
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea
                v-model="form.description"
                rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Brief description of the deity..."
              ></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Select
                v-model="form.deity_type"
                label="Deity Type"
                :options="deityTypes"
                required
                :error="errors.deity_type?.[0]"
              />
              <Input
                v-model.number="form.display_order"
                label="Display Order"
                type="number"
                min="0"
                :error="errors.display_order?.[0]"
              />
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <label class="flex items-center mt-2">
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

        <Card title="Deity Image">
          <div class="space-y-4">
            <div
              class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center"
              :class="{ 'border-primary-500': imagePreview }"
            >
              <img
                v-if="imagePreview"
                :src="imagePreview"
                alt="Preview"
                class="w-32 h-32 mx-auto rounded-lg object-cover mb-4"
              />
              <div v-else class="w-32 h-32 mx-auto bg-gray-100 rounded-lg flex items-center justify-center mb-4">
                <span class="text-gray-400 text-4xl">🙏</span>
              </div>
              <input
                type="file"
                accept="image/*"
                @change="handleImageChange"
                class="hidden"
                id="deity-image"
              />
              <label
                for="deity-image"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 cursor-pointer"
              >
                {{ imagePreview ? 'Change Image' : 'Upload Image' }}
              </label>
              <p class="mt-2 text-xs text-gray-500">PNG, JPG up to 2MB</p>
            </div>
          </div>
        </Card>
      </div>

      <div class="flex items-center justify-end gap-4">
        <Button variant="outline" type="button" @click="router.push('/deities')">
          Cancel
        </Button>
        <Button variant="primary" type="submit" :loading="saving">
          {{ isEdit ? 'Update Deity' : 'Create Deity' }}
        </Button>
      </div>
    </form>
  </div>
</template>
