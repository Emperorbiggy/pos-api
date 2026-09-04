import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import { auth } from './stores/auth'
import Login from './pages/Login.vue'
import Dashboard from './pages/Dashboard.vue'
import Terminals from './pages/Terminals.vue'
import TerminalDetail from './pages/TerminalDetail.vue'
import TerminalEdit from './pages/TerminalEdit.vue'
import Transactions from './pages/Transactions.vue'

const routes = [
  { path: '/admin/login', name: 'login', component: Login, meta: { public: true } },
  { path: '/admin', name: 'dashboard', component: Dashboard },
  { path: '/admin/terminals', name: 'terminals', component: Terminals },
  { path: '/admin/terminals/:id', name: 'terminal', component: TerminalDetail, props: true },
  { path: '/admin/terminals/:id/edit', name: 'terminal.edit', component: TerminalEdit, props: true },
  { path: '/admin/transactions', name: 'transactions', component: Transactions },
  { path: '/admin/:pathMatch(.*)*', redirect: '/admin' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

// Client-side guard for convenience only. It keeps an unauthenticated admin off
// the pages, but it is not the security boundary: every admin route is enforced
// server-side by the admin middleware, which a crafted request cannot bypass.
router.beforeEach((to) => {
  if (!to.meta.public && !auth.isAuthenticated) {
    return { name: 'login', query: { next: to.fullPath } }
  }
  if (to.meta.public && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
  return true
})

createApp(App).use(router).mount('#admin-app')
