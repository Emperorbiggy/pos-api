import { reactive } from 'vue'

const TOKEN_KEY = 'ecg_admin_token'

/**
 * Session state for the admin panel.
 *
 * The token is mirrored into localStorage so a refresh does not log the admin
 * out, and every read is guarded: a browser with site data blocked throws on
 * access rather than returning null, which would otherwise break the whole app
 * at boot.
 */
function readToken() {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export const auth = reactive({
  token: readToken(),
  user: null,

  get isAuthenticated() {
    return Boolean(this.token)
  },

  set(token, user) {
    this.token = token
    this.user = user
    try {
      localStorage.setItem(TOKEN_KEY, token)
    } catch {
      // A session that lives only in memory still works for this tab.
    }
  },

  clear() {
    this.token = null
    this.user = null
    try {
      localStorage.removeItem(TOKEN_KEY)
    } catch {
      // Nothing to clean up if storage was never available.
    }
  },
})
