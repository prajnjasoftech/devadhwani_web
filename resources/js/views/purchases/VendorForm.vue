<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

const router = useRouter();
const route = useRoute();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(false);
const saving = ref(false);

const form = ref({
  name: '',
  description: '',
  contact_person: '',
  contact_number: '',
  email: '',
  address: '',
  gst_number: '',
  notes: '',
  is_active: true,
});

const errors = ref({});

const fetchVendor = async () => {
  if (!isEdit.value) return;

  loading.value = true;
  try {
    const response = await api.get(`/vendors/${route.params.id}`);
    const data = response.data.data;
    form.value = {
      name: data.name,
      description: data.description || '',
      contact_person: data.contact_person || '',
      contact_number: data.contact_number || '',
      email: data.email || '',
      address: data.address || '',
      gst_number: data.gst_number || '',
      notes: data.notes || '',
      is_active: data.is_active,
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch vendor', 'error');
    router.push('/purchases/vendors');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    if (isEdit.value) {
      await api.put(`/vendors/${route.params.id}`, form.value);
      uiStore.showToast('Vendor updated successfully', 'success');
    } else {
      await api.post('/vendors', form.value);
      uiStore.showToast('Vendor created successfully', 'success');
    }
    router.push('/purchases/vendors');
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save vendor', 'error');
    }
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchVendor();
});
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isEdit ? 'Edit Vendor' : 'Add New Vendor' }}
      </h1>
      <p class="text-gray-500">
        {{ isEdit ? 'Update vendor information' : 'Add a new vendor/supplier' }}
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <Card title="Vendor Details">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Input
            v-model="form.name"
            label="Vendor Name"
            placeholder="e.g., RMR Florist"
            required
            :error="errors.name?.[0]"
          />
          <Input
            v-model="form.contact_person"
            label="Contact Person"
            placeholder="e.g., Rajan"
            :error="errors.contact_person?.[0]"
          />
        </div>

        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea
            v-model="form.description"
            rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Brief description about this vendor..."
          ></textarea>
        </div>
      </Card>

      <Card title="Contact Information">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Input
            v-model="form.contact_number"
            label="Phone Number"
            placeholder="e.g., 9876543210"
            :error="errors.contact_number?.[0]"
          />
          <Input
            v-model="form.email"
            label="Email"
            type="email"
            placeholder="e.g., vendor@email.com"
            :error="errors.email?.[0]"
          />
        </div>

        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
          <textarea
            v-model="form.address"
            rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Full address..."
          ></textarea>
        </div>
      </Card>

      <Card title="Additional Information">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Input
            v-model="form.gst_number"
            label="GST Number"
            placeholder="Optional"
            :error="errors.gst_number?.[0]"
          />
          <div class="flex items-center gap-3 pt-6">
            <input
              v-model="form.is_active"
              type="checkbox"
              id="is_active"
              class="w-5 h-5 text-primary-500 border-gray-300 rounded focus:ring-primary-500"
            />
            <label for="is_active" class="text-sm font-medium text-gray-700">Active Vendor</label>
          </div>
        </div>

        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea
            v-model="form.notes"
            rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Any additional notes..."
          ></textarea>
        </div>
      </Card>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-4">
        <Button type="button" variant="outline" @click="router.push('/purchases/vendors')">
          Cancel
        </Button>
        <Button type="submit" variant="primary" :loading="saving">
          {{ isEdit ? 'Update Vendor' : 'Save Vendor' }}
        </Button>
      </div>
    </form>
  </div>
</template>
