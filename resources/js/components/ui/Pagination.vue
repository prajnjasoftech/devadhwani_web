<script setup>
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  currentPage: {
    type: Number,
    required: true,
  },
  lastPage: {
    type: Number,
    required: true,
  },
  total: {
    type: Number,
    required: true,
  },
  perPage: {
    type: Number,
    default: 15,
  },
});

const emit = defineEmits(['page-change']);

const from = () => (props.currentPage - 1) * props.perPage + 1;
const to = () => Math.min(props.currentPage * props.perPage, props.total);
</script>

<template>
  <div class="flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-gray-700">
          Showing <span class="font-medium">{{ from() }}</span> to
          <span class="font-medium">{{ to() }}</span> of
          <span class="font-medium">{{ total }}</span> results
        </p>
      </div>
      <div>
        <nav class="inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
          <button
            @click="emit('page-change', currentPage - 1)"
            :disabled="currentPage === 1"
            class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronLeftIcon class="h-5 w-5" />
          </button>
          <template v-for="page in lastPage" :key="page">
            <button
              v-if="page === 1 || page === lastPage || (page >= currentPage - 1 && page <= currentPage + 1)"
              @click="emit('page-change', page)"
              class="relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 focus:z-20"
              :class="page === currentPage ? 'bg-primary-500 text-white' : 'text-gray-900 hover:bg-gray-50'"
            >
              {{ page }}
            </button>
            <span
              v-else-if="page === currentPage - 2 || page === currentPage + 2"
              class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300"
            >
              ...
            </span>
          </template>
          <button
            @click="emit('page-change', currentPage + 1)"
            :disabled="currentPage === lastPage"
            class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronRightIcon class="h-5 w-5" />
          </button>
        </nav>
      </div>
    </div>
  </div>
</template>
