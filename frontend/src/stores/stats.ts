import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useStatsStore = defineStore('stats', () => {
  const daily = ref<any>(null)
  const weekly = ref<any>(null)
  const streaks = ref<any[]>([])
  const loading = ref(false)

  async function fetchDaily(date?: string) {
    const params: Record<string, any> = {}
    if (date) params.date = date
    const { data } = await api.get('/stats/daily', { params })
    daily.value = data.data
  }

  async function fetchWeekly() {
    const { data } = await api.get('/stats/weekly')
    weekly.value = data.data
  }

  async function fetchStreaks() {
    const { data } = await api.get('/stats/streaks')
    streaks.value = data.data
  }

  async function fetchAll() {
    loading.value = true
    try {
      await Promise.all([fetchDaily(), fetchWeekly(), fetchStreaks()])
    } finally {
      loading.value = false
    }
  }

  return { daily, weekly, streaks, loading, fetchDaily, fetchWeekly, fetchStreaks, fetchAll }
})
