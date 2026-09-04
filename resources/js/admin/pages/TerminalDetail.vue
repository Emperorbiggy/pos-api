<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import { money, count, datetime, date } from '../format'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'
import Pagination from '../components/Pagination.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const terminal = ref(null)
const transactions = ref([])
const meta = ref(null)
const summary = ref(null)
const status = ref('')
const page = ref(1)
const loading = ref(true)
const loadingTxns = ref(false)
const error = ref(null)

async function loadTransactions() {
  loadingTxns.value = true
  try {
    // Both keyed on the same terminal_id, so the totals always describe the
    // rows on screen rather than some wider set.
    const params = { terminal_id: terminal.value.terminal_id, status: status.value, page: page.value }
    const [txnRes, sumRes] = await Promise.all([api.transactions(params), api.summary(params)])
    transactions.value = txnRes.data
    meta.value = txnRes.meta
    summary.value = sumRes.data
  } catch (e) {
    error.value = e.message
  } finally {
    loadingTxns.value = false
  }
}

function filterBy(next) {
  status.value = next
  page.value = 1
  loadTransactions()
}

function goTo(next) {
  page.value = next
  loadTransactions()
}

onMounted(async () => {
  try {
    const res = await api.terminal(props.id)
    terminal.value = res.data
    await loadTransactions()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <RouterLink
      :to="{ name: 'terminals' }"
      class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
      </svg>
      All terminals
    </RouterLink>

    <div v-if="error" class="mb-6 rounded-lg bg-rose-50 p-4 text-sm text-rose-800">{{ error }}</div>

    <div v-if="loading" class="h-24 animate-pulse rounded-xl bg-slate-100"></div>

    <template v-else-if="terminal">
      <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-center gap-4">
          <span
            class="flex h-14 w-14 items-center justify-center rounded-xl bg-brand-700 text-lg font-semibold text-white"
          >
            {{ (terminal.name || '?').charAt(0).toUpperCase() }}
          </span>
          <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ terminal.name }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
              <span class="font-mono">{{ terminal.terminal_id || 'no terminal id' }}</span>
              <span aria-hidden="true">·</span>
              <span>{{ terminal.email }}</span>
              <span aria-hidden="true">·</span>
              <span>joined {{ date(terminal.created_at) }}</span>
            </p>
          </div>
        </div>

        <RouterLink :to="{ name: 'terminal.edit', params: { id: terminal.id } }" class="btn-primary shrink-0">
          Reset details
        </RouterLink>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Collected" :value="money(summary?.total_collected)" :loading="loadingTxns" />
        <StatCard label="Transactions" :value="count(summary?.transactions)" :loading="loadingTxns" />
        <StatCard label="Paid" :value="count(summary?.paid)" :loading="loadingTxns" />
        <StatCard label="Pending" :value="count(summary?.pending)" :loading="loadingTxns" />
      </div>

      <section class="card mt-8 overflow-hidden">
        <header class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
          <h2 class="font-semibold text-slate-900">Transactions</h2>

          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="option in [
                { value: '', label: 'All' },
                { value: 'pending', label: 'Pending' },
                { value: 'paid', label: 'Paid' },
              ]"
              :key="option.value"
              class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
              :class="
                status === option.value
                  ? 'bg-brand-700 text-white'
                  : 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50'
              "
              @click="filterBy(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
        </header>

        <div v-if="loadingTxns" class="space-y-3 p-5">
          <div v-for="i in 5" :key="i" class="h-12 animate-pulse rounded bg-slate-100"></div>
        </div>

        <div v-else-if="!transactions.length" class="p-12 text-center">
          <p class="text-sm font-medium text-slate-900">No transactions</p>
          <p class="mt-1 text-sm text-slate-500">Nothing recorded for this terminal yet.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="border-b border-slate-200 bg-slate-50">
              <tr>
                <th class="th">IPN</th>
                <th class="th">Payer</th>
                <th class="th text-right">Billed</th>
                <th class="th text-right">Paid</th>
                <th class="th">Status</th>
                <th class="th">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="txn in transactions" :key="txn.id" class="hover:bg-slate-50">
                <td class="td font-mono text-xs">{{ txn.ipn }}</td>
                <td class="td">
                  <p class="truncate font-medium text-slate-900">{{ txn.customer?.name || '—' }}</p>
                  <p class="truncate text-xs text-slate-500">{{ txn.customer?.phone || '' }}</p>
                </td>
                <td class="td text-right tabular">{{ money(txn.total_amount) }}</td>
                <td class="td text-right font-medium tabular">{{ money(txn.amount_paid) }}</td>
                <td class="td"><StatusBadge :status="txn.status" /></td>
                <td class="td text-xs text-slate-500">{{ datetime(txn.paid_at || txn.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <Pagination v-if="meta" :meta="meta" @change="goTo" />
      </section>
    </template>
  </div>
</template>
