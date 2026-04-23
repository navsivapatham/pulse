import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<any>(null)
  const token = ref(localStorage.getItem('auth_token'))
  const isAuthenticated = computed(() => !!token.value)

  async function login(email: string, password: string) {
    const { data } = await api.post('/login', { email, password })
    token.value = data.data.token
    user.value = data.data.user
    localStorage.setItem('auth_token', data.data.token)
  }

  async function register(name: string, email: string, password: string, password_confirmation: string) {
    const { data } = await api.post('/register', { name, email, password, password_confirmation })
    token.value = data.data.token
    user.value = data.data.user
    localStorage.setItem('auth_token', data.data.token)
  }

  async function fetchUser() {
    try {
      const { data } = await api.get('/auth-user')
      user.value = data.data
    } catch {
      logout()
    }
  }

  function logout() {
    api.post('/logout').catch(() => {})
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
  }

  return { user, token, isAuthenticated, login, register, fetchUser, logout }
})
