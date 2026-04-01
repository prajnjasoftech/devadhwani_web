import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useUiStore = defineStore('ui', () => {
  const sidebarOpen = ref(true);
  const toasts = ref([]);

  function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
  }

  function showToast(message, type = 'success', duration = 3000) {
    const id = Date.now();
    toasts.value.push({ id, message, type });

    setTimeout(() => {
      removeToast(id);
    }, duration);
  }

  function removeToast(id) {
    const index = toasts.value.findIndex(t => t.id === id);
    if (index > -1) {
      toasts.value.splice(index, 1);
    }
  }

  return {
    sidebarOpen,
    toasts,
    toggleSidebar,
    showToast,
    removeToast,
  };
});
