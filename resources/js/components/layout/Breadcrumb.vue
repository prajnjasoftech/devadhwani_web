<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { HomeIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();

// Label mappings for route segments
const labelMap = {
  'dashboard': 'Dashboard',
  'profile': 'Profile',
  'temples': 'Temples',
  'temple-settings': 'Temple Settings',
  'users': 'Users',
  'roles': 'Roles',
  'deities': 'Deities',
  'poojas': 'Poojas',
  'bookings': 'Bookings',
  'daily-poojas': 'Daily Poojas',
  'devotees': 'Devotees',
  'purchases': 'Purchases',
  'vendors': 'Vendors',
  'expenses': 'Expenses',
  'categories': 'Categories',
  'donations': 'Donations',
  'heads': 'Donation Heads',
  'asset-types': 'Asset Types',
  'assets': 'Assets',
  'accounts': 'Accounts',
  'setup': 'Setup',
  'ledger': 'Ledger',
  'statement': 'Account Statement',
  'balance-sheet': 'Balance Sheet',
  'employees': 'Employees',
  'salaries': 'Salaries',
  'payments': 'Payments',
  'reports': 'Reports',
  'create': 'New',
  'new': 'New',
  'edit': 'Edit',
};

const breadcrumbs = computed(() => {
  const path = route.path;

  // Don't show breadcrumbs on dashboard
  if (path === '/' || path === '') {
    return [];
  }

  const segments = path.split('/').filter(Boolean);
  const crumbs = [];
  let currentPath = '';

  for (let i = 0; i < segments.length; i++) {
    const segment = segments[i];
    currentPath += '/' + segment;

    // Skip dynamic segments (IDs) - they start with a number or are UUIDs
    if (/^\d+$/.test(segment) || /^[a-f0-9-]{36}$/i.test(segment) || segment.includes('/')) {
      continue;
    }

    // Check if this looks like an ID (alphanumeric with slashes like TMP0001/2026/000001)
    if (/^[A-Z]{2,}/.test(segment) && segment.length > 10) {
      continue;
    }

    const label = labelMap[segment] || formatLabel(segment);
    const isLast = i === segments.length - 1;

    // Check if route exists
    const routeExists = router.resolve(currentPath).matched.length > 0;

    crumbs.push({
      label,
      path: currentPath,
      isLast,
      clickable: !isLast && routeExists,
    });
  }

  return crumbs;
});

function formatLabel(segment) {
  // Convert kebab-case or snake_case to Title Case
  return segment
    .replace(/[-_]/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function navigate(crumb) {
  if (crumb.clickable) {
    router.push(crumb.path);
  }
}
</script>

<template>
  <nav v-if="breadcrumbs.length > 0" aria-label="Breadcrumb" class="flex items-center text-sm text-gray-500 mb-4 print:hidden">
    <router-link to="/" class="flex items-center hover:text-primary-600 transition-colors">
      <HomeIcon class="w-4 h-4" />
    </router-link>

    <template v-for="(crumb, index) in breadcrumbs" :key="index">
      <ChevronRightIcon class="w-4 h-4 mx-2 text-gray-400" />
      <span
        v-if="crumb.isLast"
        class="text-gray-900 font-medium"
      >
        {{ crumb.label }}
      </span>
      <button
        v-else-if="crumb.clickable"
        @click="navigate(crumb)"
        class="hover:text-primary-600 transition-colors"
      >
        {{ crumb.label }}
      </button>
      <span v-else class="text-gray-500">
        {{ crumb.label }}
      </span>
    </template>
  </nav>
</template>
