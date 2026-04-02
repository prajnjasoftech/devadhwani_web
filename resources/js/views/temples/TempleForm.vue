<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import { isValidIndianMobile, mobileValidationMessage } from '@/composables/useValidation';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Modal from '@/components/ui/Modal.vue';

const route = useRoute();
const router = useRouter();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(false);
const saving = ref(false);
const credentialsModal = ref(false);
const newCredentials = ref(null);

const form = ref({
  temple_name: '',
  contact_person_name: '',
  contact_number: '',
  alternate_contact_number: '',
  email: '',
  address: '',
  district: '',
  place: '',
  id_proof_type: '',
  id_proof_number: '',
  status: 'active',
});

const errors = ref({});

const statusOptions = [
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'suspended', label: 'Suspended' },
];

const idProofOptions = [
  { value: 'aadhaar', label: 'Aadhaar' },
  { value: 'pan', label: 'PAN Card' },
  { value: 'driving_license', label: 'Driving License' },
];

const fetchTemple = async () => {
  if (!isEdit.value) return;

  loading.value = true;
  try {
    const response = await api.get(`/temples/${route.params.id}`);
    const data = response.data.data;
    Object.keys(form.value).forEach((key) => {
      if (data[key] !== undefined) {
        form.value[key] = data[key] || '';
      }
    });
  } catch (error) {
    uiStore.showToast('Failed to fetch temple', 'error');
    router.push('/temples');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};

  // Validate mobile numbers
  if (form.value.contact_number && !isValidIndianMobile(form.value.contact_number)) {
    errors.value.contact_number = [mobileValidationMessage];
    return;
  }
  if (form.value.alternate_contact_number && !isValidIndianMobile(form.value.alternate_contact_number)) {
    errors.value.alternate_contact_number = [mobileValidationMessage];
    return;
  }

  saving.value = true;

  try {
    if (isEdit.value) {
      await api.put(`/temples/${route.params.id}`, form.value);
      uiStore.showToast('Temple updated successfully', 'success');
      router.push('/temples');
    } else {
      const response = await api.post('/temples', form.value);
      newCredentials.value = response.data.data.super_admin;
      credentialsModal.value = true;
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save temple', 'error');
    }
  } finally {
    saving.value = false;
  }
};

const closeCredentialsModal = () => {
  credentialsModal.value = false;
  router.push('/temples');
};

onMounted(fetchTemple);
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isEdit ? 'Edit Temple' : 'Add New Temple' }}
      </h1>
      <p class="text-gray-500">
        {{ isEdit ? 'Update temple information' : 'Register a new temple in the system' }}
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card title="Basic Information">
          <div class="space-y-4">
            <Input
              v-model="form.temple_name"
              label="Temple Name"
              required
              :error="errors.temple_name?.[0]"
            />
            <Input
              v-model="form.contact_person_name"
              label="Contact Person Name"
              required
              :error="errors.contact_person_name?.[0]"
            />
            <div class="grid grid-cols-2 gap-4">
              <Input
                v-model="form.contact_number"
                label="Contact Number"
                type="tel"
                required
                :error="errors.contact_number?.[0] || (form.contact_number && !isValidIndianMobile(form.contact_number) ? mobileValidationMessage : '')"
                pattern="[6-9][0-9]{9}"
                maxlength="10"
                inputmode="tel"
                placeholder="10-digit mobile"
              />
              <Input
                v-model="form.alternate_contact_number"
                label="Alternate Contact"
                type="tel"
                :error="errors.alternate_contact_number?.[0] || (form.alternate_contact_number && !isValidIndianMobile(form.alternate_contact_number) ? mobileValidationMessage : '')"
                pattern="[6-9][0-9]{9}"
                maxlength="10"
                inputmode="tel"
                placeholder="10-digit mobile"
              />
            </div>
            <Input
              v-model="form.email"
              label="Email"
              type="email"
              :error="errors.email?.[0]"
            />
          </div>
        </Card>

        <Card title="Address & Location">
          <div class="space-y-4">
            <Input v-model="form.address" label="Address" />
            <div class="grid grid-cols-2 gap-4">
              <Input v-model="form.district" label="District" />
              <Input v-model="form.place" label="Place" />
            </div>
          </div>
        </Card>

        <Card title="ID Proof">
          <div class="space-y-4">
            <Select
              v-model="form.id_proof_type"
              label="ID Proof Type"
              :options="idProofOptions"
              placeholder="Select ID proof type"
            />
            <Input
              v-model="form.id_proof_number"
              label="ID Proof Number"
              :disabled="!form.id_proof_type"
            />
          </div>
        </Card>

        <Card v-if="isEdit" title="Status">
          <Select
            v-model="form.status"
            label="Status"
            :options="statusOptions"
          />
        </Card>
      </div>

      <div class="mt-6 flex items-center justify-end gap-4">
        <Button variant="outline" type="button" @click="router.push('/temples')">
          Cancel
        </Button>
        <Button variant="primary" type="submit" :loading="saving">
          {{ isEdit ? 'Update Temple' : 'Create Temple' }}
        </Button>
      </div>
    </form>

    <!-- Credentials Modal -->
    <Modal :show="credentialsModal" title="Temple Created Successfully" @close="closeCredentialsModal">
      <div class="space-y-4">
        <p class="text-gray-600">
          The temple has been created. Here are the super admin credentials:
        </p>
        <div class="bg-gray-50 p-4 rounded-lg space-y-2">
          <div>
            <span class="text-sm text-gray-500">Contact Number:</span>
            <p class="font-mono font-semibold">{{ newCredentials?.contact_number }}</p>
          </div>
          <div>
            <span class="text-sm text-gray-500">Password:</span>
            <p class="font-mono font-semibold">{{ newCredentials?.password }}</p>
          </div>
        </div>
        <p class="text-sm text-yellow-600">
          Please save these credentials securely. The password will need to be reset on first login.
        </p>
      </div>
      <template #footer>
        <Button variant="primary" @click="closeCredentialsModal" class="w-full">
          Done
        </Button>
      </template>
    </Modal>
  </div>
</template>
