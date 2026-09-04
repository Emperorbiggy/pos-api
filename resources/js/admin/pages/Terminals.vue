<script setup>
import { onMounted, ref, watch } from 'vue'
import { api } from '../api'
import { money, count, date } from '../format'
import Pagination from '../components/Pagination.vue'

const terminals = ref([])
const meta = ref(null)
const search = ref('')
const page = ref(1)
const loading = ref(true)
const error = ref(null)

let debounce

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await api.terminals({ search: search.value, page: page.value })
    terminals.value = res.data
    meta.value = res.meta
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

// Debounced so typing a terminal id does not fire a request per keystroke.
watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
})

function goTo(next) {
  page.value = next
  load()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <p class="text-sm text-slate-500">
        <span v-if="meta" class="tabular font-medium text-slate-900">{{ count(meta.total) }}</span>
        registered
      </p>

      <div class="relative sm:w-72">
        <svg
          class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          viewBox="0 0 24 24"
        >
          <circle cx="11" cy="11" r="7" />
          <path stroke-linecap="round" d="m20 20-3.5-3.5" />
        </svg>
        <input v-model="search" type="search" class="field pl-9" placeholder="Search name, email or ID" />
      </div>
    </div>

    <div v-if="error" class="mb-6 rounded-lg bg-rose-50 p-4 text-sm text-rose-800">{{ error }}</div>

    <div class="card overflow-hidden">
      <div v-if="loading" class="space-y-3 p-5">
        <div v-for="i in 6" :key="i" class="h-12 animate-pulse rounded bg-slate-100"></div>
      </div>

      <div v-else-if="!terminals.length" class="p-12 text-center">
        <p class="text-sm font-medium text-slate-900">No terminals found</p>
        <p class="mt-1 text-sm text-slate-500">
          {{ search ? 'Try a different search term.' : 'Import terminals to get started.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full">
          <thead class="border-b border-slate-200 bg-slate-50">
            <tr>
              <th class="th">Terminal</th>
              <th class="th">Terminal ID</th>
              <th class="th text-right">Transactions</th>
              <th class="th text-right">Collected</th>
              <th class="th">PIN</th>
              <th class="th">Registered</th>
              <th class="th"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="terminal in terminals" :key="terminal.id" class="hover:bg-slate-50">
              <td class="td">
                <div class="flex items-center gap-3">
                  <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-600"
                  >
                    {{ (terminal.name || '?').charAt(0).toUpperCase() }}
                  </span>
                  <div class="min-w-0">
                    <p class="truncate font-medium text-slate-900">{{ terminal.name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ terminal.email }}</p>
                  </div>
                  <span v-if="terminal.is_admin" class="badge bg-brand-100 text-brand-800">Admin</span>
                </div>
              </td>
              <td class="td font-mono text-xs">{{ terminal.terminal_id || '—' }}</td>
              <td class="td text-right tabular">{{ count(terminal.transactions_count) }}</td>
              <td class="td text-right font-medium tabular">{{ money(terminal.total_collected) }}</td>
              <td class="td">
                <span
                  class="badge"
                  :class="terminal.has_pin ? 'bg-brand-100 text-brand-800' : 'bg-slate-100 text-slate-500'"
                >
                  {{ terminal.has_pin ? 'Set' : 'Not set' }}
                </span>
              </td>
              <td class="td text-xs text-slate-500">{{ date(terminal.created_at) }}</td>
              <td class="td text-right">
                <div class="flex justify-end gap-2">
                  <RouterLink
                    :to="{ name: 'terminal', params: { id: terminal.id } }"
                    class="btn-secondary px-3 py-1.5"
                  >
                    View
                  </RouterLink>
                  <RouterLink
                    :to="{ name: 'terminal.edit', params: { id: terminal.id } }"
                    class="btn-secondary px-3 py-1.5"
                  >
                    Edit
                  </RouterLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination v-if="meta" :meta="meta" @change="goTo" />
    </div>
  </div>
</template>
