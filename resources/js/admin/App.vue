<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth } from './stores/auth'
import { api } from './api'
import Sidebar from './components/Sidebar.vue'

const route = useRoute()
const router = useRouter()

const drawerOpen = ref(false)

// Persisted so the choice survives a reload; guarded because a browser with
// site data blocked throws on access rather than returning null.
const collapsed = ref(
  (() => {
    try {
      return localStorage.getItem('ecg_admin_sidebar') === 'collapsed'
    } catch {
      return false
    }
  })()
)

watch(collapsed, (value) => {
  try {
    localStorage.setItem('ecg_admin_sidebar', value ? 'collapsed' : 'expanded')
  } catch {
    /* the session still works without the preference */
  }
})

const titles = {
  dashboard: 'Dashboard',
  terminals: 'Terminals',
  terminal: 'Terminal',
  'terminal.edit': 'Reset terminal details',
  transactions: 'Transactions',
}

const pageTitle = computed(() => titles[route.name] || 'Admin')

async function logout() {
  // Best effort: the token is blacklisted server-side if the call lands, but a
  // failed request must still end the session locally.
  try {
    await api.logout()
  } catch {
    /* ignored */
  }
  auth.clear()
  router.push({ name: 'login' })
}
</script>

<template>
  <!-- Signed out: the login page owns the whole viewport, no chrome. -->
  <RouterView v-if="!auth.isAuthenticated" />

  <div v-else class="min-h-screen bg-slate-50">
    <!-- Desktop sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-30 hidden transition-all duration-200 lg:block"
      :class="collapsed ? 'w-[4.5rem]' : 'w-64'"
    >
      <Sidebar :collapsed="collapsed" />
    </aside>

    <!-- Mobile drawer -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="drawerOpen"
        class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden"
        aria-hidden="true"
        @click="drawerOpen = false"
      />
    </Transition>

    <Transition
      enter-active-class="transition-transform duration-200 ease-out"
      leave-active-class="transition-transform duration-200 ease-in"
      enter-from-class="-translate-x-full"
      leave-to-class="-translate-x-full"
    >
      <aside v-if="drawerOpen" class="fixed inset-y-0 left-0 z-50 w-64 lg:hidden">
        <Sidebar @navigate="drawerOpen = false" />
      </aside>
    </Transition>

    <!-- Content column, offset by the sidebar on desktop -->
    <div class="transition-all duration-200" :class="collapsed ? 'lg:pl-[4.5rem]' : 'lg:pl-64'">
      <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
          <div class="flex min-w-0 items-center gap-3">
            <!-- Opens the drawer on mobile, collapses the rail on desktop -->
            <button
              class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 lg:hidden"
              aria-label="Open navigation"
              @click="drawerOpen = true"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>

            <button
              class="hidden rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 lg:block"
              :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
              @click="collapsed = !collapsed"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h16" />
              </svg>
            </button>

            <h1 class="truncate text-base font-semibold text-slate-900">{{ pageTitle }}</h1>
          </div>

          <button class="btn-secondary shrink-0 px-3 py-1.5" @click="logout">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15 17l5-5-5-5M20 12H9M12 20H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h6"
              />
            </svg>
            <span class="hidden sm:inline">Sign out</span>
          </button>
        </div>
      </header>

      <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>
