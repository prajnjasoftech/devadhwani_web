<script setup>
import { ref, computed, onMounted, watch, reactive } from 'vue';
import { useUiStore } from '@/stores/ui';
import api from '@/composables/useApi';
import Card from '@/components/ui/Card.vue';
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  SunIcon,
  MoonIcon,
  CalendarIcon,
} from '@heroicons/vue/24/outline';

const uiStore = useUiStore();

const loading = ref(false);
const loadingPanchang = ref(false);
const currentDate = ref(new Date());
const monthData = ref(null);
const selectedDate = ref(null);
const panchangData = ref(null);
const todayPanchang = ref(null);
const fetchedPanchang = reactive({});

// Malayalam month names
const malayalamMonths = {
  1: 'ചിങ്ങം',
  2: 'കന്നി',
  3: 'തുലാം',
  4: 'വൃശ്ചികം',
  5: 'ധനു',
  6: 'മകരം',
  7: 'കുംഭം',
  8: 'മീനം',
  9: 'മേടം',
  10: 'ഇടവം',
  11: 'മിഥുനം',
  12: 'കർക്കടകം',
};

// English month names
const englishMonths = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];

// Malayalam day names
const malayalamDayNames = ['ഞായർ', 'തിങ്കൾ', 'ചൊവ്വ', 'ബുധൻ', 'വ്യാഴം', 'വെള്ളി', 'ശനി'];

const currentYear = computed(() => currentDate.value.getFullYear());
const currentMonth = computed(() => currentDate.value.getMonth() + 1);
const currentMonthName = computed(() => englishMonths[currentDate.value.getMonth()]);

// Get Malayalam month range for header
const malayalamMonthRange = computed(() => {
  if (!monthData.value?.days) return '';
  const days = Object.values(monthData.value.days);
  const firstDay = days[0]?.malayalam_date;
  const lastDay = days[days.length - 1]?.malayalam_date;

  if (!firstDay || !lastDay) return '';

  const firstMonth = malayalamMonths[firstDay.month] || firstDay.month_name;
  const lastMonth = malayalamMonths[lastDay.month] || lastDay.month_name;
  const year = firstDay.year;

  if (firstDay.month === lastDay.month) {
    return `${firstMonth} ${year}`;
  }
  return `${firstMonth} - ${lastMonth} ${year}`;
});

const today = computed(() => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
});

const isToday = (date) => date === today.value;

const calendarDays = computed(() => {
  const year = currentYear.value;
  const month = currentMonth.value;

  const firstDay = new Date(year, month - 1, 1);
  const startingDay = firstDay.getDay();
  const daysInMonth = new Date(year, month, 0).getDate();
  const prevMonth = month === 1 ? 12 : month - 1;
  const prevYear = month === 1 ? year - 1 : year;
  const daysInPrevMonth = new Date(prevYear, prevMonth, 0).getDate();

  const days = [];

  // Previous month days
  for (let i = startingDay - 1; i >= 0; i--) {
    const day = daysInPrevMonth - i;
    const date = `${prevYear}-${String(prevMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    days.push({ day, date, isCurrentMonth: false, malayalamDate: null });
  }

  // Current month days
  for (let day = 1; day <= daysInMonth; day++) {
    const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const dayData = monthData.value?.days?.[day];
    days.push({
      day,
      date,
      isCurrentMonth: true,
      malayalamDate: dayData?.malayalam_date,
    });
  }

  // Next month days
  const remainingDays = 42 - days.length;
  const nextMonth = month === 12 ? 1 : month + 1;
  const nextYear = month === 12 ? year + 1 : year;

  for (let day = 1; day <= remainingDays; day++) {
    const date = `${nextYear}-${String(nextMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    days.push({ day, date, isCurrentMonth: false, malayalamDate: null });
  }

  return days;
});

const fetchMonthData = async () => {
  loading.value = true;
  try {
    const response = await api.get('/calendar/month', {
      params: { year: currentYear.value, month: currentMonth.value },
    });
    monthData.value = response.data.data;

    // Set today's panchang if available
    if (response.data.data.today_panchang) {
      todayPanchang.value = response.data.data.today_panchang;
      fetchedPanchang[today.value] = response.data.data.today_panchang;
      // Auto-select today and show panchang
      selectedDate.value = today.value;
      panchangData.value = response.data.data.today_panchang;
    }
  } catch (error) {
    console.error('Failed to fetch month data:', error);
  } finally {
    loading.value = false;
  }
};

const fetchPanchang = async (date) => {
  // Check if already fetched
  if (fetchedPanchang[date]) {
    panchangData.value = fetchedPanchang[date];
    return;
  }

  loadingPanchang.value = true;
  panchangData.value = null;
  try {
    const response = await api.get('/calendar/panchang', { params: { date } });
    panchangData.value = response.data.data;
    fetchedPanchang[date] = response.data.data;
  } catch (error) {
    uiStore.showToast('Failed to fetch Panchang data', 'error');
  } finally {
    loadingPanchang.value = false;
  }
};

const selectDate = (day) => {
  if (!day.isCurrentMonth) return;
  selectedDate.value = day.date;
  fetchPanchang(day.date);
};

const previousMonth = () => {
  const newDate = new Date(currentDate.value);
  newDate.setMonth(newDate.getMonth() - 1);
  currentDate.value = newDate;
};

const nextMonth = () => {
  const newDate = new Date(currentDate.value);
  newDate.setMonth(newDate.getMonth() + 1);
  currentDate.value = newDate;
};

const goToToday = () => {
  currentDate.value = new Date();
  selectedDate.value = today.value;
  if (fetchedPanchang[today.value]) {
    panchangData.value = fetchedPanchang[today.value];
  } else {
    fetchPanchang(today.value);
  }
};

const formatTime = (timeStr) => {
  if (!timeStr) return '-';
  try {
    const date = new Date(timeStr);
    if (!isNaN(date)) {
      return date.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
  } catch (e) {
    return timeStr;
  }
  return timeStr;
};

const selectedDateFormatted = computed(() => {
  if (!selectedDate.value) return 'Select a date';
  const date = new Date(selectedDate.value);
  return date.toLocaleDateString('en-IN', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
});

watch([currentYear, currentMonth], fetchMonthData);
onMounted(fetchMonthData);
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-4 bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl p-4 text-white">
      <div class="flex items-center justify-between">
        <button @click="previousMonth" class="p-2 hover:bg-white/20 rounded-lg transition-colors">
          <ChevronLeftIcon class="w-6 h-6" />
        </button>
        <div class="text-center">
          <h1 class="text-2xl font-bold">{{ currentMonthName }} {{ currentYear }}</h1>
        </div>
        <button @click="nextMonth" class="p-2 hover:bg-white/20 rounded-lg transition-colors">
          <ChevronRightIcon class="w-6 h-6" />
        </button>
      </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- Calendar (Left - 2/3) -->
      <div class="lg:col-span-2">
        <Card class="overflow-hidden relative">
          <!-- Loading Overlay -->
          <div v-if="loading" class="absolute inset-0 bg-white/50 flex items-center justify-center z-10">
            <svg class="animate-spin h-8 w-8 text-orange-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </div>

          <!-- Day Headers -->
          <div class="grid grid-cols-7 bg-gray-100 border-b">
            <div
              v-for="(day, index) in malayalamDayNames"
              :key="day"
              class="text-center py-3 font-semibold text-sm"
              :class="index === 0 ? 'text-red-600 bg-red-50' : 'text-gray-700'"
            >
              {{ day }}
            </div>
          </div>

          <!-- Calendar Days -->
          <div class="grid grid-cols-7">
            <button
              v-for="(day, index) in calendarDays"
              :key="index"
              @click="selectDate(day)"
              :disabled="!day.isCurrentMonth"
              class="min-h-[85px] p-1 text-left border-b border-r transition-all relative"
              :class="[
                day.isCurrentMonth
                  ? 'hover:bg-orange-50 cursor-pointer'
                  : 'bg-gray-50 opacity-40 cursor-not-allowed',
                isToday(day.date) ? 'bg-yellow-100' : '',
                index % 7 === 0 ? 'bg-red-50/50' : '',
                selectedDate === day.date ? 'ring-2 ring-orange-500 bg-orange-50' : '',
              ]"
            >
              <!-- English Date (Big) -->
              <div
                class="text-xl font-bold"
                :class="[
                  index % 7 === 0 && day.isCurrentMonth ? 'text-red-600' : 'text-gray-800',
                  isToday(day.date) ? 'bg-orange-500 text-white rounded-full w-7 h-7 flex items-center justify-center' : ''
                ]"
              >
                {{ day.day }}
              </div>

              <!-- Malayalam Date (Small Red) -->
              <div v-if="day.malayalamDate" class="absolute top-1 right-1">
                <span class="text-sm font-semibold text-red-500">
                  {{ day.malayalamDate.day }}
                </span>
              </div>

              <!-- Month Name (if day 1) -->
              <div v-if="day.malayalamDate && day.malayalamDate.day === 1" class="text-xs text-orange-600 font-medium mt-1">
                {{ day.malayalamDate.month_name }}
              </div>

              <!-- Panchang preview in cell (if fetched) -->
              <div v-if="fetchedPanchang[day.date]" class="mt-1 text-[10px] leading-tight">
                <div v-if="fetchedPanchang[day.date].nakshatra?.[0]" class="text-indigo-600 font-medium truncate">
                  {{ fetchedPanchang[day.date].nakshatra[0].name }}
                </div>
              </div>
            </button>
          </div>

          <!-- Legend -->
          <div class="p-3 bg-gray-50 border-t text-xs text-gray-600">
            <p><strong>Big numbers</strong> - English date, <strong class="text-red-500">Small red</strong> - Malayalam date</p>
          </div>
        </Card>
      </div>

      <!-- Panchang Details (Right - 1/3) -->
      <div class="space-y-4">
        <!-- Selected Date Details -->
        <Card>
          <div class="border-b pb-3 mb-3 -mx-4 -mt-4 px-4 pt-4 bg-gray-50">
            <h3 class="font-semibold text-gray-900">{{ selectedDateFormatted }}</h3>
          </div>

          <!-- Loading State -->
          <div v-if="loadingPanchang" class="flex flex-col items-center justify-center py-8">
            <svg class="animate-spin h-8 w-8 text-orange-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-2 text-sm text-gray-500">Fetching panchang...</p>
          </div>

          <!-- Panchang Data -->
          <div v-else-if="panchangData" class="space-y-3">
            <!-- Malayalam Date -->
            <div v-if="panchangData.malayalam_date" class="text-center pb-3 border-b">
              <div class="text-3xl font-bold text-red-600">{{ panchangData.malayalam_date.day }}</div>
              <div class="text-lg text-gray-700">{{ panchangData.malayalam_date.month_name }}</div>
              <div class="text-sm text-gray-500">{{ panchangData.malayalam_date.year }} {{ panchangData.malayalam_date.year_name }}</div>
            </div>

            <!-- Vaara -->
            <div v-if="panchangData.vaara" class="flex justify-between py-2 border-b">
              <span class="text-gray-600 font-medium">വാരം</span>
              <span class="text-orange-600 font-semibold">{{ panchangData.vaara }}</span>
            </div>

            <!-- Tithi -->
            <div v-if="panchangData.tithi?.[0]" class="flex justify-between py-2 border-b">
              <span class="text-gray-600 font-medium">തിഥി</span>
              <span class="text-green-700 font-medium text-right">{{ panchangData.tithi[0].name }}</span>
            </div>

            <!-- Nakshatra -->
            <div v-if="panchangData.nakshatra?.length" class="py-2 border-b">
              <div class="flex justify-between">
                <span class="text-gray-600 font-medium">നക്ഷത്രം</span>
                <div class="text-right">
                  <div class="text-yellow-700 font-medium">
                    {{ panchangData.nakshatra[0].name }}
                    <span class="text-xs text-gray-500 ml-1">{{ formatTime(panchangData.nakshatra[0].end) }} വരെ</span>
                  </div>
                  <div v-if="panchangData.nakshatra[1]" class="text-yellow-700 font-medium mt-1">
                    {{ panchangData.nakshatra[1].name }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Yoga -->
            <div v-if="panchangData.yoga?.[0]" class="flex justify-between py-2 border-b">
              <span class="text-gray-600 font-medium">യോഗം</span>
              <span class="text-purple-700 font-medium text-right">{{ panchangData.yoga[0].name }}</span>
            </div>

            <!-- Karana -->
            <div v-if="panchangData.karana?.[0]" class="flex justify-between py-2 border-b">
              <span class="text-gray-600 font-medium">കരണം</span>
              <span class="text-pink-700 font-medium text-right">{{ panchangData.karana[0].name }}</span>
            </div>

            <!-- Sun Times -->
            <div class="flex justify-between py-2 border-b">
              <span class="text-gray-600 font-medium flex items-center gap-1">
                <SunIcon class="w-4 h-4 text-orange-500" /> Sun
              </span>
              <span class="text-gray-700 text-sm">
                {{ formatTime(panchangData.sunrise) }} - {{ formatTime(panchangData.sunset) }}
              </span>
            </div>

            <!-- Moon Times -->
            <div class="flex justify-between py-2">
              <span class="text-gray-600 font-medium flex items-center gap-1">
                <MoonIcon class="w-4 h-4 text-blue-500" /> Moon
              </span>
              <span class="text-gray-700 text-sm">
                {{ formatTime(panchangData.moonrise) }} - {{ formatTime(panchangData.moonset) }}
              </span>
            </div>
          </div>

          <!-- No Selection State -->
          <div v-else class="text-center py-8">
            <CalendarIcon class="w-12 h-12 text-gray-300 mx-auto mb-2" />
            <p class="text-gray-500 text-sm">Click on a date to view panchang</p>
          </div>
        </Card>

        <!-- Today's Highlights (only show if todayPanchang exists and not already selected) -->
        <Card v-if="todayPanchang && selectedDate !== today">
          <div class="border-b pb-3 mb-3 -mx-4 -mt-4 px-4 pt-4 bg-orange-50">
            <h3 class="font-semibold text-orange-800">Today's Highlights</h3>
          </div>

          <div class="space-y-3">
            <div class="flex items-center gap-3">
              <SunIcon class="w-6 h-6 text-orange-500 flex-shrink-0" />
              <div>
                <div class="text-xs text-gray-500">Sunrise / Sunset</div>
                <div class="font-medium text-sm">{{ formatTime(todayPanchang.sunrise) }} - {{ formatTime(todayPanchang.sunset) }}</div>
              </div>
            </div>

            <div v-if="todayPanchang.tithi?.[0]" class="flex items-center gap-3">
              <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                <span class="text-green-700 text-xs font-bold">T</span>
              </div>
              <div>
                <div class="text-xs text-gray-500">Tithi</div>
                <div class="font-medium text-sm text-green-700">{{ todayPanchang.tithi[0].name }}</div>
              </div>
            </div>

            <div v-if="todayPanchang.nakshatra?.length" class="flex items-center gap-3">
              <div class="w-6 h-6 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0">
                <span class="text-yellow-700 text-xs font-bold">N</span>
              </div>
              <div>
                <div class="text-xs text-gray-500">Nakshatra</div>
                <div class="font-medium text-sm text-yellow-700">
                  {{ todayPanchang.nakshatra[0].name }}
                  <span class="text-xs text-gray-500">{{ formatTime(todayPanchang.nakshatra[0].end) }} വരെ</span>
                </div>
                <div v-if="todayPanchang.nakshatra[1]" class="font-medium text-sm text-yellow-700">
                  {{ todayPanchang.nakshatra[1].name }}
                </div>
              </div>
            </div>
          </div>

          <button
            @click="goToToday"
            class="mt-3 w-full text-center text-sm text-orange-600 hover:text-orange-700 font-medium"
          >
            View full details →
          </button>
        </Card>

        <!-- Auspicious/Inauspicious Timings (when date selected) -->
        <Card v-if="panchangData && (Object.keys(panchangData.auspicious || {}).length || Object.keys(panchangData.inauspicious || {}).length)">
          <!-- Auspicious -->
          <div v-if="Object.keys(panchangData.auspicious || {}).length">
            <h4 class="text-sm font-semibold text-emerald-700 mb-2">ശുഭ മുഹൂർത്തം</h4>
            <div class="space-y-2 text-sm">
              <div v-if="panchangData.auspicious.brahma_muhurat" class="flex justify-between text-xs">
                <span class="text-gray-700 font-medium">ബ്രഹ്മ മുഹൂർത്തം</span>
                <span class="text-emerald-600">{{ formatTime(panchangData.auspicious.brahma_muhurat.start) }} - {{ formatTime(panchangData.auspicious.brahma_muhurat.end) }}</span>
              </div>
              <div v-if="panchangData.auspicious.abhijit_muhurat" class="flex justify-between text-xs">
                <span class="text-gray-700 font-medium">അഭിജിത്ത് മുഹൂർത്തം</span>
                <span class="text-emerald-600">{{ formatTime(panchangData.auspicious.abhijit_muhurat.start) }} - {{ formatTime(panchangData.auspicious.abhijit_muhurat.end) }}</span>
              </div>
            </div>
          </div>

          <!-- Inauspicious -->
          <div v-if="Object.keys(panchangData.inauspicious || {}).length" :class="{ 'mt-4 pt-4 border-t': Object.keys(panchangData.auspicious || {}).length }">
            <h4 class="text-sm font-semibold text-red-700 mb-2">അശുഭ സമയം</h4>
            <div class="space-y-2 text-sm">
              <div v-if="panchangData.inauspicious.rahu_kaal" class="flex justify-between text-xs">
                <span class="text-gray-700 font-medium">രാഹു കാലം</span>
                <span class="text-red-600">{{ formatTime(panchangData.inauspicious.rahu_kaal.start) }} - {{ formatTime(panchangData.inauspicious.rahu_kaal.end) }}</span>
              </div>
              <div v-if="panchangData.inauspicious.gulika_kaal" class="flex justify-between text-xs">
                <span class="text-gray-700 font-medium">ഗുളിക കാലം</span>
                <span class="text-red-600">{{ formatTime(panchangData.inauspicious.gulika_kaal.start) }} - {{ formatTime(panchangData.inauspicious.gulika_kaal.end) }}</span>
              </div>
              <div v-if="panchangData.inauspicious.yamaganda_kaal" class="flex justify-between text-xs">
                <span class="text-gray-700 font-medium">യമഘണ്ട കാലം</span>
                <span class="text-red-600">{{ formatTime(panchangData.inauspicious.yamaganda_kaal.start) }} - {{ formatTime(panchangData.inauspicious.yamaganda_kaal.end) }}</span>
              </div>
              <div v-if="panchangData.inauspicious.dur_muhurat" class="flex justify-between text-xs">
                <span class="text-gray-700 font-medium">ദുർമുഹൂർത്തം</span>
                <span class="text-red-600">{{ formatTime(panchangData.inauspicious.dur_muhurat.start) }} - {{ formatTime(panchangData.inauspicious.dur_muhurat.end) }}</span>
              </div>
              <div v-if="panchangData.inauspicious.varjyam" class="flex justify-between text-xs">
                <span class="text-gray-700 font-medium">വർജ്യം</span>
                <span class="text-red-600">{{ formatTime(panchangData.inauspicious.varjyam.start) }} - {{ formatTime(panchangData.inauspicious.varjyam.end) }}</span>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </div>
</template>
