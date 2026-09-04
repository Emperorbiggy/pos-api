<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  hint: { type: String, default: null },
  loading: { type: Boolean, default: false },
})
</script>

<template>
  <div class="card p-5">
    <p class="text-sm font-medium text-slate-500">{{ label }}</p>

    <div v-if="loading" class="mt-2 h-8 w-24 animate-pulse rounded bg-slate-200"></div>
    <p v-else class="mt-1 text-2xl font-semibold text-slate-900 tabular">{{ value }}</p>

    <p v-if="hint && !loading" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
  </div>
</template>
