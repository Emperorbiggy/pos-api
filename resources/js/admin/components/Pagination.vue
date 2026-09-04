<script setup>
defineProps({
  meta: { type: Object, required: true },
})
const emit = defineEmits(['change'])
</script>

<template>
  <div
    v-if="meta && meta.last_page > 1"
    class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
  >
    <p class="text-sm text-slate-600">
      Showing <span class="font-medium text-slate-900">{{ meta.from ?? 0 }}</span>–<span
        class="font-medium text-slate-900"
        >{{ meta.to ?? 0 }}</span
      >
      of <span class="font-medium text-slate-900">{{ meta.total }}</span>
    </p>

    <div class="flex items-center gap-2">
      <button
        class="btn-secondary px-3 py-1.5"
        :disabled="meta.current_page <= 1"
        @click="emit('change', meta.current_page - 1)"
      >
        Previous
      </button>
      <span class="px-2 text-sm text-slate-600 tabular">
        Page {{ meta.current_page }} of {{ meta.last_page }}
      </span>
      <button
        class="btn-secondary px-3 py-1.5"
        :disabled="meta.current_page >= meta.last_page"
        @click="emit('change', meta.current_page + 1)"
      >
        Next
      </button>
    </div>
  </div>
</template>
