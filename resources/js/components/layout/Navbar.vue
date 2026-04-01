<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import {
  UserCircleIcon,
  ArrowRightOnRectangleIcon,
  ChevronDownIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const showDropdown = ref(false);

const logout = async () => {
  await authStore.logout();
  router.push('/login');
};
</script>

<template>
  <header class="sticky top-0 z-30 bg-white border-b border-gray-200">
    <div class="flex items-center justify-between h-16 px-6">
      <div>
        <h1 class="text-lg font-semibold text-gray-900">
          {{ authStore.user?.temple?.temple_name || 'Platform Admin' }}
        </h1>
      </div>

      <div class="relative">
        <button
          @click="showDropdown = !showDropdown"
          class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <UserCircleIcon class="w-6 h-6 text-gray-500" />
          <span class="hidden sm:block">{{ authStore.user?.name }}</span>
          <ChevronDownIcon class="w-4 h-4" />
        </button>

        <Transition name="dropdown">
          <div
            v-if="showDropdown"
            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1"
            @click="showDropdown = false"
          >
            <div class="px-4 py-2 border-b border-gray-100">
              <p class="text-sm font-medium text-gray-900">{{ authStore.user?.name }}</p>
              <p class="text-xs text-gray-500">{{ authStore.user?.contact_number }}</p>
            </div>
            <router-link
              to="/profile"
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
            >
              <Cog6ToothIcon class="w-4 h-4" />
              My Profile
            </router-link>
            <button
              @click="logout"
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
            >
              <ArrowRightOnRectangleIcon class="w-4 h-4" />
              Logout
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
