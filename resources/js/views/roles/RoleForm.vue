<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

const route = useRoute();
const router = useRouter();
const uiStore = useUiStore();

const isEdit = computed(() => !!route.params.id);
const loading = ref(false);
const saving = ref(false);
const permissionModules = ref([]);

const form = ref({
  role_name: '',
  description: '',
  permissions: [],
});

const errors = ref({});

const fetchPermissions = async () => {
  try {
    const response = await api.get('/permissions');
    permissionModules.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch permissions:', error);
  }
};

const fetchRole = async () => {
  if (!isEdit.value) return;

  loading.value = true;
  try {
    const response = await api.get(`/roles/${route.params.id}`);
    const data = response.data.data;
    form.value.role_name = data.role_name;
    form.value.description = data.description || '';
    form.value.permissions = data.permission_ids || [];
  } catch (error) {
    uiStore.showToast('Failed to fetch role', 'error');
    router.push('/roles');
  } finally {
    loading.value = false;
  }
};

const togglePermission = (permissionId) => {
  const index = form.value.permissions.indexOf(permissionId);
  if (index > -1) {
    form.value.permissions.splice(index, 1);
  } else {
    form.value.permissions.push(permissionId);
  }
};

const toggleModule = (module) => {
  const modulePermissionIds = module.permissions.map((p) => p.id);
  const allSelected = modulePermissionIds.every((id) => form.value.permissions.includes(id));

  if (allSelected) {
    form.value.permissions = form.value.permissions.filter(
      (id) => !modulePermissionIds.includes(id)
    );
  } else {
    const newPermissions = new Set([...form.value.permissions, ...modulePermissionIds]);
    form.value.permissions = Array.from(newPermissions);
  }
};

const isModuleSelected = (module) => {
  return module.permissions.every((p) => form.value.permissions.includes(p.id));
};

const isModulePartial = (module) => {
  const selected = module.permissions.filter((p) => form.value.permissions.includes(p.id));
  return selected.length > 0 && selected.length < module.permissions.length;
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    if (isEdit.value) {
      await api.put(`/roles/${route.params.id}`, form.value);
      uiStore.showToast('Role updated successfully', 'success');
    } else {
      await api.post('/roles', form.value);
      uiStore.showToast('Role created successfully', 'success');
    }
    router.push('/roles');
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      uiStore.showToast('Failed to save role', 'error');
    }
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchPermissions();
  fetchRole();
});
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isEdit ? 'Edit Role' : 'Add New Role' }}
      </h1>
      <p class="text-gray-500">
        {{ isEdit ? 'Update role permissions' : 'Create a new role with specific permissions' }}
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="handleSubmit">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Card title="Role Details">
          <div class="space-y-4">
            <Input
              v-model="form.role_name"
              label="Role Name"
              required
              :error="errors.role_name?.[0]"
            />
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Brief description of this role..."
              ></textarea>
            </div>
          </div>
        </Card>

        <Card title="Permissions" class="lg:col-span-2">
          <div class="space-y-6">
            <div v-for="module in permissionModules" :key="module.module_key" class="border border-gray-200 rounded-lg overflow-hidden">
              <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                <label class="flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    :checked="isModuleSelected(module)"
                    :indeterminate="isModulePartial(module)"
                    @change="toggleModule(module)"
                    class="w-4 h-4 text-primary-500 border-gray-300 rounded focus:ring-primary-500"
                  />
                  <span class="ml-2 font-medium text-gray-900">{{ module.module_name }}</span>
                </label>
              </div>
              <div class="px-4 py-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                <label
                  v-for="permission in module.permissions"
                  :key="permission.id"
                  class="flex items-center cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :checked="form.permissions.includes(permission.id)"
                    @change="togglePermission(permission.id)"
                    class="w-4 h-4 text-primary-500 border-gray-300 rounded focus:ring-primary-500"
                  />
                  <span class="ml-2 text-sm text-gray-600 capitalize">{{ permission.action }}</span>
                </label>
              </div>
            </div>
          </div>
          <p v-if="errors.permissions" class="mt-2 text-sm text-red-600">{{ errors.permissions[0] }}</p>
        </Card>
      </div>

      <div class="mt-6 flex items-center justify-end gap-4">
        <Button variant="outline" type="button" @click="router.push('/roles')">
          Cancel
        </Button>
        <Button variant="primary" type="submit" :loading="saving">
          {{ isEdit ? 'Update Role' : 'Create Role' }}
        </Button>
      </div>
    </form>
  </div>
</template>
