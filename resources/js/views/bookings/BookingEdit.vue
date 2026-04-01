<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const uiStore = useUiStore();

const loading = ref(true);
const saving = ref(false);
const booking = ref(null);
const errors = ref({});

const form = ref({
  contact_name: '',
  contact_number: '',
  contact_address: '',
  prasadam_required: false,
  notes: '',
});

// Check if contact is required
const contactRequired = computed(() => {
  if (!booking.value) return false;
  const hasRecurring = booking.value.items?.some(item => item.frequency !== 'once');
  return booking.value.balance_amount > 0 || hasRecurring || form.value.prasadam_required;
});

const contactRequiredReason = computed(() => {
  if (!booking.value) return '';
  const reasons = [];
  if (booking.value.balance_amount > 0) reasons.push('pending payment');
  const hasRecurring = booking.value.items?.some(item => item.frequency !== 'once');
  if (hasRecurring) reasons.push('recurring poojas');
  if (form.value.prasadam_required) reasons.push('sending prasadam');
  return reasons.join(', ');
});

const isValid = computed(() => {
  if (contactRequired.value) {
    if (!form.value.contact_name || !form.value.contact_number) {
      return false;
    }
    if (form.value.prasadam_required && !form.value.contact_address) {
      return false;
    }
  }
  return true;
});

const fetchBooking = async () => {
  loading.value = true;
  try {
    const response = await api.get(`/bookings/${route.params.id}`);
    booking.value = response.data.data;

    // Populate form with existing data
    form.value = {
      contact_name: booking.value.contact_name || '',
      contact_number: booking.value.contact_number || '',
      contact_address: booking.value.contact_address || '',
      prasadam_required: booking.value.prasadam_required || false,
      notes: booking.value.notes || '',
    };
  } catch (error) {
    uiStore.showToast('Failed to fetch booking', 'error');
    router.push('/bookings');
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  errors.value = {};
  saving.value = true;

  try {
    await api.put(`/bookings/${route.params.id}`, form.value);
    uiStore.showToast('Booking updated successfully', 'success');
    router.push(`/bookings/${route.params.id}`);
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
      if (error.response.data.message) {
        uiStore.showToast(error.response.data.message, 'error');
      }
    } else {
      uiStore.showToast(error.response?.data?.message || 'Failed to update booking', 'error');
    }
  } finally {
    saving.value = false;
  }
};

onMounted(fetchBooking);
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center gap-4 mb-6">
      <Button variant="ghost" @click="router.push(`/bookings/${route.params.id}`)">
        <ArrowLeftIcon class="w-5 h-5" />
      </Button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Edit Booking</h1>
        <p v-if="booking" class="text-gray-500 font-mono">{{ booking.booking_number }}</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <!-- Cancelled Warning -->
    <div v-else-if="booking?.booking_status === 'cancelled'" class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
      <p class="text-red-700 font-medium">This booking has been cancelled and cannot be edited.</p>
      <Button variant="outline" class="mt-4" @click="router.push(`/bookings/${route.params.id}`)">
        Back to Booking
      </Button>
    </div>

    <form v-else-if="booking" @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Booking Summary (Read-only) -->
      <Card title="Booking Summary">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <p class="text-sm text-gray-500">Booking Date</p>
            <p class="font-medium">{{ booking.booking_date_formatted }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Total Amount</p>
            <p class="font-medium">{{ booking.total_amount_formatted }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Balance</p>
            <p class="font-medium" :class="booking.balance_amount > 0 ? 'text-red-600' : 'text-green-600'">
              {{ booking.balance_amount_formatted }}
            </p>
          </div>
        </div>

        <!-- Items Summary -->
        <div class="mt-4 pt-4 border-t">
          <p class="text-sm font-medium text-gray-700 mb-2">Items</p>
          <div class="space-y-2">
            <div v-for="item in booking.items" :key="item.id" class="flex justify-between text-sm">
              <span>{{ item.pooja?.name }} - {{ item.deity?.name }}</span>
              <span class="text-gray-500">{{ item.frequency_label }}</span>
            </div>
          </div>
        </div>
      </Card>

      <!-- Editable Contact Details -->
      <Card title="Contact Details">
        <!-- Contact Required Info -->
        <div v-if="contactRequired" class="mb-4 p-3 bg-orange-50 rounded-lg text-sm text-orange-700">
          Contact details required for {{ contactRequiredReason }}
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Input
            v-model="form.contact_name"
            :label="contactRequired ? 'Contact Name *' : 'Contact Name'"
            placeholder="Full name"
            :required="contactRequired"
            :error="errors.contact_name?.[0]"
          />
          <Input
            v-model="form.contact_number"
            :label="contactRequired ? 'Contact Number *' : 'Contact Number'"
            placeholder="Mobile number"
            :required="contactRequired"
            :error="errors.contact_number?.[0]"
          />
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ form.prasadam_required ? 'Delivery Address *' : 'Address' }}
            </label>
            <textarea
              v-model="form.contact_address"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              :placeholder="form.prasadam_required ? 'Full delivery address for prasadam' : 'Address (optional)'"
              :required="form.prasadam_required"
            ></textarea>
          </div>
        </div>

        <!-- Prasadam Option -->
        <div class="mt-6 flex items-center gap-3 p-4 bg-orange-50 rounded-lg">
          <input
            v-model="form.prasadam_required"
            type="checkbox"
            id="prasadam"
            class="w-5 h-5 text-primary-500 border-gray-300 rounded focus:ring-primary-500"
          />
          <label for="prasadam" class="text-sm font-medium text-gray-700">
            Prasadam needs to be sent to devotee
          </label>
        </div>
      </Card>

      <!-- Notes -->
      <Card title="Notes">
        <textarea
          v-model="form.notes"
          rows="3"
          class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          placeholder="Any additional notes..."
        ></textarea>
      </Card>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-4">
        <Button type="button" variant="outline" @click="router.push(`/bookings/${route.params.id}`)">
          Cancel
        </Button>
        <Button type="submit" variant="primary" :loading="saving" :disabled="!isValid">
          Save Changes
        </Button>
      </div>
    </form>
  </div>
</template>
