<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { api } from '../api'
import { money, count, datetime } from '../format'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'
import Pagination from '../components/Pagination.vue'

const transactions = ref([])
const terminals = ref([])
const meta = ref(null)
const summary = ref(null)
const loading = ref(true)
const error = ref(null)
const page = ref(1)

const filters = reactive({ search: '', terminal_id: '', status: '', from: '', to: '' })

let debounce

async function load() {
  loading.value = true
  error.value = null
  try {
    const params = { ...filters, page: page.value }
    const [txnRes, sumRes] = await Promise.all([api.transactions(params), api.summary(params)])
    transactions.value = txnRes.data
    meta.value = txnRes.meta
    summary.value = sumRes.data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

// Any filter change resets to page 1: staying on page 7 of a narrower result
// set would otherwise show an empty table.
watch(
  () => ({ ...filters }),
  () => {
    clearTimeout(debounce)
    debounce = setTimeout(() => {
      page.value = 1
      load()
    }, 300)
  },
  { deep: true }
)

function reset() {
  Object.keys(filters).forEach((key) => (filters[key] = ''))
}

function goTo(next) {
  page.value = next
  load()
}

onMounted(async () => {
  // Populate the terminal filter from the full list, not just what happens to
  // appear on the first page of transactions.
  try {
    const res = await api.terminals({ per_page: 100 })
    terminals.value = res.data.filter((t) => t.terminal_id)
  } catch {
    /* the filter is optional; the page still works without it */
  }
  await load()
})
</script>

<template>
  <div>
    <p class="mb-6 text-sm text-slate-500">Every collection across all terminals</p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatCard label="Collected" :value="money(summary?.total_collected)" :loading="loading" />
      <StatCard label="Transactions" :value="count(summary?.transactions)" :loading="loading" />
      <StatCard label="Paid" :value="count(summary?.paid)" :loading="loading" />
      <StatCard label="Pending" :value="count(summary?.pending)" :loading="loading" />
    </div>

    <div class="card mt-6 p-4">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
          <label class="label" for="search">Search</label>
          <input id="search" v-model="filters.search" class="field" placeholder="IPN or payer name" />
        </div>

        <div>
          <label class="label" for="terminal">Terminal</label>
          <select id="terminal" v-model="filters.terminal_id" class="field">
            <option value="">All terminals</option>
            <option v-for="t in terminals" :key="t.id" :value="t.terminal_id">
              {{ t.terminal_id }} — {{ t.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="label" for="status">Status</label>
          <select id="status" v-model="filters.status" class="field">
            <option value="">Any status</option>
            <option value="pending">Pending</option>
            <option value="paid">Paid</option>
            <option value="part-payment">Part payment</option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="label" for="from">From</label>
            <input id="from" v-model="filters.from" type="date" class="field" />
          </div>
          <div>
            <label class="label" for="to">To</label>
            <input id="to" v-model="filters.to" type="date" class="field" />
          </div>
        </div>
      </div>

      <div class="mt-3 flex justify-end">
        <button class="btn-secondary px-3 py-1.5" @click="reset">Clear filters</button>
      </div>
    </div>

    <div v-if="error" class="mt-6 rounded-lg bg-rose-50 p-4 text-sm text-rose-800">{{ error }}</div>

    <div class="card mt-6 overflow-hidden">
      <div v-if="loading" class="space-y-3 p-5">
        <div v-for="i in 8" :key="i" class="h-12 animate-pulse rounded bg-slate-100"></div>
      </div>

      <div v-else-if="!transactions.length" class="p-12 text-center">
        <p class="text-sm font-medium text-slate-900">No transactions match</p>
        <p class="mt-1 text-sm text-slate-500">Try widening or clearing the filters.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full">
          <thead class="border-b border-slate-200 bg-slate-50">
            <tr>
              <th class="th">IPN</th>
              <th class="th">Payer</th>
              <th class="th">Terminal</th>
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
                <p class="max-w-[14rem] truncate font-medium text-slate-900">{{ txn.customer?.name || '—' }}</p>
                <p class="truncate text-xs text-slate-500">{{ txn.customer?.phone || '' }}</p>
              </td>
              <td class="td font-mono text-xs">{{ txn.terminal_id }}</td>
              <td class="td text-right tabular">{{ money(txn.total_amount) }}</td>
              <td class="td text-right font-medium tabular">{{ money(txn.amount_paid) }}</td>
              <td class="td"><StatusBadge :status="txn.status" /></td>
              <td class="td whitespace-nowrap text-xs text-slate-500">
                {{ datetime(txn.paid_at || txn.created_at) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination v-if="meta" :meta="meta" @change="goTo" />
    </div>
  </div>
</template>
