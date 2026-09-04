<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import { auth } from '../stores/auth'

const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref(null)

async function submit() {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.login(email.value, password.value)
    auth.set(data.access_token, data.user)

    // The API authenticates any terminal, so the panel has to check the admin
    // flag itself. Without this a plain terminal would land on a dashboard
    // whose every request then failed with 403.
    const me = await api.me()
    if (!me.data?.is_admin) {
      auth.clear()
      error.value = 'This account is not an administrator.'
      return
    }

    auth.set(data.access_token, me.data)
    router.push(route.query.next || { name: 'dashboard' })
  } catch (e) {
    error.value =
      e.status === 401 ? 'Those details do not match an account.' : e.message || 'Unable to sign in.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12">
    <div class="w-full max-w-sm">
      <div class="mb-8 text-center">
        <span
          class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-brand-700 text-lg font-bold text-white"
        >
          ECG
        </span>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Admin sign in</h1>
        <p class="mt-1.5 text-sm text-slate-500">Terminal and transaction administration</p>
      </div>

      <form class="card space-y-5 p-6" novalidate @submit.prevent="submit">
        <div
          v-if="error"
          class="flex items-start gap-2.5 rounded-lg bg-rose-50 p-3 text-sm text-rose-800"
          role="alert"
        >
          <svg class="mt-0.5 h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path
              fill-rule="evenodd"
              d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-8-4a1 1 0 00-1 1v3a1 1 0 002 0V7a1 1 0 00-1-1zm0 8a1 1 0 100-2 1 1 0 000 2z"
              clip-rule="evenodd"
            />
          </svg>
          <span>{{ error }}</span>
        </div>

        <div>
          <label for="email" class="label">Email address</label>
          <input
            id="email"
            v-model="email"
            type="email"
            autocomplete="username"
            required
            class="field"
            placeholder="admin@ecgpos.local"
          />
        </div>

        <div>
          <label for="password" class="label">Password</label>
          <div class="relative">
            <input
              id="password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              required
              class="field pr-11"
              placeholder="••••••••"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
              @click="showPassword = !showPassword"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                  v-if="!showPassword"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M2.036 12.322a1 1 0 010-.639C3.423 7.51 7.36 4.5 12 4.5s8.577 3.01 9.964 7.183a1 1 0 010 .639C20.577 16.49 16.64 19.5 12 19.5s-8.577-3.01-9.964-7.178z"
                />
                <circle v-if="!showPassword" cx="12" cy="12" r="3" />
                <path
                  v-else
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M3.98 8.223A10.477 10.477 0 002.036 12.32a1 1 0 000 .64C3.423 17.49 7.36 20.5 12 20.5c1.66 0 3.234-.386 4.646-1.075M6.228 6.228A10.45 10.45 0 0112 4.5c4.64 0 8.577 3.01 9.964 7.183a1 1 0 010 .639 10.55 10.55 0 01-4.293 5.192M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65"
                />
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-primary w-full" :disabled="loading">
          <svg v-if="loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
          </svg>
          {{ loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-slate-400">
        Authorised personnel only. Activity is logged.
      </p>
    </div>
  </div>
</template>
