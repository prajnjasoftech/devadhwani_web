<script setup>
import { ref, onMounted, computed } from 'vue';
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
const roles = ref([]);

const form = ref({
  name: '',
  contact_number: '',
  email: '',
  address: '',
  role_id: '',
  password: '',
  is_active: true,
  id_proof_type: '',
  id_proof_number: '',
});

const errors = ref({});

const idProofOptions = [
  { value: 'aadhaar', label: 'Aadhaar' },
  { value: 'pan', label: 'PAN Card' },
  { value: 'driving_license', label: 'Driving License' },
];

const statusOptions = [
  { value: true, label: 'Active' },
  { value: false, label: 'Inactive' },
];

const fetchRoles = async () => {
  try {
    const response = await api.get('/roles/all');
    roles.value = response.data.data.map((role) => ({
      value: role.id,
      label: role.role_name,
    }));
  } catch (error) {
    console.error('Failed to fetch roles:', error);
  }
};

const fetchUser = async () => {
  if (!isEdit.value) return;

  loading.value = true;
  try {
    const response = await api.get(`/users/${route.params.id}`);
    const data = response.data.data;
    Object.keys(form.value).forEach((key) => {
      if (key !== 'password' && data[key] !== undefined) {
        form.value[key] = data[key] ?? '';
      }
    });
    form.value.role_id = data.role_id || '';
  } catch (error) {
    uiStore.showToast('Failed to fetch user', 'error');
    router.push('/users');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  const data = { ...form.value };
  if (isEdit.value && !data.password) {
    delete data.password;
  }

  try {
    if (isEdit.value) {
      await api.put(`/users/${route.params.id}`, data);
      uiStore.showToast('User updated successfully', 'success');
    } else {
      await api.post('/users', data);
      uiStore.showToast('User created successfully', 'success');
    }
    router.push('/users');
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save user', 'error');
    }
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchRoles();
  fetchUser();
});
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isEdit ? 'Edit User' : 'Add New User' }}
      </h1>
      <p class="text-gray-500">
        {{ isEdit ? 'Update user information' : 'Create a new user account' }}
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
              v-model="form.name"
              label="Full Name"
              required
              :error="errors.name?.[0]"
            />
            <Input
              v-model="form.contact_number"
              label="Contact Number"
              type="tel"
              required
              :error="errors.contact_number?.[0]"
            />
            <Input
              v-model="form.email"
              label="Email"
              type="email"
              :error="errors.email?.[0]"
            />
            <Input v-model="form.address" label="Address" />
          </div>
        </Card>

        <Card title="Access Settings">
          <div class="space-y-4">
            <Select
              v-model="form.role_id"
              label="Role"
              :options="roles"
              placeholder="Select a role"
              :error="errors.role_id?.[0]"
            />
            <Input
              v-model="form.password"
              label="Password"
              type="password"
              :required="!isEdit"
              :placeholder="isEdit ? 'Leave blank to keep current' : ''"
              :error="errors.password?.[0]"
            />
            <Select
              v-if="isEdit"
              v-model="form.is_active"
              label="Status"
              :options="statusOptions"
            />
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
      </div>

      <div class="mt-6 flex items-center justify-end gap-4">
        <Button variant="outline" type="button" @click="router.push('/users')">
          Cancel
        </Button>
        <Button variant="primary" type="submit" :loading="saving">
          {{ isEdit ? 'Update User' : 'Create User' }}
        </Button>
      </div>
    </form>
  </div>
</template>
