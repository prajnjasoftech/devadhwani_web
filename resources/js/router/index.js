import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/Login.vue'),
    meta: { guest: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPassword.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('@/views/Dashboard.vue'),
      },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('@/views/profile/Profile.vue'),
      },
      {
        path: 'temples',
        name: 'temples',
        component: () => import('@/views/temples/TempleList.vue'),
        meta: { platformAdmin: true },
      },
      {
        path: 'temples/create',
        name: 'temples.create',
        component: () => import('@/views/temples/TempleForm.vue'),
        meta: { platformAdmin: true },
      },
      {
        path: 'temples/:id/edit',
        name: 'temples.edit',
        component: () => import('@/views/temples/TempleForm.vue'),
        meta: { platformAdmin: true },
      },
      {
        path: 'users',
        name: 'users',
        component: () => import('@/views/users/UserList.vue'),
        meta: { permission: 'users.read', templeUserOnly: true },
      },
      {
        path: 'users/create',
        name: 'users.create',
        component: () => import('@/views/users/UserForm.vue'),
        meta: { permission: 'users.create', templeUserOnly: true },
      },
      {
        path: 'users/:id/edit',
        name: 'users.edit',
        component: () => import('@/views/users/UserForm.vue'),
        meta: { permission: 'users.update', templeUserOnly: true },
      },
      {
        path: 'roles',
        name: 'roles',
        component: () => import('@/views/roles/RoleList.vue'),
        meta: { permission: 'roles.read', templeUserOnly: true },
      },
      {
        path: 'roles/create',
        name: 'roles.create',
        component: () => import('@/views/roles/RoleForm.vue'),
        meta: { permission: 'roles.create', templeUserOnly: true },
      },
      {
        path: 'roles/:id/edit',
        name: 'roles.edit',
        component: () => import('@/views/roles/RoleForm.vue'),
        meta: { permission: 'roles.update', templeUserOnly: true },
      },
      {
        path: 'temple-settings',
        name: 'temple-settings',
        component: () => import('@/views/temples/TempleSettings.vue'),
        meta: { templeUserOnly: true, superAdminOnly: true },
      },
      {
        path: 'deities',
        name: 'deities',
        component: () => import('@/views/deities/DeityList.vue'),
        meta: { permission: 'deities.read', templeUserOnly: true },
      },
      {
        path: 'deities/create',
        name: 'deities.create',
        component: () => import('@/views/deities/DeityForm.vue'),
        meta: { permission: 'deities.create', templeUserOnly: true },
      },
      {
        path: 'deities/:id/edit',
        name: 'deities.edit',
        component: () => import('@/views/deities/DeityForm.vue'),
        meta: { permission: 'deities.update', templeUserOnly: true },
      },
      {
        path: 'poojas',
        name: 'poojas',
        component: () => import('@/views/poojas/PoojaList.vue'),
        meta: { permission: 'poojas.read', templeUserOnly: true },
      },
      {
        path: 'poojas/create',
        name: 'poojas.create',
        component: () => import('@/views/poojas/PoojaForm.vue'),
        meta: { permission: 'poojas.create', templeUserOnly: true },
      },
      {
        path: 'poojas/:id/edit',
        name: 'poojas.edit',
        component: () => import('@/views/poojas/PoojaForm.vue'),
        meta: { permission: 'poojas.update', templeUserOnly: true },
      },
      {
        path: 'bookings',
        name: 'bookings',
        component: () => import('@/views/bookings/BookingList.vue'),
        meta: { permission: 'bookings.read', templeUserOnly: true },
      },
      {
        path: 'bookings/create',
        name: 'bookings.create',
        component: () => import('@/views/bookings/BookingForm.vue'),
        meta: { permission: 'bookings.create', templeUserOnly: true },
      },
      {
        path: 'bookings/:id',
        name: 'bookings.show',
        component: () => import('@/views/bookings/BookingDetail.vue'),
        meta: { permission: 'bookings.read', templeUserOnly: true },
      },
      {
        path: 'bookings/:id/edit',
        name: 'bookings.edit',
        component: () => import('@/views/bookings/BookingEdit.vue'),
        meta: { permission: 'bookings.update', templeUserOnly: true },
      },
      {
        path: 'daily-poojas',
        name: 'daily-poojas',
        component: () => import('@/views/daily-poojas/DailyPoojaList.vue'),
        meta: { permission: 'daily_poojas.read', templeUserOnly: true },
      },
      {
        path: 'devotees',
        name: 'devotees',
        component: () => import('@/views/devotees/DevoteeList.vue'),
        meta: { permission: 'bookings.read', templeUserOnly: true },
      },
      {
        path: 'devotees/:id',
        name: 'devotees.show',
        component: () => import('@/views/devotees/DevoteeDetail.vue'),
        meta: { permission: 'bookings.read', templeUserOnly: true },
      },
      // Purchases
      {
        path: 'purchases',
        name: 'purchases',
        component: () => import('@/views/purchases/PurchaseList.vue'),
        meta: { permission: 'purchases.read', templeUserOnly: true },
      },
      {
        path: 'purchases/new',
        name: 'purchases.create',
        component: () => import('@/views/purchases/PurchaseForm.vue'),
        meta: { permission: 'purchases.create', templeUserOnly: true },
      },
      {
        path: 'purchases/vendors',
        name: 'purchases.vendors',
        component: () => import('@/views/purchases/VendorList.vue'),
        meta: { permission: 'purchases.read', templeUserOnly: true },
      },
      {
        path: 'purchases/vendors/new',
        name: 'purchases.vendors.create',
        component: () => import('@/views/purchases/VendorForm.vue'),
        meta: { permission: 'purchases.create', templeUserOnly: true },
      },
      {
        path: 'purchases/vendors/:id/edit',
        name: 'purchases.vendors.edit',
        component: () => import('@/views/purchases/VendorForm.vue'),
        meta: { permission: 'purchases.update', templeUserOnly: true },
      },
      {
        path: 'purchases/:id/edit',
        name: 'purchases.edit',
        component: () => import('@/views/purchases/PurchaseForm.vue'),
        meta: { permission: 'purchases.update', templeUserOnly: true },
      },
      // Expenses
      {
        path: 'expenses',
        name: 'expenses',
        component: () => import('@/views/expenses/ExpenseList.vue'),
        meta: { permission: 'expenses.read', templeUserOnly: true },
      },
      {
        path: 'expenses/new',
        name: 'expenses.create',
        component: () => import('@/views/expenses/ExpenseForm.vue'),
        meta: { permission: 'expenses.create', templeUserOnly: true },
      },
      {
        path: 'expenses/categories',
        name: 'expenses.categories',
        component: () => import('@/views/expenses/ExpenseCategoryList.vue'),
        meta: { permission: 'expenses.read', templeUserOnly: true },
      },
      {
        path: 'expenses/categories/new',
        name: 'expenses.categories.create',
        component: () => import('@/views/expenses/ExpenseCategoryForm.vue'),
        meta: { permission: 'expenses.create', templeUserOnly: true },
      },
      {
        path: 'expenses/categories/:id/edit',
        name: 'expenses.categories.edit',
        component: () => import('@/views/expenses/ExpenseCategoryForm.vue'),
        meta: { permission: 'expenses.update', templeUserOnly: true },
      },
      {
        path: 'expenses/:id/edit',
        name: 'expenses.edit',
        component: () => import('@/views/expenses/ExpenseForm.vue'),
        meta: { permission: 'expenses.update', templeUserOnly: true },
      },
      // Donations
      {
        path: 'donations',
        name: 'donations',
        component: () => import('@/views/donations/DonationList.vue'),
        meta: { permission: 'donations.read', templeUserOnly: true },
      },
      {
        path: 'donations/new',
        name: 'donations.create',
        component: () => import('@/views/donations/DonationForm.vue'),
        meta: { permission: 'donations.create', templeUserOnly: true },
      },
      {
        path: 'donations/heads',
        name: 'donations.heads',
        component: () => import('@/views/donations/DonationHeadList.vue'),
        meta: { permission: 'donations.read', templeUserOnly: true },
      },
      {
        path: 'donations/asset-types',
        name: 'donations.asset-types',
        component: () => import('@/views/donations/AssetTypeList.vue'),
        meta: { permission: 'donations.read', templeUserOnly: true },
      },
      {
        path: 'donations/:id/edit',
        name: 'donations.edit',
        component: () => import('@/views/donations/DonationForm.vue'),
        meta: { permission: 'donations.update', templeUserOnly: true },
      },
      {
        path: 'donations/assets',
        name: 'donations.assets',
        component: () => import('@/views/donations/AssetList.vue'),
        meta: { permission: 'donations.read', templeUserOnly: true },
      },
      {
        path: 'donations/assets/new',
        name: 'donations.assets.create',
        component: () => import('@/views/donations/AssetForm.vue'),
        meta: { permission: 'donations.create', templeUserOnly: true },
      },
      {
        path: 'donations/assets/:id/edit',
        name: 'donations.assets.edit',
        component: () => import('@/views/donations/AssetForm.vue'),
        meta: { permission: 'donations.update', templeUserOnly: true },
      },
      // Accounts
      {
        path: 'accounts',
        name: 'accounts',
        component: () => import('@/views/accounts/AccountList.vue'),
        meta: { permission: 'accounts.read', templeUserOnly: true },
      },
      {
        path: 'accounts/setup',
        name: 'accounts.setup',
        component: () => import('@/views/accounts/AccountSetup.vue'),
        meta: { permission: 'accounts.create', templeUserOnly: true },
      },
      // Ledger
      {
        path: 'ledger',
        name: 'ledger',
        component: () => import('@/views/ledger/LedgerList.vue'),
        meta: { permission: 'ledger.read', templeUserOnly: true },
      },
      {
        path: 'ledger/statement',
        name: 'ledger.statement',
        component: () => import('@/views/ledger/AccountStatement.vue'),
        meta: { permission: 'ledger.read', templeUserOnly: true },
      },
      {
        path: 'ledger/balance-sheet',
        name: 'ledger.balance-sheet',
        component: () => import('@/views/ledger/BalanceSheet.vue'),
        meta: { permission: 'ledger.read', templeUserOnly: true },
      },
      // Calendar
      {
        path: 'calendar',
        name: 'calendar',
        component: () => import('@/views/calendar/CalendarView.vue'),
        meta: { templeUserOnly: true },
      },
      // Reports
      {
        path: 'reports',
        name: 'reports',
        component: () => import('@/views/reports/ReportList.vue'),
        meta: { templeUserOnly: true },
      },
      // Employees
      {
        path: 'employees',
        name: 'employees',
        component: () => import('@/views/employees/EmployeeList.vue'),
        meta: { permission: 'employees.read', templeUserOnly: true },
      },
      {
        path: 'employees/new',
        name: 'employees.create',
        component: () => import('@/views/employees/EmployeeForm.vue'),
        meta: { permission: 'employees.create', templeUserOnly: true },
      },
      {
        path: 'employees/salaries',
        name: 'employees.salaries',
        component: () => import('@/views/employees/EmployeeSalaryList.vue'),
        meta: { permission: 'employees.read', templeUserOnly: true },
      },
      {
        path: 'employees/payments',
        name: 'employees.payments',
        component: () => import('@/views/employees/EmployeePaymentList.vue'),
        meta: { permission: 'employees.read', templeUserOnly: true },
      },
      {
        path: 'employees/payments/new',
        name: 'employees.payments.create',
        component: () => import('@/views/employees/EmployeePaymentForm.vue'),
        meta: { permission: 'employees.create', templeUserOnly: true },
      },
      {
        path: 'employees/payments/:id/edit',
        name: 'employees.payments.edit',
        component: () => import('@/views/employees/EmployeePaymentForm.vue'),
        meta: { permission: 'employees.update', templeUserOnly: true },
      },
      {
        path: 'employees/:id/edit',
        name: 'employees.edit',
        component: () => import('@/views/employees/EmployeeForm.vue'),
        meta: { permission: 'employees.update', templeUserOnly: true },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Track if initial auth check has been done
let authChecked = false;

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  // On first load, if we have a token but no user data, fetch user data first
  if (!authChecked && authStore.isAuthenticated && !authStore.user) {
    await authStore.checkAuth();
    authChecked = true;
  }

  if (to.meta.guest && authStore.isAuthenticated) {
    return next({ name: 'dashboard' });
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login' });
  }

  if (authStore.isAuthenticated && authStore.mustResetPassword && to.name !== 'reset-password') {
    return next({ name: 'reset-password' });
  }

  if (to.meta.platformAdmin && !authStore.isPlatformAdmin) {
    return next({ name: 'dashboard' });
  }

  // Block platform admins from temple-user-only routes (users, roles)
  if (to.meta.templeUserOnly && authStore.isPlatformAdmin) {
    return next({ name: 'dashboard' });
  }

  // Temple Settings - only for Super Admin (system role)
  if (to.meta.superAdminOnly && !authStore.user?.role?.is_system_role) {
    return next({ name: 'dashboard' });
  }

  if (to.meta.permission && !authStore.hasPermission(to.meta.permission)) {
    return next({ name: 'dashboard' });
  }

  next();
});

export default router;
