import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },
    { path: '/register', name: 'register', component: () => import('@/views/RegisterView.vue'), meta: { guest: true } },
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { auth: true } },
    { path: '/inbox', name: 'inbox', component: () => import('@/views/InboxView.vue'), meta: { auth: true } },
    { path: '/review', name: 'review', component: () => import('@/views/ReviewView.vue'), meta: { auth: true } },
    { path: '/routines', name: 'routines', component: () => import('@/views/RoutinesView.vue'), meta: { auth: true } },
    { path: '/stats', name: 'stats', component: () => import('@/views/StatsView.vue'), meta: { auth: true } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.isAuthenticated && !auth.user) {
    await auth.fetchUser()
  }

  if (to.meta.auth && !auth.isAuthenticated) return '/login'
  if (to.meta.guest && auth.isAuthenticated) return '/dashboard'
})

export default router
