<script setup>
defineProps({
  modelValue: [String, Number],
  label: String,
  options: {
    type: Array,
    default: () => [],
  },
  placeholder: String,
  error: String,
  required: Boolean,
  disabled: Boolean,
});

defineEmits(['update:modelValue']);
</script>

<template>
  <div>
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <select
      :value="modelValue"
      @change="$emit('update:modelValue', $event.target.value)"
      :disabled="disabled"
      :required="required"
      class="w-full px-3 py-2 border rounded-md text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
      :class="error ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300'"
    >
      <option v-if="placeholder" value="">{{ placeholder }}</option>
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
    <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
  </div>
</template>
