<script setup>
import { computed } from 'vue'

const props = defineProps({ status: { type: String, default: null } })

// Statuses come straight from OIRS, so the mapping is forgiving: anything
// unrecognised still renders as a readable neutral badge rather than nothing.
const tone = computed(() => {
  const value = (props.status || '').toLowerCase()
  if (value === 'paid' || value === 'successful' || value === 'success') {
    return 'bg-brand-100 text-brand-800'
  }
  if (value.includes('part')) return 'bg-amber-100 text-amber-800'
  if (value === 'pending') return 'bg-slate-100 text-slate-600'
  if (value.includes('fail') || value.includes('cancel')) return 'bg-rose-100 text-rose-700'
  return 'bg-slate-100 text-slate-600'
})
</script>

<template>
  <span class="badge" :class="tone">{{ status || 'unknown' }}</span>
</template>
