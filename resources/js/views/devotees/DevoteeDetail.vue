<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import {
  ArrowLeftIcon,
  UserIcon,
  CalendarDaysIcon,
  CurrencyRupeeIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const uiStore = useUiStore();

const loading = ref(true);
const devotee = ref(null);
const bookingHistory = ref([]);

const fetchDevotee = async () => {
  loading.value = true;
  try {
    const response = await api.get(`/devotees/${route.params.id}`);
    devotee.value = response.data.data;
    bookingHistory.value = response.data.data.booking_history || [];
  } catch (error) {
    uiStore.showToast('Failed to fetch devotee', 'error');
    router.push('/devotees');
  } finally {
    loading.value = false;
  }
};

const getStatusColor = (status) => {
  switch (status) {
    case 'completed': return 'bg-green-100 text-green-700';
    case 'confirmed': return 'bg-blue-100 text-blue-700';
    case 'cancelled': return 'bg-gray-100 text-gray-500';
    default: return 'bg-gray-100 text-gray-700';
  }
};

onMounted(fetchDevotee);
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center gap-4 mb-6">
      <Button variant="ghost" @click="router.push('/devotees')">
        <ArrowLeftIcon class="w-5 h-5" />
      </Button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Devotee Details</h1>
        <p class="text-gray-500">View devotee information and booking history</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <div v-else-if="devotee" class="space-y-6">
      <!-- Devotee Info Card -->
      <Card>
        <div class="flex items-start gap-4">
          <div class="p-3 bg-primary-100 rounded-full">
            <UserIcon class="w-8 h-8 text-primary-600" />
          </div>
          <div class="flex-1">
            <h2 class="text-xl font-bold text-gray-900">{{ devotee.name }}</h2>
            <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <p class="text-sm text-gray-500">Nakshathra</p>
                <p class="font-medium">
                  {{ devotee.nakshathra?.malayalam_name || '-' }}
                  <span v-if="devotee.nakshathra" class="text-gray-500 text-sm">
                    ({{ devotee.nakshathra.name }})
                  </span>
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Gothram</p>
                <p class="font-medium">{{ devotee.gothram || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Total Bookings</p>
                <p class="font-medium text-primary-600">{{ bookingHistory.length }}</p>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Booking History -->
      <Card title="Booking History">
        <div v-if="bookingHistory.length === 0" class="text-center py-8 text-gray-500">
          No bookings found for this devotee.
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="booking in bookingHistory"
            :key="booking.id"
            class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition-colors"
            @click="router.push(`/bookings/${booking.booking_id}`)"
          >
            <div class="flex items-start justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-mono font-medium text-primary-600">
                    {{ booking.booking_number }}
                  </span>
                  <span
                    :class="['px-2 py-0.5 text-xs font-medium rounded-full', getStatusColor(booking.booking_status)]"
                  >
                    {{ booking.booking_status }}
                  </span>
                </div>
                <p class="text-gray-700 mt-1">
                  {{ booking.pooja_name }} - {{ booking.deity_name }}
                </p>
                <div class="flex items-center gap-4 mt-2 text-sm text-gray-500">
                  <span class="flex items-center gap-1">
                    <CalendarDaysIcon class="w-4 h-4" />
                    {{ booking.scheduled_date }}
                  </span>
                  <span>{{ booking.frequency_label }}</span>
                </div>
              </div>
              <div class="text-right">
                <p class="font-medium">{{ booking.amount_formatted }}</p>
                <p class="text-xs text-gray-500">{{ booking.booking_date }}</p>
              </div>
            </div>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>
