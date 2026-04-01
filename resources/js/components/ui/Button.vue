<script setup>
defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'outline', 'danger', 'ghost'].includes(value),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    default: 'button',
  },
});
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
    :class="[
      // Variants
      variant === 'primary' && 'bg-primary-500 text-white hover:bg-primary-600 focus:ring-primary-500',
      variant === 'secondary' && 'bg-gray-800 text-white hover:bg-gray-900 focus:ring-gray-800',
      variant === 'outline' && 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-primary-500',
      variant === 'danger' && 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
      variant === 'ghost' && 'text-gray-700 hover:bg-gray-100 focus:ring-gray-500',
      // Sizes
      size === 'sm' && 'px-3 py-1.5 text-sm',
      size === 'md' && 'px-4 py-2 text-sm',
      size === 'lg' && 'px-6 py-3 text-base',
    ]"
  >
    <svg
      v-if="loading"
      class="animate-spin -ml-1 mr-2 h-4 w-4"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <slot />
  </button>
</template>
