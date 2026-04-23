import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export interface Routine {
  id: number
  title: string
  description: string | null
  cadence: 'daily' | 'weekly' | 'monthly'
  scheduled_time: string | null
  is_active: boolean
  sort_order: number
  color: string | null
  icon: string | null
  is_completed_today?: boolean
  streak?: {
    current_streak: number
    longest_streak: number
    last_completed_date: string | null
  } | null
}

export const useRoutinesStore = defineStore('routines', () => {
  const routines = ref<Routine[]>([])
  const loading = ref(false)

  async function fetch(params?: Record<string, any>) {
    loading.value = true
    try {
      const { data } = await api.get('/routines', { params })
      routines.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Partial<Routine>) {
    const { data } = await api.post('/routines', payload)
    routines.value.push(data.data)
    return data.data
  }

  async function update(id: number, payload: Partial<Routine>) {
    const { data } = await api.patch(`/routines/${id}`, payload)
    const idx = routines.value.findIndex((r) => r.id === id)
    if (idx !== -1) routines.value[idx] = data.data
    return data.data
  }

  async function remove(id: number) {
    await api.delete(`/routines/${id}`)
    routines.value = routines.value.filter((r) => r.id !== id)
  }

  async function complete(id: number, date?: string) {
    const { data } = await api.post(`/routines/${id}/complete`, { date })
    const idx = routines.value.findIndex((r) => r.id === id)
    if (idx !== -1) {
      routines.value[idx] = { ...data.data, is_completed_today: true }
    }
  }

  async function uncomplete(id: number, date?: string) {
    const { data } = await api.post(`/routines/${id}/uncomplete`, { date })
    const idx = routines.value.findIndex((r) => r.id === id)
    if (idx !== -1) {
      routines.value[idx] = { ...data.data, is_completed_today: false }
    }
  }

  return { routines, loading, fetch, create, update, remove, complete, uncomplete }
})
