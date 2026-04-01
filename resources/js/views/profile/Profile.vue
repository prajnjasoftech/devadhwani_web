<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import {
  UserCircleIcon,
  KeyIcon,
  PhoneIcon,
  EnvelopeIcon,
  IdentificationIcon,
  BuildingLibraryIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const uiStore = useUiStore();

const loading = ref(false);
const savingProfile = ref(false);
const changingPassword = ref(false);

const profileForm = ref({
  name: '',
  email: '',
  address: '',
});

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const initForm = () => {
  profileForm.value = {
    name: authStore.user?.name || '',
    email: authStore.user?.email || '',
    address: authStore.user?.address || '',
  };
};

const saveProfile = async () => {
  savingProfile.value = true;
  try {
    const response = await api.put('/auth/profile', profileForm.value);
    if (response.data.success) {
      // Update the auth store with new user data
      authStore.user.name = profileForm.value.name;
      authStore.user.email = profileForm.value.email;
      authStore.user.address = profileForm.value.address;
      uiStore.showToast('Profile updated successfully', 'success');
    }
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Failed to update profile', 'error');
  } finally {
    savingProfile.value = false;
  }
};

const changePassword = async () => {
  if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
    uiStore.showToast('Passwords do not match', 'error');
    return;
  }

  changingPassword.value = true;
  try {
    const result = await authStore.resetPassword(
      passwordForm.value.current_password,
      passwordForm.value.password,
      passwordForm.value.password_confirmation
    );

    if (result.success) {
      uiStore.showToast('Password changed successfully', 'success');
      passwordForm.value = {
        current_password: '',
        password: '',
        password_confirmation: '',
      };
    } else {
      uiStore.showToast(result.message || 'Failed to change password', 'error');
    }
  } catch (error) {
    uiStore.showToast('Failed to change password', 'error');
  } finally {
    changingPassword.value = false;
  }
};

onMounted(() => {
  initForm();
});
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
      <p class="text-gray-500">View and manage your account settings</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Profile Info Card -->
      <Card class="lg:col-span-1">
        <div class="text-center">
          <div class="flex justify-center mb-4">
            <div class="w-24 h-24 bg-primary-100 rounded-full flex items-center justify-center">
              <UserCircleIcon class="w-16 h-16 text-primary-600" />
            </div>
          </div>
          <h3 class="text-lg font-semibold text-gray-900">{{ authStore.user?.name }}</h3>
          <p class="text-sm text-gray-500">{{ authStore.user?.role?.role_name || 'User' }}</p>

          <div class="mt-6 space-y-3 text-left">
            <div class="flex items-center gap-3 text-sm">
              <PhoneIcon class="w-5 h-5 text-gray-400" />
              <span class="text-gray-600">{{ authStore.user?.contact_number }}</span>
            </div>
            <div v-if="authStore.user?.email" class="flex items-center gap-3 text-sm">
              <EnvelopeIcon class="w-5 h-5 text-gray-400" />
              <span class="text-gray-600">{{ authStore.user?.email }}</span>
            </div>
            <div v-if="authStore.user?.temple" class="flex items-center gap-3 text-sm">
              <BuildingLibraryIcon class="w-5 h-5 text-gray-400" />
              <span class="text-gray-600">{{ authStore.user?.temple?.temple_name }}</span>
            </div>
            <div class="flex items-center gap-3 text-sm">
              <ShieldCheckIcon class="w-5 h-5 text-gray-400" />
              <span class="text-gray-600">{{ authStore.user?.role?.role_name || 'Platform Admin' }}</span>
            </div>
          </div>
        </div>
      </Card>

      <!-- Edit Profile & Change Password -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Edit Profile -->
        <Card>
          <template #header>
            <div class="flex items-center gap-2">
              <IdentificationIcon class="w-5 h-5 text-primary-600" />
              <span class="font-semibold">Edit Profile</span>
            </div>
          </template>

          <form @submit.prevent="saveProfile" class="space-y-4">
            <Input
              v-model="profileForm.name"
              label="Full Name"
              required
            />
            <Input
              v-model="profileForm.email"
              label="Email Address"
              type="email"
            />
            <Input
              v-model="profileForm.address"
              label="Address"
            />

            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm text-gray-600">
              <strong>Note:</strong> Contact number cannot be changed as it is used for login.
            </div>

            <div class="flex justify-end">
              <Button type="submit" variant="primary" :loading="savingProfile">
                Save Changes
              </Button>
            </div>
          </form>
        </Card>

        <!-- Change Password -->
        <Card>
          <template #header>
            <div class="flex items-center gap-2">
              <KeyIcon class="w-5 h-5 text-primary-600" />
              <span class="font-semibold">Change Password</span>
            </div>
          </template>

          <form @submit.prevent="changePassword" class="space-y-4">
            <Input
              v-model="passwordForm.current_password"
              label="Current Password"
              type="password"
              required
            />
            <Input
              v-model="passwordForm.password"
              label="New Password"
              type="password"
              required
            />
            <Input
              v-model="passwordForm.password_confirmation"
              label="Confirm New Password"
              type="password"
              required
            />

            <div class="flex justify-end">
              <Button type="submit" variant="primary" :loading="changingPassword">
                Change Password
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </div>
  </div>
</template>
