import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/composables/useApi';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const token = ref(localStorage.getItem('token') || null);
  const loading = ref(false);

  const isAuthenticated = computed(() => !!token.value);
  const isPlatformAdmin = computed(() => user.value?.user_type === 'platform_admin');
  const isSuperAdmin = computed(() => user.value?.role?.is_system_role === true);
  const mustResetPassword = computed(() => user.value?.must_reset_password ?? false);
  const permissions = computed(() => user.value?.permissions ?? []);

  function hasPermission(permission) {
    // Super Admin (system role) has all permissions
    if (isSuperAdmin.value) {
      return true;
    }
    return permissions.value.includes(permission);
  }

  async function login(contactNumber, password) {
    loading.value = true;
    try {
      const response = await api.post('/auth/login', {
        contact_number: contactNumber,
        password,
      });

      if (response.data.success) {
        token.value = response.data.data.token;
        user.value = response.data.data.user;
        // Add permissions to user object from login response
        user.value.permissions = response.data.data.permissions || [];
        localStorage.setItem('token', token.value);
        return { success: true, mustResetPassword: response.data.data.must_reset_password };
      }

      return { success: false, message: response.data.message };
    } catch (error) {
      return { success: false, message: error.response?.data?.message || 'Login failed' };
    } finally {
      loading.value = false;
    }
  }

  async function logout() {
    try {
      await api.post('/auth/logout');
    } catch (error) {
      // Ignore logout errors
    } finally {
      token.value = null;
      user.value = null;
      localStorage.removeItem('token');
    }
  }

  async function checkAuth() {
    if (!token.value) return;

    try {
      const response = await api.get('/auth/me');
      if (response.data.success) {
        user.value = response.data.data;
      } else {
        await logout();
      }
    } catch (error) {
      await logout();
    }
  }

  async function resetPassword(currentPassword, newPassword, confirmPassword) {
    loading.value = true;
    try {
      const response = await api.post('/auth/reset-password', {
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      });

      if (response.data.success) {
        user.value.must_reset_password = false;
        return { success: true };
      }

      return { success: false, message: response.data.message };
    } catch (error) {
      return { success: false, message: error.response?.data?.message || 'Password reset failed' };
    } finally {
      loading.value = false;
    }
  }

  return {
    user,
    token,
    loading,
    isAuthenticated,
    isPlatformAdmin,
    isSuperAdmin,
    mustResetPassword,
    permissions,
    hasPermission,
    login,
    logout,
    checkAuth,
    resetPassword,
  };
});
