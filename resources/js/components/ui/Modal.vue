<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline';

defineProps({
  show: Boolean,
  title: String,
  maxWidth: {
    type: String,
    default: 'md',
  },
});

const emit = defineEmits(['close']);
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
          <div class="fixed inset-0 bg-black/50" @click="emit('close')"></div>
          <div
            class="relative bg-white rounded-lg shadow-xl w-full"
            :class="{
              'max-w-sm': maxWidth === 'sm',
              'max-w-md': maxWidth === 'md',
              'max-w-lg': maxWidth === 'lg',
              'max-w-xl': maxWidth === 'xl',
              'max-w-2xl': maxWidth === '2xl',
            }"
          >
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
              <button
                @click="emit('close')"
                class="text-gray-400 hover:text-gray-500 transition-colors"
              >
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div class="p-6">
              <slot />
            </div>
            <div v-if="$slots.footer" class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg">
              <slot name="footer" />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
