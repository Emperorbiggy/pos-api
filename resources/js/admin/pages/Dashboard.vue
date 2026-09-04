<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import { money, count, datetime } from '../format'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'

const summary = ref(null)
const terminals = ref([])
const recent = ref([])
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    // Fired together: three independent reads should cost one round trip's
    // worth of waiting, not three.
    const [summaryRes, terminalsRes, recentRes] = await Promise.all([
      api.summary(),
      api.terminals({ per_page: 5 }),
      api.transactions({ per_page: 8 }),
    ])

    summary.value = summaryRes.data
    terminals.value = terminalsRes.data
    recent.value = recentRes.data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <!-- The page name lives in the topbar; this is the one-line context. -->
    <p class="mb-6 text-sm text-slate-500">Collections across every registered terminal</p>

    <div v-if="error" class="mb-6 rounded-lg bg-rose-50 p-4 text-sm text-rose-800">{{ error }}</div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatCard label="Total collected" :value="money(summary?.total_collected)" :loading="loading" />
      <StatCard label="Transactions" :value="count(summary?.transactions)" :loading="loading" />
      <StatCard
        label="Paid"
        :value="count(summary?.paid)"
        :hint="summary ? `${count(summary.pending)} still pending` : null"
        :loading="loading"
      />
      <StatCard label="Active terminals" :value="count(summary?.terminals)" :loading="loading" />
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-5">
      <!-- Top terminals -->
      <section class="card lg:col-span-2">
        <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <h2 class="font-semibold text-slate-900">Terminals</h2>
          <RouterLink :to="{ name: 'terminals' }" class="text-sm font-medium text-brand-700 hover:text-brand-800">
            View all
          </RouterLink>
        </header>

        <div v-if="loading" class="space-y-3 p-5">
          <div v-for="i in 4" :key="i" class="h-12 animate-pulse rounded bg-slate-100"></div>
        </div>

        <p v-else-if="!terminals.length" class="p-5 text-sm text-slate-500">No terminals registered yet.</p>

        <ul v-else class="divide-y divide-slate-100">
          <li v-for="terminal in terminals" :key="terminal.id">
            <RouterLink
              :to="{ name: 'terminal', params: { id: terminal.id } }"
              class="flex items-center justify-between px-5 py-3.5 transition hover:bg-slate-50"
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-900">{{ terminal.name }}</p>
                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ terminal.terminal_id || '—' }}</p>
              </div>
              <div class="ml-4 shrink-0 text-right">
                <p class="text-sm font-medium text-slate-900 tabular">{{ money(terminal.total_collected) }}</p>
                <p class="text-xs text-slate-500 tabular">{{ count(terminal.transactions_count) }} txns</p>
              </div>
            </RouterLink>
          </li>
        </ul>
      </section>

      <!-- Recent transactions -->
      <section class="card lg:col-span-3">
        <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <h2 class="font-semibold text-slate-900">Recent transactions</h2>
          <RouterLink :to="{ name: 'transactions' }" class="text-sm font-medium text-brand-700 hover:text-brand-800">
            View all
          </RouterLink>
        </header>

        <div v-if="loading" class="space-y-3 p-5">
          <div v-for="i in 6" :key="i" class="h-10 animate-pulse rounded bg-slate-100"></div>
        </div>

        <p v-else-if="!recent.length" class="p-5 text-sm text-slate-500">No transactions recorded yet.</p>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="border-b border-slate-200 bg-slate-50">
              <tr>
                <th class="th">IPN / Payer</th>
                <th class="th">Terminal</th>
                <th class="th text-right">Amount</th>
                <th class="th">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="txn in recent" :key="txn.id" class="hover:bg-slate-50">
                <td class="td">
                  <p class="font-mono text-xs text-slate-900">{{ txn.ipn }}</p>
                  <p class="mt-0.5 truncate text-xs text-slate-500">{{ txn.customer?.name || '—' }}</p>
                </td>
                <td class="td font-mono text-xs">{{ txn.terminal_id }}</td>
                <td class="td text-right tabular">
                  <p class="font-medium text-slate-900">{{ money(txn.amount_paid) }}</p>
                  <p class="text-xs text-slate-500">of {{ money(txn.total_amount) }}</p>
                </td>
                <td class="td">
                  <StatusBadge :status="txn.status" />
                  <p class="mt-1 text-xs text-slate-400">{{ datetime(txn.paid_at || txn.created_at) }}</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>
