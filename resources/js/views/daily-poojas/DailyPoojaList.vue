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
  PrinterIcon,
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

const printSchedule = () => {
  window.print();
};

watch(selectedDate, fetchSchedules);

onMounted(fetchSchedules);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6 print:hidden">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Daily Poojas</h1>
        <p class="text-gray-500">Manage daily pooja schedules</p>
      </div>
      <Button v-if="data?.poojas?.length" variant="secondary" @click="printSchedule">
        <PrinterIcon class="w-4 h-4 mr-2" />
        Print
      </Button>
    </div>

    <!-- Date Navigation -->
    <Card class="mb-6 print:hidden">
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
    <div v-if="data?.summary?.pending > 0 && authStore.hasPermission('daily_poojas.update')" class="flex items-center justify-between mb-4 print:hidden">
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
    <div v-if="loading" class="flex items-center justify-center py-12 print:hidden">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <!-- Empty State -->
    <div v-else-if="!data?.poojas?.length" class="text-center py-12 print:hidden">
      <CalendarDaysIcon class="w-12 h-12 mx-auto text-gray-400 mb-4" />
      <h3 class="text-lg font-medium text-gray-900">No poojas scheduled</h3>
      <p class="text-gray-500">There are no poojas scheduled for this date.</p>
    </div>

    <!-- Pooja List (Screen View) -->
    <div v-else class="space-y-4 print:hidden">
      <Card v-for="pooja in data.poojas" :key="`${pooja.pooja_id}-${pooja.deity_id}`">
        <!-- Header -->
        <div class="flex items-start justify-between mb-3">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ pooja.pooja_name }}</h3>
            <p class="text-sm text-gray-500">{{ pooja.deity_name }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-sm font-medium">
              {{ pooja.pending_count }} pending
            </span>
            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-sm font-medium">
              {{ pooja.completed_count }} done
            </span>
          </div>
        </div>

        <!-- Devotee List -->
        <div class="border rounded-lg divide-y">
          <template v-for="schedule in pooja.schedules" :key="schedule.id">
            <!-- Show devotees with nakshatra -->
            <template v-if="schedule.beneficiaries_with_nakshatra?.length">
              <div
                v-for="(ben, bIdx) in schedule.beneficiaries_with_nakshatra"
                :key="`${schedule.id}-${bIdx}`"
                class="flex items-center justify-between px-3 py-2"
                :class="schedule.status === 'completed' ? 'bg-green-50' : 'hover:bg-gray-50'"
              >
                <div class="flex items-center gap-3">
                  <input
                    v-if="schedule.status === 'pending' && authStore.hasPermission('daily_poojas.update') && bIdx === 0"
                    type="checkbox"
                    :checked="selectedSchedules.includes(schedule.id)"
                    @change="toggleSchedule(schedule.id)"
                    class="w-4 h-4 text-primary-500 border-gray-300 rounded focus:ring-primary-500"
                  />
                  <CheckCircleIcon
                    v-else-if="schedule.status === 'completed' && bIdx === 0"
                    class="w-4 h-4 text-green-500"
                  />
                  <div v-else class="w-4"></div>
                  <span class="font-medium text-gray-900">{{ ben.name }}</span>
                </div>
                <div class="flex items-center gap-3">
                  <span v-if="ben.nakshathra" class="text-sm text-gray-600">{{ ben.nakshathra }}</span>
                  <Button
                    v-if="schedule.status === 'pending' && authStore.hasPermission('daily_poojas.update') && bIdx === 0"
                    variant="ghost"
                    size="sm"
                    @click.stop="completeSingle(schedule.id)"
                    class="text-xs"
                  >
                    Done
                  </Button>
                </div>
              </div>
            </template>
            <!-- Show quantity if no devotees -->
            <div
              v-else
              class="flex items-center justify-between px-3 py-2"
              :class="schedule.status === 'completed' ? 'bg-green-50' : 'hover:bg-gray-50'"
            >
              <div class="flex items-center gap-3">
                <input
                  v-if="schedule.status === 'pending' && authStore.hasPermission('daily_poojas.update')"
                  type="checkbox"
                  :checked="selectedSchedules.includes(schedule.id)"
                  @change="toggleSchedule(schedule.id)"
                  class="w-4 h-4 text-primary-500 border-gray-300 rounded focus:ring-primary-500"
                />
                <CheckCircleIcon
                  v-else-if="schedule.status === 'completed'"
                  class="w-4 h-4 text-green-500"
                />
                <ClockIcon v-else class="w-4 h-4 text-gray-400" />
                <span class="text-gray-600">Qty: {{ schedule.quantity || 1 }}</span>
              </div>
              <Button
                v-if="schedule.status === 'pending' && authStore.hasPermission('daily_poojas.update')"
                variant="ghost"
                size="sm"
                @click.stop="completeSingle(schedule.id)"
                class="text-xs"
              >
                Done
              </Button>
            </div>
          </template>
        </div>

        <!-- Footer with select all -->
        <div v-if="pooja.pending_count > 0 && authStore.hasPermission('daily_poojas.update')" class="mt-3 flex justify-end">
          <button
            @click="toggleAllInPooja(pooja)"
            class="text-sm text-primary-600 hover:text-primary-700"
          >
            Select all pending
          </button>
        </div>
      </Card>
    </div>

    <!-- Print View (Thermal Printer Friendly) -->
    <div v-if="data?.poojas?.length" class="hidden print:block print-view">
      <div class="text-center mb-4 border-b border-dashed border-gray-400 pb-2">
        <div class="font-bold text-lg">Daily Pooja Schedule</div>
        <div class="text-sm">{{ formattedDate }}</div>
      </div>

      <div v-for="pooja in data.poojas" :key="`print-${pooja.pooja_id}-${pooja.deity_id}`" class="mb-4 pb-2 border-b border-dashed border-gray-300">
        <div class="font-bold">{{ pooja.pooja_name }}</div>
        <div class="text-sm text-gray-600 mb-1">{{ pooja.deity_name }}</div>

        <template v-for="(schedule, idx) in pooja.schedules.filter(s => s.status === 'pending')" :key="schedule.id">
          <div class="text-sm pl-2">
            <template v-if="schedule.beneficiaries_with_nakshatra?.length">
              <div v-for="(ben, bIdx) in schedule.beneficiaries_with_nakshatra" :key="bIdx" class="flex justify-between">
                <span>{{ ben.name }}</span>
                <span v-if="ben.nakshathra" class="text-gray-600">{{ ben.nakshathra }}</span>
              </div>
            </template>
            <div v-else class="text-gray-600">
              Qty: {{ schedule.quantity || 1 }}
            </div>
          </div>
        </template>

        <div class="text-right text-xs text-gray-500 mt-1">
          Total: {{ pooja.pending_count }} pending
        </div>
      </div>

      <div class="text-center text-xs text-gray-500 mt-4 pt-2 border-t border-dashed border-gray-400">
        Powered by Prajnja Softech LLP
      </div>
    </div>
  </div>
</template>

<style>
@media print {
  @page {
    size: 80mm auto;
    margin: 2mm;
  }

  .print-view {
    font-family: monospace;
    font-size: 12px;
    line-height: 1.3;
    max-width: 76mm;
    padding: 0;
  }

  .print-view .font-bold {
    font-weight: bold;
  }

  .print-view .text-sm {
    font-size: 11px;
  }

  .print-view .text-xs {
    font-size: 10px;
  }
}
</style>
