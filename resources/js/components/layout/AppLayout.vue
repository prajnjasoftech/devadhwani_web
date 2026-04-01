<script setup>
import { useUiStore } from '@/stores/ui';
import Sidebar from './Sidebar.vue';
import Navbar from './Navbar.vue';
import Breadcrumb from './Breadcrumb.vue';

const uiStore = useUiStore();
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <Sidebar />
    <div
      class="transition-all duration-300"
      :class="uiStore.sidebarOpen ? 'lg:ml-64' : 'lg:ml-20'"
    >
      <Navbar />
      <main class="p-6">
        <Breadcrumb />
        <router-view />
      </main>
    </div>
  </div>
</template>
