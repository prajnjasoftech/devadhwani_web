<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();

const form = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
});
const errors = ref({});
const loading = ref(false);

const handleReset = async () => {
  errors.value = {};
  loading.value = true;

  const result = await authStore.resetPassword(
    form.value.current_password,
    form.value.password,
    form.value.password_confirmation
  );

  if (result.success) {
    uiStore.showToast('Password reset successfully!', 'success');
    router.push('/');
  } else {
    errors.value.general = result.message;
  }

  loading.value = false;
};
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
    <div class="max-w-md w-full">
      <!-- Logo -->
      <div class="text-center mb-8">
        <img src="/images/logo.png" alt="Devadhwani" class="h-20 w-20 mx-auto" />
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Reset Your Password</h1>
        <p class="mt-2 text-sm text-gray-600">Please create a new password to continue</p>
      </div>

      <!-- Reset Form -->
      <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-8">
        <form @submit.prevent="handleReset" class="space-y-4">
          <div v-if="errors.general" class="p-3 bg-red-50 border border-red-200 rounded-md">
            <p class="text-sm text-red-600">{{ errors.general }}</p>
          </div>

          <Input
            v-model="form.current_password"
            label="Current Password"
            type="password"
            placeholder="Enter your current password"
            required
          />

          <Input
            v-model="form.password"
            label="New Password"
            type="password"
            placeholder="Enter your new password"
            required
          />

          <Input
            v-model="form.password_confirmation"
            label="Confirm New Password"
            type="password"
            placeholder="Confirm your new password"
            required
          />

          <div class="text-sm text-gray-500">
            Password must be at least 8 characters with uppercase, lowercase, and numbers.
          </div>

          <Button
            type="submit"
            variant="primary"
            size="lg"
            class="w-full"
            :loading="loading"
          >
            Reset Password
          </Button>
        </form>
      </div>
    </div>
  </div>
</template>
