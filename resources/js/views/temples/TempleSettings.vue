<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/composables/useApi';
import { useUiStore } from '@/stores/ui';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Button from '@/components/ui/Button.vue';

const router = useRouter();
const uiStore = useUiStore();

const loading = ref(true);
const saving = ref(false);
const temple = ref(null);
const errors = ref({});

const form = ref({
  temple_name: '',
  contact_person_name: '',
  contact_number: '', // Read-only
  alternate_contact_number: '',
  email: '',
  address: '',
  district: '',
  place: '',
  id_proof_type: '',
  id_proof_number: '',
});

const idProofTypes = [
  { value: '', label: 'Select ID Proof Type' },
  { value: 'aadhaar', label: 'Aadhaar' },
  { value: 'pan', label: 'PAN' },
  { value: 'driving_license', label: 'Driving License' },
];

const fetchTemple = async () => {
  try {
    const response = await api.get('/my-temple');
    if (response.data.success) {
      temple.value = response.data.data;
      populateForm(response.data.data);
    }
  } catch (error) {
    uiStore.showToast('Failed to load temple details', 'error');
  } finally {
    loading.value = false;
  }
};

const populateForm = (data) => {
  form.value = {
    temple_name: data.temple_name || '',
    contact_person_name: data.contact_person_name || '',
    contact_number: data.contact_number || '',
    alternate_contact_number: data.alternate_contact_number || '',
    email: data.email || '',
    address: data.address || '',
    district: data.district || '',
    place: data.place || '',
    id_proof_type: data.id_proof_type || '',
    id_proof_number: data.id_proof_number || '',
  };
};

const submitForm = async () => {
  errors.value = {};
  saving.value = true;

  try {
    // Exclude contact_number from submission
    const { contact_number, ...submitData } = form.value;

    const response = await api.put('/my-temple', submitData);
    if (response.data.success) {
      uiStore.showToast('Temple updated successfully', 'success');
      temple.value = response.data.data;
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast(error.response?.data?.message || 'Failed to update temple', 'error');
    }
  } finally {
    saving.value = false;
  }
};

onMounted(fetchTemple);
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Temple Settings</h1>
      <p class="text-gray-500">Manage your temple information</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="submitForm" class="space-y-6">
      <Card>
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Input
            v-model="form.temple_name"
            label="Temple Name"
            required
            :error="errors.temple_name?.[0]"
          />
          <div>
            <Input
              v-model="form.contact_number"
              label="Contact Number"
              disabled
              class="bg-gray-100"
            />
            <p class="mt-1 text-xs text-gray-500">Contact number cannot be changed as it's used for login</p>
          </div>
          <Input
            v-model="form.contact_person_name"
            label="Contact Person Name"
            required
            :error="errors.contact_person_name?.[0]"
          />
          <Input
            v-model="form.alternate_contact_number"
            label="Alternate Contact Number"
            :error="errors.alternate_contact_number?.[0]"
          />
          <Input
            v-model="form.email"
            label="Email"
            type="email"
            :error="errors.email?.[0]"
          />
        </div>
      </Card>

      <Card>
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Address</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="md:col-span-2">
            <Input
              v-model="form.address"
              label="Full Address"
              :error="errors.address?.[0]"
            />
          </div>
          <Input
            v-model="form.district"
            label="District"
            :error="errors.district?.[0]"
          />
          <Input
            v-model="form.place"
            label="Place"
            :error="errors.place?.[0]"
          />
        </div>
      </Card>

      <Card>
        <h2 class="text-lg font-semibold text-gray-900 mb-4">ID Proof</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Select
            v-model="form.id_proof_type"
            label="ID Proof Type"
            :options="idProofTypes"
            :error="errors.id_proof_type?.[0]"
          />
          <Input
            v-model="form.id_proof_number"
            label="ID Proof Number"
            :error="errors.id_proof_number?.[0]"
          />
        </div>
      </Card>

      <div class="flex justify-end">
        <Button type="submit" :loading="saving">
          Save Changes
        </Button>
      </div>
    </form>
  </div>
</template>
