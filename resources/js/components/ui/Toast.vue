<script setup>
import { useUiStore } from '@/stores/ui';
import { XMarkIcon, CheckCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';

const uiStore = useUiStore();
</script>

<template>
  <div class="fixed top-4 right-4 z-50 space-y-2">
    <TransitionGroup name="toast">
      <div
        v-for="toast in uiStore.toasts"
        :key="toast.id"
        class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg border"
        :class="{
          'bg-green-50 border-green-200 text-green-800': toast.type === 'success',
          'bg-red-50 border-red-200 text-red-800': toast.type === 'error',
          'bg-yellow-50 border-yellow-200 text-yellow-800': toast.type === 'warning',
          'bg-blue-50 border-blue-200 text-blue-800': toast.type === 'info',
        }"
      >
        <CheckCircleIcon v-if="toast.type === 'success'" class="w-5 h-5 text-green-500" />
        <ExclamationCircleIcon v-else-if="toast.type === 'error'" class="w-5 h-5 text-red-500" />
        <span class="text-sm font-medium">{{ toast.message }}</span>
        <button @click="uiStore.removeToast(toast.id)" class="ml-2 hover:opacity-70">
          <XMarkIcon class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>
