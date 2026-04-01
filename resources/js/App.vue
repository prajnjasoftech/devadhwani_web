<script setup>
import { useAuthStore } from '@/stores/auth';
import { onMounted } from 'vue';
import Toast from '@/components/ui/Toast.vue';

const authStore = useAuthStore();

onMounted(async () => {
  await authStore.checkAuth();
});
</script>

<template>
  <router-view />
  <Toast />
</template>
