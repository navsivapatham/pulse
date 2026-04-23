import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export interface InboxItem {
  id: number
  body: string
  status: 'pending' | 'reviewed' | 'converted' | 'dismissed'
  reviewed_at: string | null
  created_at: string
}

export const useInboxStore = defineStore('inbox', () => {
  const items = ref<InboxItem[]>([])
  const loading = ref(false)

  const pendingCount = computed(() => items.value.filter((i) => i.status === 'pending').length)

  async function fetch(status?: string) {
    loading.value = true
    try {
      const params: Record<string, any> = {}
      if (status) params.status = status
      const { data } = await api.get('/inbox', { params })
      items.value = data.data.data ?? data.data
    } finally {
      loading.value = false
    }
  }

  async function capture(body: string) {
    const { data } = await api.post('/inbox', { body })
    items.value.unshift(data.data)
    return data.data
  }

  async function review(id: number, action: string, extra?: Record<string, any>) {
    const { data } = await api.post(`/inbox/${id}/review`, { action, ...extra })
    const idx = items.value.findIndex((i) => i.id === id)
    if (idx !== -1) {
      if (data.data.inbox_item) {
        items.value[idx] = data.data.inbox_item
      } else {
        items.value[idx] = data.data
      }
    }
    return data.data
  }

  async function remove(id: number) {
    await api.delete(`/inbox/${id}`)
    items.value = items.value.filter((i) => i.id !== id)
  }

  return { items, loading, pendingCount, fetch, capture, review, remove }
})
