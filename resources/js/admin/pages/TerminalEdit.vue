<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'

const props = defineProps({ id: { type: [String, Number], required: true } })
const router = useRouter()

const terminal = ref(null)
const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const errors = ref({})
const saved = ref(null)

const form = reactive({
  name: '',
  email: '',
  terminal_id: '',
  password: '',
  password_confirmation: '',
  pin: '',
})

// Credentials are opt-in: an admin fixing a typo in a name should not be able
// to blank a working password by accident.
const resetPassword = ref(false)
const resetPin = ref(false)

onMounted(async () => {
  try {
    const res = await api.terminal(props.id)
    terminal.value = res.data
    form.name = res.data.name || ''
    form.email = res.data.email || ''
    form.terminal_id = res.data.terminal_id || ''
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})

async function submit() {
  saving.value = true
  error.value = null
  errors.value = {}
  saved.value = null

  // Only changed fields are sent, so the API's "sometimes" rules leave the
  // rest untouched rather than revalidating values nobody edited.
  const payload = {}
  if (form.name !== terminal.value.name) payload.name = form.name
  if (form.email !== terminal.value.email) payload.email = form.email
  if (form.terminal_id !== terminal.value.terminal_id) payload.terminal_id = form.terminal_id
  if (resetPassword.value && form.password) {
    payload.password = form.password
    payload.password_confirmation = form.password_confirmation
  }
  if (resetPin.value && form.pin) payload.pin = form.pin

  if (Object.keys(payload).length === 0) {
    error.value = 'Nothing to save — no fields were changed.'
    saving.value = false
    return
  }

  try {
    const res = await api.updateTerminal(props.id, payload)
    terminal.value = res.data
    saved.value = Object.keys(payload).filter((k) => k !== 'password_confirmation')

    form.password = ''
    form.password_confirmation = ''
    form.pin = ''
    resetPassword.value = false
    resetPin.value = false
  } catch (e) {
    if (e.status === 422 && e.errors) {
      errors.value = e.errors
      error.value = 'Please correct the highlighted fields.'
    } else {
      error.value = e.message
    }
  } finally {
    saving.value = false
  }
}

function fieldError(name) {
  return errors.value[name]?.[0] || null
}
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <RouterLink
      :to="{ name: 'terminal', params: { id } }"
      class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
      </svg>
      Back to terminal
    </RouterLink>

    <div v-if="loading" class="h-64 animate-pulse rounded-xl bg-slate-100"></div>

    <template v-else-if="terminal">
      <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Reset terminal details</h1>
        <p class="mt-1 text-sm text-slate-500">
          {{ terminal.name }} · <span class="font-mono">{{ terminal.terminal_id || 'no terminal id' }}</span>
        </p>
      </div>

      <div
        v-if="saved"
        class="mb-6 flex items-start gap-2.5 rounded-lg bg-brand-50 p-4 text-sm text-brand-900"
        role="status"
      >
        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>
        <span>Saved. Updated: {{ saved.join(', ') }}.</span>
      </div>

      <div v-if="error" class="mb-6 rounded-lg bg-rose-50 p-4 text-sm text-rose-800" role="alert">
        {{ error }}
      </div>

      <form class="space-y-6" novalidate @submit.prevent="submit">
        <section class="card p-6">
          <h2 class="mb-5 font-semibold text-slate-900">Identity</h2>

          <div class="space-y-4">
            <div>
              <label class="label" for="name">Terminal name</label>
              <input
                id="name"
                v-model="form.name"
                class="field"
                :class="{ 'field-error': fieldError('name') }"
              />
              <p v-if="fieldError('name')" class="mt-1.5 text-xs text-rose-600">{{ fieldError('name') }}</p>
            </div>

            <div>
              <label class="label" for="email">Email address</label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                class="field"
                :class="{ 'field-error': fieldError('email') }"
              />
              <p v-if="fieldError('email')" class="mt-1.5 text-xs text-rose-600">{{ fieldError('email') }}</p>
            </div>

            <div>
              <label class="label" for="terminal_id">Terminal ID</label>
              <input
                id="terminal_id"
                v-model="form.terminal_id"
                class="field font-mono"
                :class="{ 'field-error': fieldError('terminal_id') }"
              />
              <p v-if="fieldError('terminal_id')" class="mt-1.5 text-xs text-rose-600">
                {{ fieldError('terminal_id') }}
              </p>
              <p v-else class="mt-1.5 text-xs text-slate-500">
                Must be unique. Past transactions keep the ID they were recorded under.
              </p>
            </div>
          </div>
        </section>

        <section class="card p-6">
          <h2 class="mb-1 font-semibold text-slate-900">Credentials</h2>
          <p class="mb-5 text-sm text-slate-500">
            Left alone unless you tick a box. Neither value can be read back once saved.
          </p>

          <div class="space-y-5">
            <div>
              <label class="flex items-center gap-2.5">
                <input v-model="resetPassword" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-700" />
                <span class="text-sm font-medium text-slate-700">Set a new password</span>
              </label>

              <div v-if="resetPassword" class="mt-3 space-y-3 border-l-2 border-slate-200 pl-4">
                <div>
                  <label class="label" for="password">New password</label>
                  <input
                    id="password"
                    v-model="form.password"
                    type="text"
                    autocomplete="new-password"
                    class="field"
                    :class="{ 'field-error': fieldError('password') }"
                    placeholder="At least 8 characters"
                  />
                  <p v-if="fieldError('password')" class="mt-1.5 text-xs text-rose-600">
                    {{ fieldError('password') }}
                  </p>
                </div>

                <div>
                  <label class="label" for="password_confirmation">Confirm password</label>
                  <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="text"
                    autocomplete="new-password"
                    class="field"
                  />
                </div>

                <p class="text-xs text-slate-500">
                  Shown in plain text so you can read it back to the terminal holder. Existing sessions on
                  that terminal stay signed in until their token expires.
                </p>
              </div>
            </div>

            <div class="border-t border-slate-200 pt-5">
              <label class="flex items-center gap-2.5">
                <input v-model="resetPin" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-700" />
                <span class="text-sm font-medium text-slate-700">Set a new PIN</span>
              </label>

              <div v-if="resetPin" class="mt-3 border-l-2 border-slate-200 pl-4">
                <label class="label" for="pin">New PIN</label>
                <input
                  id="pin"
                  v-model="form.pin"
                  inputmode="numeric"
                  maxlength="6"
                  class="field max-w-40 font-mono tracking-widest"
                  :class="{ 'field-error': fieldError('pin') }"
                  placeholder="4–6 digits"
                />
                <p v-if="fieldError('pin')" class="mt-1.5 text-xs text-rose-600">{{ fieldError('pin') }}</p>
                <p v-else class="mt-1.5 text-xs text-slate-500">
                  Currently {{ terminal.has_pin ? 'set' : 'not set' }}. Leading zeros are kept.
                </p>
              </div>
            </div>
          </div>
        </section>

        <div class="flex items-center justify-end gap-3">
          <RouterLink :to="{ name: 'terminal', params: { id } }" class="btn-secondary">Cancel</RouterLink>
          <button type="submit" class="btn-primary" :disabled="saving">
            <svg v-if="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
            </svg>
            {{ saving ? 'Saving…' : 'Save changes' }}
          </button>
        </div>
      </form>
    </template>
  </div>
</template>
