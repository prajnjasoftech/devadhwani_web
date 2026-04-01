<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

const router = useRouter();
const authStore = useAuthStore();

const form = ref({
  contact_number: '',
  password: '',
});
const error = ref('');
const loading = ref(false);

const handleLogin = async () => {
  error.value = '';
  loading.value = true;

  const result = await authStore.login(form.value.contact_number, form.value.password);

  if (result.success) {
    if (result.mustResetPassword) {
      router.push('/reset-password');
    } else {
      router.push('/');
    }
  } else {
    error.value = result.message;
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
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Devadhwani</h1>
        <p class="mt-2 text-sm text-gray-600">Temple Management System</p>
      </div>

      <!-- Login Form -->
      <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-6">Sign in to your account</h2>

        <form @submit.prevent="handleLogin" class="space-y-4">
          <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-md">
            <p class="text-sm text-red-600">{{ error }}</p>
          </div>

          <Input
            v-model="form.contact_number"
            label="Contact Number"
            type="tel"
            placeholder="Enter your contact number"
            required
          />

          <Input
            v-model="form.password"
            label="Password"
            type="password"
            placeholder="Enter your password"
            required
          />

          <Button
            type="submit"
            variant="primary"
            size="lg"
            class="w-full"
            :loading="loading"
          >
            Sign In
          </Button>
        </form>
      </div>

      <p class="mt-6 text-center text-sm text-gray-500">
        Contact your administrator if you need help accessing your account.
      </p>
    </div>
  </div>
</template>
