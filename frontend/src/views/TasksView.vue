<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useTasksStore } from '@/stores/tasks'
import { Plus, CheckCircle2, Circle, Trash2, Calendar } from 'lucide-vue-next'
import { formatDistanceToNow } from 'date-fns'

const store = useTasksStore()
const newTitle = ref('')
const newDueDate = ref('')
const showCompleted = ref(false)

async function add() {
  const title = newTitle.value.trim()
  if (!title) return
  await store.create({ title, due_date: newDueDate.value || null })
  newTitle.value = ''
  newDueDate.value = ''
}

function formatDue(date: string) {
  const d = new Date(date + 'T00:00:00')
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const diff = d.getTime() - today.getTime()
  const days = Math.round(diff / 86400000)
  if (days < 0) return 'Overdue'
  if (days === 0) return 'Today'
  if (days === 1) return 'Tomorrow'
  return formatDistanceToNow(d, { addSuffix: true })
}

function dueColor(date: string) {
  const d = new Date(date + 'T00:00:00')
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  if (d < today) return 'text-destructive'
  if (d.getTime() === today.getTime()) return 'text-yellow-500'
  return 'text-muted-foreground'
}

onMounted(() => store.fetch())
</script>

<template>
  <div class="max-w-2xl mx-auto px-6 py-8">
    <h2 class="text-xl font-semibold text-foreground">Tasks</h2>
    <p class="text-sm text-muted-foreground mt-1">One-off things to get done.</p>

    <form @submit.prevent="add" class="mt-6 flex gap-2">
      <input
        v-model="newTitle"
        placeholder="Add a task..."
        class="flex-1 px-4 py-3 rounded-lg border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring placeholder:text-muted-foreground"
      />
      <input
        v-model="newDueDate"
        type="date"
        class="px-3 py-3 rounded-lg border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
      />
      <button
        type="submit"
        :disabled="!newTitle.trim()"
        class="px-4 py-3 rounded-lg bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 transition-colors disabled:opacity-30"
      >
        <Plus class="w-4 h-4" />
      </button>
    </form>

    <div class="mt-6 space-y-1">
      <button
        v-for="task in store.pending"
        :key="task.id"
        class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-border hover:bg-accent/50 transition-colors text-left group"
        @click="store.toggle(task.id)"
      >
        <Circle class="w-5 h-5 text-muted-foreground flex-shrink-0" />
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-foreground truncate">{{ task.title }}</p>
          <p v-if="task.notes" class="text-xs text-muted-foreground truncate">{{ task.notes }}</p>
        </div>
        <span v-if="task.due_date" class="flex items-center gap-1 text-xs flex-shrink-0" :class="dueColor(task.due_date)">
          <Calendar class="w-3 h-3" />
          {{ formatDue(task.due_date) }}
        </span>
        <button @click.stop="store.remove(task.id)" class="text-muted-foreground hover:text-destructive transition-colors opacity-0 group-hover:opacity-100 flex-shrink-0">
          <Trash2 class="w-3.5 h-3.5" />
        </button>
      </button>
    </div>

    <p v-if="!store.pending.length && !store.loading" class="text-sm text-muted-foreground py-8 text-center">
      No tasks. Add one above or convert from your inbox.
    </p>

    <div v-if="store.completed.length" class="mt-8">
      <button @click="showCompleted = !showCompleted" class="text-xs text-muted-foreground hover:text-foreground transition-colors uppercase tracking-wide font-medium">
        {{ showCompleted ? 'Hide' : 'Show' }} completed ({{ store.completed.length }})
      </button>
      <div v-if="showCompleted" class="mt-3 space-y-1">
        <button
          v-for="task in store.completed"
          :key="task.id"
          class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-border opacity-50 hover:opacity-75 transition-opacity text-left group"
          @click="store.toggle(task.id)"
        >
          <CheckCircle2 class="w-5 h-5 text-primary flex-shrink-0" />
          <p class="flex-1 text-sm font-medium text-muted-foreground line-through truncate">{{ task.title }}</p>
          <button @click.stop="store.remove(task.id)" class="text-muted-foreground hover:text-destructive transition-colors opacity-0 group-hover:opacity-100 flex-shrink-0">
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </button>
      </div>
    </div>
  </div>
</template>
