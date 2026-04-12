<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import {
  HomeIcon,
  BuildingLibraryIcon,
  UsersIcon,
  UserGroupIcon,
  ShieldCheckIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  Cog6ToothIcon,
  SparklesIcon,
  FireIcon,
  BookOpenIcon,
  CalendarDaysIcon,
  ShoppingCartIcon,
  BanknotesIcon,
  WalletIcon,
  GiftIcon,
  IdentificationIcon,
  DocumentTextIcon,
  ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const authStore = useAuthStore();
const uiStore = useUiStore();

const navigation = computed(() => {
  const items = [
    { name: 'Dashboard', href: '/', icon: HomeIcon, permission: null },
  ];

  // Platform Admin only sees Temples
  if (authStore.isPlatformAdmin) {
    items.push({ name: 'Temples', href: '/temples', icon: BuildingLibraryIcon, permission: null });
    return items;
  }

  // Temple users see modules based on permissions
  if (authStore.hasPermission('deities.read')) {
    items.push({ name: 'Deities', href: '/deities', icon: SparklesIcon, permission: 'deities.read' });
  }

  if (authStore.hasPermission('poojas.read')) {
    items.push({ name: 'Poojas', href: '/poojas', icon: FireIcon, permission: 'poojas.read' });
  }

  if (authStore.hasPermission('bookings.read')) {
    items.push({ name: 'Bookings', href: '/bookings', icon: BookOpenIcon, permission: 'bookings.read' });
  }

  if (authStore.hasPermission('daily_poojas.read')) {
    items.push({ name: 'Daily Poojas', href: '/daily-poojas', icon: CalendarDaysIcon, permission: 'daily_poojas.read' });
  }

  if (authStore.hasPermission('purchases.read')) {
    items.push({ name: 'Purchases', href: '/purchases', icon: ShoppingCartIcon, permission: 'purchases.read' });
  }

  if (authStore.hasPermission('expenses.read')) {
    items.push({ name: 'Expenses', href: '/expenses', icon: BanknotesIcon, permission: 'expenses.read' });
  }

  if (authStore.hasPermission('donations.read')) {
    items.push({ name: 'Donations', href: '/donations', icon: GiftIcon, permission: 'donations.read' });
  }

  if (authStore.hasPermission('employees.read')) {
    items.push({ name: 'Employees', href: '/employees', icon: IdentificationIcon, permission: 'employees.read' });
  }

  if (authStore.hasPermission('bookings.read')) {
    items.push({ name: 'Devotees', href: '/devotees', icon: UserGroupIcon, permission: 'bookings.read' });
  }

  if (authStore.hasPermission('users.read')) {
    items.push({ name: 'Users', href: '/users', icon: UsersIcon, permission: 'users.read' });
  }

  if (authStore.hasPermission('roles.read')) {
    items.push({ name: 'Roles', href: '/roles', icon: ShieldCheckIcon, permission: 'roles.read' });
  }

  // Accounts & Ledger - role-based permissions
  if (authStore.hasPermission('accounts.read')) {
    items.push({ name: 'Accounts', href: '/accounts', icon: WalletIcon, permission: 'accounts.read' });
  }

  if (authStore.hasPermission('ledger.read')) {
    items.push({ name: 'Ledger', href: '/ledger', icon: DocumentTextIcon, permission: 'ledger.read' });
  }

  // Calendar - available to all temple users
  items.push({ name: 'Calendar', href: '/calendar', icon: CalendarDaysIcon, permission: null });

  // Reports - available to all temple users
  items.push({ name: 'Reports', href: '/reports', icon: ClipboardDocumentListIcon, permission: null });

  // Temple Settings - only for Super Admin (system role)
  if (authStore.user?.role?.is_system_role) {
    items.push({ name: 'Temple Settings', href: '/temple-settings', icon: Cog6ToothIcon, permission: null });
  }

  return items;
});

const isActive = (href) => {
  if (href === '/') return route.path === '/';

  // Exact match
  if (route.path === href) return true;

  // For nested routes like /purchases/vendors, only match exact or its children
  // Don't let /purchases match /purchases/vendors
  if (route.path.startsWith(href + '/')) {
    // Check if there's a more specific menu item that matches
    const moreSpecificMatch = navigation.value.some(
      item => item.href !== href && item.href.startsWith(href) && route.path.startsWith(item.href)
    );
    return !moreSpecificMatch;
  }

  return false;
};
</script>

<template>
  <aside
    class="fixed top-0 left-0 z-40 h-screen transition-all duration-300 bg-gray-800"
    :class="uiStore.sidebarOpen ? 'w-64' : 'w-20'"
  >
    <!-- Logo -->
    <div class="flex items-center h-16 px-4 border-b border-gray-700">
      <img src="/images/logo.png" alt="Devadhwani" class="h-10 w-10" />
      <span
        v-if="uiStore.sidebarOpen"
        class="ml-3 text-xl font-semibold text-white"
      >
        Devadhwani
      </span>
    </div>

    <!-- Navigation -->
    <nav class="mt-4 px-3">
      <ul class="space-y-1">
        <li v-for="item in navigation" :key="item.name">
          <router-link
            :to="item.href"
            class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="[
              isActive(item.href)
                ? 'bg-primary-500 text-white'
                : 'text-gray-300 hover:bg-gray-700 hover:text-white',
            ]"
          >
            <component :is="item.icon" class="w-5 h-5 shrink-0" />
            <span v-if="uiStore.sidebarOpen" class="ml-3">{{ item.name }}</span>
          </router-link>
        </li>
      </ul>
    </nav>

    <!-- Toggle Button -->
    <button
      @click="uiStore.toggleSidebar"
      class="absolute bottom-4 -right-3 flex items-center justify-center w-6 h-6 bg-gray-800 border border-gray-600 rounded-full text-gray-400 hover:text-white transition-colors"
    >
      <ChevronLeftIcon v-if="uiStore.sidebarOpen" class="w-4 h-4" />
      <ChevronRightIcon v-else class="w-4 h-4" />
    </button>
  </aside>
</template>
