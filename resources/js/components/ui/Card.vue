<script setup>
defineProps({
  title: String,
  subtitle: String,
  padding: {
    type: Boolean,
    default: true,
  },
});
</script>

<template>
  <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
    <div v-if="title || $slots.header" class="px-6 py-4 border-b border-gray-200">
      <slot name="header">
        <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
        <p v-if="subtitle" class="mt-1 text-sm text-gray-500">{{ subtitle }}</p>
      </slot>
    </div>
    <div :class="padding ? 'p-6' : ''">
      <slot />
    </div>
    <div v-if="$slots.footer" class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg">
      <slot name="footer" />
    </div>
  </div>
</template>
