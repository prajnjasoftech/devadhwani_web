<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import { isValidIndianMobile, mobileValidationMessage } from '@/composables/useValidation';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Modal from '@/components/ui/Modal.vue';
import { ArrowLeftIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const route = useRoute();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(true);
const saving = ref(false);

// Dropdown data
const roles = ref([]);

// Success modal
const showSuccessModal = ref(false);
const createdEmployee = ref(null);
const defaultPassword = ref('');

// Form
const form = ref({
  name: '',
  designation: '',
  contact_number: '',
  alternate_contact: '',
  email: '',
  address: '',
  date_of_birth: null,
  date_of_joining: new Date().toISOString().split('T')[0],
  basic_salary: 0,
  bank_name: '',
  bank_account_number: '',
  ifsc_code: '',
  pan_number: '',
  aadhaar_number: '',
  notes: '',
  create_user: false,
  role_id: '',
});

const errors = ref({});

const fetchRoles = async () => {
  try {
    const response = await api.get('/roles/all');
    roles.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch roles:', error);
  }
};

const fetchEmployee = async () => {
  if (!isEdit.value) {
    loading.value = false;
    return;
  }

  try {
    const response = await api.get(`/employees/${route.params.id}`);
    const data = response.data.data;
    form.value = {
      name: data.name,
      designation: data.designation,
      contact_number: data.contact_number,
      alternate_contact: data.alternate_contact || '',
      email: data.email || '',
      address: data.address || '',
      date_of_birth: data.date_of_birth?.split('T')[0] || null,
      date_of_joining: data.date_of_joining.split('T')[0],
      basic_salary: data.basic_salary,
      bank_name: data.bank_name || '',
      bank_account_number: data.bank_account_number || '',
      ifsc_code: data.ifsc_code || '',
      pan_number: data.pan_number || '',
      aadhaar_number: data.aadhaar_number || '',
      notes: data.notes || '',
      create_user: false,
      role_id: '',
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch employee', 'error');
    router.push('/employees');
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
  if (form.value.alternate_contact && !isValidIndianMobile(form.value.alternate_contact)) {
    errors.value.alternate_contact = [mobileValidationMessage];
    return;
  }

  saving.value = true;

  try {
    if (isEdit.value) {
      await api.put(`/employees/${route.params.id}`, form.value);
      uiStore.showToast('Employee updated successfully', 'success');
      router.push('/employees');
    } else {
      const response = await api.post('/employees', form.value);
      createdEmployee.value = response.data.data;
      if (form.value.create_user) {
        defaultPassword.value = 'Employee@123';
      }
      showSuccessModal.value = true;
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save employee', 'error');
    }
  } finally {
    saving.value = false;
  }
};

const resetForm = () => {
  form.value = {
    name: '',
    designation: '',
    contact_number: '',
    alternate_contact: '',
    email: '',
    address: '',
    date_of_birth: null,
    date_of_joining: new Date().toISOString().split('T')[0],
    basic_salary: 0,
    bank_name: '',
    bank_account_number: '',
    ifsc_code: '',
    pan_number: '',
    aadhaar_number: '',
    notes: '',
    create_user: false,
    role_id: '',
  };
  errors.value = {};
  showSuccessModal.value = false;
  createdEmployee.value = null;
  defaultPassword.value = '';
};

// Clear role when create_user is unchecked
watch(() => form.value.create_user, (val) => {
  if (!val) form.value.role_id = '';
});

onMounted(async () => {
  await fetchRoles();
  await fetchEmployee();
});
</script>

<template>
  <div>
    <div class="flex items-center gap-4 mb-6">
      <Button variant="ghost" @click="router.push('/employees')">
        <ArrowLeftIcon class="w-5 h-5" />
      </Button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          {{ isEdit ? 'Edit Employee' : 'Add Employee' }}
        </h1>
        <p class="text-gray-500">
          {{ isEdit ? 'Update employee details' : 'Add a new employee to the temple' }}
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
      <!-- Basic Info -->
      <Card title="Basic Information">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Input
            v-model="form.name"
            label="Full Name *"
            placeholder="Enter full name"
            required
            :error="errors.name?.[0]"
          />
          <Input
            v-model="form.designation"
            label="Designation *"
            placeholder="e.g., Priest, Peon, Manager"
            required
            :error="errors.designation?.[0]"
          />
          <Input
            v-model="form.date_of_joining"
            label="Date of Joining *"
            type="date"
            required
            :error="errors.date_of_joining?.[0]"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
          <Input
            v-model="form.contact_number"
            label="Contact Number *"
            placeholder="10-digit mobile number"
            required
            :error="errors.contact_number?.[0] || (form.contact_number && !isValidIndianMobile(form.contact_number) ? mobileValidationMessage : '')"
            pattern="[6-9][0-9]{9}"
            maxlength="10"
            inputmode="tel"
          />
          <Input
            v-model="form.alternate_contact"
            label="Alternate Contact"
            placeholder="10-digit mobile number"
            :error="form.alternate_contact && !isValidIndianMobile(form.alternate_contact) ? mobileValidationMessage : ''"
            pattern="[6-9][0-9]{9}"
            maxlength="10"
            inputmode="tel"
          />
          <Input
            v-model="form.email"
            label="Email"
            type="email"
            placeholder="Optional"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
          <Input
            v-model="form.date_of_birth"
            label="Date of Birth"
            type="date"
          />
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
            <textarea
              v-model="form.address"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              placeholder="Enter address"
            ></textarea>
          </div>
        </div>
      </Card>

      <!-- Salary & Bank -->
      <Card title="Salary & Bank Details">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <Input
            v-model.number="form.basic_salary"
            label="Basic Salary *"
            type="number"
            min="0"
            step="0.01"
            required
            :error="errors.basic_salary?.[0]"
          />
          <Input
            v-model="form.bank_name"
            label="Bank Name"
            placeholder="e.g., State Bank of India"
          />
          <Input
            v-model="form.bank_account_number"
            label="Account Number"
            placeholder="Enter account number"
          />
          <Input
            v-model="form.ifsc_code"
            label="IFSC Code"
            placeholder="e.g., SBIN0001234"
          />
        </div>
      </Card>

      <!-- ID Proofs -->
      <Card title="ID Documents">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Input
            v-model="form.pan_number"
            label="PAN Number"
            placeholder="e.g., ABCDE1234F"
          />
          <Input
            v-model="form.aadhaar_number"
            label="Aadhaar Number"
            placeholder="e.g., 1234 5678 9012"
          />
        </div>
      </Card>

      <!-- App User -->
      <Card v-if="!isEdit" title="Application Access">
        <div class="space-y-4">
          <label class="flex items-center gap-3">
            <input
              v-model="form.create_user"
              type="checkbox"
              class="rounded text-primary-600 focus:ring-primary-500"
            />
            <div>
              <span class="font-medium text-gray-700">Create as Application User</span>
              <p class="text-sm text-gray-500">Employee can login to the application with their contact number</p>
            </div>
          </label>

          <div v-if="form.create_user" class="pl-6 border-l-2 border-primary-200">
            <div class="max-w-md">
              <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
              <select
                v-model="form.role_id"
                :required="form.create_user"
                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                <option value="">Select Role</option>
                <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.role_name }}</option>
              </select>
              <p class="mt-1 text-xs text-gray-500">Default password will be: Employee@123</p>
            </div>
          </div>
        </div>
      </Card>

      <!-- Notes -->
      <Card title="Additional Notes">
        <textarea
          v-model="form.notes"
          rows="3"
          class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          placeholder="Any additional notes..."
        ></textarea>
      </Card>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-4">
        <Button type="button" variant="outline" @click="router.push('/employees')">
          Cancel
        </Button>
        <Button type="submit" variant="primary" :loading="saving">
          {{ isEdit ? 'Update Employee' : 'Add Employee' }}
        </Button>
      </div>
    </form>

    <!-- Success Modal -->
    <Modal :show="showSuccessModal" title="Employee Added" @close="router.push('/employees')">
      <div class="text-center py-4">
        <div class="mx-auto flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
          <CheckCircleIcon class="w-10 h-10 text-green-600" />
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Employee Added Successfully!</h3>
        <p v-if="createdEmployee" class="text-gray-600">
          {{ createdEmployee.employee_code }} - {{ createdEmployee.name }}
        </p>
        <div v-if="defaultPassword" class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
          <p class="text-sm text-yellow-800">
            <strong>App User Created</strong><br>
            Login: {{ form.contact_number }}<br>
            Password: {{ defaultPassword }}
          </p>
        </div>
      </div>
      <div class="flex justify-center gap-3 pt-4 border-t">
        <Button variant="outline" @click="resetForm">Add Another</Button>
        <Button variant="primary" @click="router.push('/employees')">View All</Button>
      </div>
    </Modal>
  </div>
</template>
