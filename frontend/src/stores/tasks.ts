import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export interface Task {
  id: number
  title: string
  notes: string | null
  due_date: string | null
  is_completed: boolean
  completed_at: string | null
  created_at: string
}

export const useTasksStore = defineStore('tasks', () => {
  const tasks = ref<Task[]>([])
  const loading = ref(false)

  const pending = computed(() => tasks.value.filter((t) => !t.is_completed))
  const completed = computed(() => tasks.value.filter((t) => t.is_completed))

  async function fetch(params?: Record<string, any>) {
    loading.value = true
    try {
      const { data } = await api.get('/tasks', { params })
      tasks.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Partial<Task>) {
    const { data } = await api.post('/tasks', payload)
    tasks.value.unshift(data.data)
    return data.data
  }

  async function update(id: number, payload: Partial<Task>) {
    const { data } = await api.patch(`/tasks/${id}`, payload)
    const idx = tasks.value.findIndex((t) => t.id === id)
    if (idx !== -1) tasks.value[idx] = data.data
    return data.data
  }

  async function toggle(id: number) {
    const task = tasks.value.find((t) => t.id === id)
    if (!task) return
    return update(id, { is_completed: !task.is_completed })
  }

  async function remove(id: number) {
    await api.delete(`/tasks/${id}`)
    tasks.value = tasks.value.filter((t) => t.id !== id)
  }

  return { tasks, loading, pending, completed, fetch, create, update, toggle, remove }
})
