<script setup>
import { useUiStore } from '@/stores/ui';
import Sidebar from './Sidebar.vue';
import Navbar from './Navbar.vue';
import Breadcrumb from './Breadcrumb.vue';

const uiStore = useUiStore();
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <Sidebar />
    <div
      class="transition-all duration-300 flex flex-col flex-1"
      :class="uiStore.sidebarOpen ? 'lg:ml-64' : 'lg:ml-20'"
    >
      <Navbar />
      <main class="p-6 flex-1">
        <Breadcrumb />
        <router-view />
      </main>
      <footer class="py-3 px-6 text-center text-sm text-gray-500 border-t border-gray-200 bg-white">
        Powered by
        <a
          href="https://www.prajnja.com"
          target="_blank"
          rel="noopener noreferrer"
          class="text-primary-600 hover:text-primary-700 hover:underline font-medium"
        >
          Prajnja Softech LLP
        </a>
      </footer>
    </div>
  </div>
</template>
