<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import {
  CalendarDaysIcon,
  CheckCircleIcon,
  ClockIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  UserIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const uiStore = useUiStore();

const loading = ref(true);
const data = ref(null);
const selectedDate = ref(new Date().toISOString().split('T')[0]);
const selectedSchedules = ref([]);
const completing = ref(false);

const formattedDate = computed(() => {
  const date = new Date(selectedDate.value);
  return date.toLocaleDateString('en-IN', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
});

const isToday = computed(() => {
  return selectedDate.value === new Date().toISOString().split('T')[0];
});

const hasSelectedPending = computed(() => {
  return selectedSchedules.value.length > 0;
});

const fetchSchedules = async () => {
  loading.value = true;
  selectedSchedules.value = [];
  try {
    const response = await api.get('/daily-poojas', {
      params: { date: selectedDate.value },
    });
    data.value = response.data.data;
  } catch (error) {
    uiStore.showToast('Failed to fetch schedules', 'error');
  } finally {
    loading.value = false;
  }
};

const previousDay = () => {
  const date = new Date(selectedDate.value);
  date.setDate(date.getDate() - 1);
  selectedDate.value = date.toISOString().split('T')[0];
};

const nextDay = () => {
  const date = new Date(selectedDate.value);
  date.setDate(date.getDate() + 1);
  selectedDate.value = date.toISOString().split('T')[0];
};

const goToToday = () => {
  selectedDate.value = new Date().toISOString().split('T')[0];
};

const toggleSchedule = (scheduleId) => {
  const index = selectedSchedules.value.indexOf(scheduleId);
  if (index === -1) {
    selectedSchedules.value.push(scheduleId);
  } else {
    selectedSchedules.value.splice(index, 1);
  }
};

const toggleAllInPooja = (pooja) => {
  const pendingIds = pooja.schedules
    .filter(s => s.status === 'pending')
    .map(s => s.id);

  const allSelected = pendingIds.every(id => selectedSchedules.value.includes(id));

  if (allSelected) {
    selectedSchedules.value = selectedSchedules.value.filter(id => !pendingIds.includes(id));
  } else {
    pendingIds.forEach(id => {
      if (!selectedSchedules.value.includes(id)) {
        selectedSchedules.value.push(id);
      }
    });
  }
};

const selectAllPending = () => {
  if (!data.value?.poojas) return;

  const allPendingIds = [];
  data.value.poojas.forEach(pooja => {
    pooja.schedules.forEach(s => {
      if (s.status === 'pending') {
        allPendingIds.push(s.id);
      }
    });
  });

  selectedSchedules.value = allPendingIds;
};

const completeSelected = async () => {
  if (!hasSelectedPending.value) return;

  completing.value = true;
  try {
    await api.post('/daily-poojas/batch-complete', {
      schedule_ids: selectedSchedules.value,
    });
    uiStore.showToast(`${selectedSchedules.value.length} poojas marked as completed`, 'success');
    selectedSchedules.value = [];
    await fetchSchedules();
  } catch (error) {
    uiStore.showToast('Failed to complete poojas', 'error');
  } finally {
    completing.value = false;
  }
};

const completeSingle = async (scheduleId) => {
  try {
    await api.post(`/daily-poojas/${scheduleId}/complete`);
    uiStore.showToast('Pooja marked as completed', 'success');
    await fetchSchedules();
  } catch (error) {
    uiStore.showToast('Failed to complete pooja', 'error');
  }
};

watch(selectedDate, fetchSchedules);

onMounted(fetchSchedules);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Daily Poojas</h1>
        <p class="text-gray-500">Manage daily pooja schedules</p>
      </div>
    </div>

    <!-- Date Navigation -->
    <Card class="mb-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <button
            @click="previousDay"
            class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <ChevronLeftIcon class="w-5 h-5" />
          </button>
          <div class="flex items-center gap-3">
            <CalendarDaysIcon class="w-6 h-6 text-primary-500" />
            <div>
              <input
                v-model="selectedDate"
                type="date"
                class="text-lg font-semibold text-gray-900 border-0 p-0 focus:ring-0"
              />
              <p class="text-sm text-gray-500">{{ formattedDate }}</p>
            </div>
          </div>
          <button
            @click="nextDay"
            class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <ChevronRightIcon class="w-5 h-5" />
          </button>
        </div>
        <div class="flex items-center gap-3">
          <Button v-if="!isToday" variant="outline" size="sm" @click="goToToday">
            Today
          </Button>
          <div v-if="data?.summary" class="flex items-center gap-4 text-sm">
            <span class="flex items-center gap-1">
              <ClockIcon class="w-4 h-4 text-yellow-500" />
              {{ data.summary.pending }} pending
            </span>
            <span class="flex items-center gap-1">
              <CheckCircleIcon class="w-4 h-4 text-green-500" />
              {{ data.summary.completed }} completed
            </span>
          </div>
        </div>
      </div>
    </Card>

    <!-- Batch Actions -->
    <div v-if="data?.summary?.pending > 0 && authStore.hasPermission('daily_poojas.update')" class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <Button variant="outline" size="sm" @click="selectAllPending">
          Select All Pending
        </Button>
        <span v-if="selectedSchedules.length > 0" class="text-sm text-gray-600">
          {{ selectedSchedules.length }} selected
        </span>
      </div>
      <Button
        v-if="hasSelectedPending"
        variant="primary"
        :loading="completing"
        @click="completeSelected"
      >
        <CheckCircleIcon class="w-5 h-5 mr-2" />
        Mark Selected Complete
      </Button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <!-- Empty State -->
    <div v-else-if="!data?.poojas?.length" class="text-center py-12">
      <CalendarDaysIcon class="w-12 h-12 mx-auto text-gray-400 mb-4" />
      <h3 class="text-lg font-medium text-gray-900">No poojas scheduled</h3>
      <p class="text-gray-500">There are no poojas scheduled for this date.</p>
    </div>

    <!-- Pooja List -->
    <div v-else class="space-y-6">
      <Card v-for="pooja in data.poojas" :key="`${pooja.pooja_id}-${pooja.deity_id}`">
        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ pooja.pooja_name }}</h3>
            <p class="text-gray-500">{{ pooja.deity_name }}</p>
          </div>
          <div class="flex items-center gap-3">
            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
              {{ pooja.total_count }} total
            </span>
            <button
              v-if="pooja.pending_count > 0 && authStore.hasPermission('daily_poojas.update')"
              @click="toggleAllInPooja(pooja)"
              class="text-sm text-primary-600 hover:text-primary-700"
            >
              Select all pending
            </button>
          </div>
        </div>

        <div class="space-y-3">
          <div
            v-for="schedule in pooja.schedules"
            :key="schedule.id"
            class="flex items-center justify-between p-3 rounded-lg transition-colors"
            :class="[
              schedule.status === 'completed'
                ? 'bg-green-50'
                : selectedSchedules.includes(schedule.id)
                ? 'bg-primary-50 border border-primary-200'
                : 'bg-gray-50 hover:bg-gray-100',
            ]"
          >
            <div class="flex items-center gap-4">
              <!-- Checkbox for pending -->
              <input
                v-if="schedule.status === 'pending' && authStore.hasPermission('daily_poojas.update')"
                type="checkbox"
                :checked="selectedSchedules.includes(schedule.id)"
                @change="toggleSchedule(schedule.id)"
                class="w-5 h-5 text-primary-500 border-gray-300 rounded focus:ring-primary-500"
              />
              <CheckCircleIcon
                v-else-if="schedule.status === 'completed'"
                class="w-5 h-5 text-green-500"
              />
              <ClockIcon v-else class="w-5 h-5 text-gray-400" />

              <div>
                <div class="flex items-center gap-2">
                  <span class="font-mono text-sm text-primary-600">
                    {{ schedule.booking_number }}
                  </span>
                  <span
                    class="px-2 py-0.5 text-xs font-medium rounded"
                    :class="schedule.status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
                  >
                    {{ schedule.status }}
                  </span>
                </div>
                <div v-if="schedule.beneficiaries?.length" class="flex items-center gap-2 mt-1">
                  <UserIcon class="w-4 h-4 text-gray-400" />
                  <span class="text-sm text-gray-600">
                    {{ schedule.beneficiaries.join(', ') }}
                  </span>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <Button
                v-if="schedule.status === 'pending' && authStore.hasPermission('daily_poojas.update')"
                variant="ghost"
                size="sm"
                @click.stop="completeSingle(schedule.id)"
              >
                <CheckCircleIcon class="w-4 h-4 mr-1" />
                Complete
              </Button>
              <span v-if="schedule.completed_at" class="text-xs text-gray-500">
                {{ schedule.completed_at_formatted }}
              </span>
            </div>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t flex items-center justify-between text-sm">
          <span class="text-gray-500">
            {{ pooja.completed_count }} of {{ pooja.total_count }} completed
          </span>
          <div class="w-32 h-2 bg-gray-200 rounded-full overflow-hidden">
            <div
              class="h-full bg-green-500 transition-all"
              :style="{ width: `${(pooja.completed_count / pooja.total_count) * 100}%` }"
            ></div>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>
