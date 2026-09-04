<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { auth } from '../stores/auth'

const props = defineProps({
  collapsed: { type: Boolean, default: false },
})
defineEmits(['navigate', 'toggle'])

const route = useRoute()

const links = [
  {
    name: 'dashboard',
    label: 'Dashboard',
    // Exact match: every admin route starts with /admin, so a prefix test
    // would light up Dashboard on every page.
    exact: true,
    icon: 'M3 10.5 12 3l9 7.5M5 9.5V20a1 1 0 0 0 1 1h3.5v-5.5h5V21H18a1 1 0 0 0 1-1V9.5',
  },
  {
    name: 'terminals',
    label: 'Terminals',
    match: 'terminal',
    icon: 'M4 5.5h16a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1ZM8 19h8M12 15.5V19',
  },
  {
    name: 'transactions',
    label: 'Transactions',
    icon: 'M3 7.5h13m0 0-3.5-3.5M16 7.5 12.5 11M21 16.5H8m0 0 3.5-3.5M8 16.5 11.5 20',
  },
]

const initials = computed(() => {
  const name = auth.user?.name || 'Admin'
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('')
})

function isActive(link) {
  if (link.exact) return route.name === link.name
  if (link.match) return String(route.name || '').startsWith(link.match)
  return route.name === link.name
}
</script>

<template>
  <div class="flex h-full flex-col bg-slate-900 text-slate-300">
    <!-- Brand -->
    <div
      class="flex h-16 shrink-0 items-center border-b border-white/5"
      :class="collapsed ? 'justify-center px-2' : 'gap-3 px-5'"
    >
      <span
        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-700 text-sm font-bold text-white"
      >
        ECG
      </span>
      <div v-if="!collapsed" class="min-w-0">
        <p class="truncate text-sm font-semibold text-white">POS Admin</p>
        <p class="truncate text-xs text-slate-400">Revenue collection</p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-1 overflow-y-auto p-3">
      <p v-if="!collapsed" class="px-3 pb-2 pt-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
        Menu
      </p>

      <RouterLink
        v-for="link in links"
        :key="link.name"
        :to="{ name: link.name }"
        :title="collapsed ? link.label : null"
        class="group relative flex items-center rounded-lg text-sm font-medium transition"
        :class="[
          collapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5',
          isActive(link)
            ? 'bg-brand-700 text-white shadow-sm shadow-brand-950/40'
            : 'text-slate-400 hover:bg-white/5 hover:text-white',
        ]"
        @click="$emit('navigate')"
      >
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" :d="link.icon" />
        </svg>
        <span v-if="!collapsed">{{ link.label }}</span>
      </RouterLink>
    </nav>

    <!-- Signed-in admin -->
    <div class="shrink-0 border-t border-white/5 p-3">
      <div
        class="flex items-center rounded-lg bg-white/5 p-2"
        :class="collapsed ? 'justify-center' : 'gap-3'"
        :title="collapsed ? auth.user?.name : null"
      >
        <span
          class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xs font-semibold text-white"
        >
          {{ initials }}
        </span>
        <div v-if="!collapsed" class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-white">{{ auth.user?.name || 'Administrator' }}</p>
          <p class="truncate text-xs text-slate-400">{{ auth.user?.email }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
