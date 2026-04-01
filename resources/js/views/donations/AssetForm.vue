<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Modal from '@/components/ui/Modal.vue';
import { ArrowLeftIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const route = useRoute();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(true);
const saving = ref(false);

// Dropdown data
const assetTypes = ref([]);

// Quick add modal
const showAssetTypeModal = ref(false);
const quickAddForm = ref({ name: '', unit: '', description: '' });
const quickAddSaving = ref(false);

// Success modal
const showSuccessModal = ref(false);
const createdAsset = ref(null);

// Form
const form = ref({
  asset_type_id: '',
  name: '',
  description: '',
  quantity: 1,
  estimated_value: null,
  acquisition_date: null,
  acquisition_type: 'existing',
  location: '',
  condition: 'good',
  notes: '',
});

const errors = ref({});

const conditions = [
  { value: 'excellent', label: 'Excellent' },
  { value: 'good', label: 'Good' },
  { value: 'fair', label: 'Fair' },
  { value: 'poor', label: 'Poor' },
];

const acquisitionTypes = [
  { value: 'existing', label: 'Existing Asset (Already Owned)' },
  { value: 'purchase', label: 'Purchased' },
];

const selectedAssetType = computed(() => {
  return assetTypes.value.find(t => t.id === form.value.asset_type_id);
});

const fetchDropdowns = async () => {
  try {
    const response = await api.get('/asset-types/all');
    assetTypes.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch asset types:', error);
  }
};

const fetchAsset = async () => {
  if (!isEdit.value) {
    loading.value = false;
    return;
  }

  try {
    const response = await api.get(`/assets/${route.params.id}`);
    const data = response.data.data;
    form.value = {
      asset_type_id: data.asset_type_id,
      name: data.name,
      description: data.description || '',
      quantity: data.quantity,
      estimated_value: data.estimated_value,
      acquisition_date: data.acquisition_date?.split('T')[0] || null,
      acquisition_type: data.acquisition_type,
      location: data.location || '',
      condition: data.condition,
      notes: data.notes || '',
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch asset', 'error');
    router.push('/donations/assets');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    const submitData = { ...form.value };

    if (isEdit.value) {
      await api.put(`/assets/${route.params.id}`, submitData);
      uiStore.showToast('Asset updated successfully', 'success');
      router.push('/donations/assets');
    } else {
      const response = await api.post('/assets', submitData);
      createdAsset.value = response.data.data;
      showSuccessModal.value = true;
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save asset', 'error');
    }
  } finally {
    saving.value = false;
  }
};

const quickAddAssetType = async () => {
  if (!quickAddForm.value.name.trim()) return;
  quickAddSaving.value = true;
  try {
    const response = await api.post('/asset-types', {
      name: quickAddForm.value.name.trim(),
      unit: quickAddForm.value.unit.trim() || null,
      description: quickAddForm.value.description.trim() || null,
    });
    assetTypes.value.push(response.data.data);
    form.value.asset_type_id = response.data.data.id;
    showAssetTypeModal.value = false;
    quickAddForm.value = { name: '', description: '', unit: '' };
    uiStore.showToast('Asset type added', 'success');
  } catch (error) {
    uiStore.showToast('Failed to add asset type', 'error');
  } finally {
    quickAddSaving.value = false;
  }
};

const resetForm = () => {
  form.value = {
    asset_type_id: '',
    name: '',
    description: '',
    quantity: 1,
    estimated_value: null,
    acquisition_date: null,
    acquisition_type: 'existing',
    location: '',
    condition: 'good',
    notes: '',
  };
  errors.value = {};
  showSuccessModal.value = false;
  createdAsset.value = null;
};

onMounted(async () => {
  await fetchDropdowns();
  await fetchAsset();
});
</script>

<template>
  <div>
    <div class="flex items-center gap-4 mb-6">
      <Button variant="ghost" @click="router.push('/donations/assets')">
        <ArrowLeftIcon class="w-5 h-5" />
      </Button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          {{ isEdit ? 'Edit Asset' : 'Add Asset' }}
        </h1>
        <p class="text-gray-500">
          {{ isEdit ? 'Update asset details' : 'Add an existing or new asset to the register' }}
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

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Asset Details -->
      <Card title="Asset Details">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-sm font-medium text-gray-700">Asset Type *</label>
              <button type="button" @click="showAssetTypeModal = true; quickAddForm = { name: '', description: '', unit: '' }" class="text-xs text-primary-600 hover:underline">
                + Add New
              </button>
            </div>
            <select
              v-model="form.asset_type_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option value="">Select Type</option>
              <option v-for="t in assetTypes" :key="t.id" :value="t.id">
                {{ t.name }}{{ t.unit ? ` (${t.unit})` : '' }}
              </option>
            </select>
          </div>

          <Input
            v-model="form.name"
            label="Asset Name *"
            placeholder="e.g., Gold Chain, Silver Lamp, Temple Land"
            required
            :error="errors.name?.[0]"
          />

          <div>
            <Input
              v-model.number="form.quantity"
              :label="`Quantity${selectedAssetType?.unit ? ` (${selectedAssetType.unit})` : ''}`"
              type="number"
              min="0.001"
              step="0.001"
              required
              :error="errors.quantity?.[0]"
            />
          </div>
        </div>

        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea
            v-model="form.description"
            rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Detailed description of the asset..."
          ></textarea>
        </div>
      </Card>

      <!-- Acquisition & Value -->
      <Card title="Acquisition & Value">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div v-if="!isEdit">
            <label class="block text-sm font-medium text-gray-700 mb-1">Source *</label>
            <select
              v-model="form.acquisition_type"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <option v-for="a in acquisitionTypes" :key="a.value" :value="a.value">{{ a.label }}</option>
            </select>
          </div>

          <Input
            v-model="form.acquisition_date"
            label="Acquisition Date"
            type="date"
          />

          <Input
            v-model.number="form.estimated_value"
            label="Estimated Value"
            type="number"
            min="0"
            step="0.01"
            placeholder="Value in INR"
          />

          <Select
            v-model="form.condition"
            label="Condition *"
            :options="conditions"
            required
          />
        </div>
      </Card>

      <!-- Location & Notes -->
      <Card title="Storage & Notes">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Input
            v-model="form.location"
            label="Storage Location"
            placeholder="e.g., Temple Safe, Store Room, Bank Locker"
          />

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea
              v-model="form.notes"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              placeholder="Any additional notes..."
            ></textarea>
          </div>
        </div>
      </Card>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-4">
        <Button type="button" variant="outline" @click="router.push('/donations/assets')">
          Cancel
        </Button>
        <Button type="submit" variant="primary" :loading="saving">
          {{ isEdit ? 'Update Asset' : 'Add Asset' }}
        </Button>
      </div>
    </form>

    <!-- Quick Add Asset Type Modal -->
    <Modal :show="showAssetTypeModal" title="Add Asset Type" @close="showAssetTypeModal = false">
      <div class="space-y-4">
        <Input v-model="quickAddForm.name" label="Asset Type Name" placeholder="e.g., Gold, Silver, Land" />
        <Input v-model="quickAddForm.unit" label="Unit (Optional)" placeholder="e.g., grams, kg, sq.ft" />
        <Input v-model="quickAddForm.description" label="Description (Optional)" placeholder="Brief description" />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showAssetTypeModal = false">Cancel</Button>
          <Button variant="primary" :loading="quickAddSaving" @click="quickAddAssetType">Add</Button>
        </div>
      </div>
    </Modal>

    <!-- Success Modal -->
    <Modal :show="showSuccessModal" title="Asset Added" @close="router.push('/donations/assets')">
      <div class="text-center py-4">
        <div class="mx-auto flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
          <CheckCircleIcon class="w-10 h-10 text-green-600" />
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Asset Added Successfully!</h3>
        <p v-if="createdAsset" class="text-gray-600">
          {{ createdAsset.asset_number }}
        </p>
      </div>
      <div class="flex justify-center gap-3 pt-4 border-t">
        <Button variant="outline" @click="resetForm">Add Another</Button>
        <Button variant="primary" @click="router.push('/donations/assets')">View All</Button>
      </div>
    </Modal>
  </div>
</template>
