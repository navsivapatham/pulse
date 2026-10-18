<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRoutinesStore, type Routine } from '@/stores/routines'
import { Plus, Flame, Pencil, Trash2, X } from 'lucide-vue-next'

const store = useRoutinesStore()

const activeTab = ref<'all' | 'daily' | 'weekly' | 'monthly'>('all')
const showForm = ref(false)
const editing = ref<Routine | null>(null)

const form = ref({
  title: '',
  description: '',
  cadence: 'daily' as 'daily' | 'weekly' | 'monthly',
  scheduled_time: '' as string,
  weekday: '' as string,
  month_day: '' as string,
  color: '#6366F1',
})

const filtered = computed(() => {
  if (activeTab.value === 'all') return store.routines
  return store.routines.filter((r) => r.cadence === activeTab.value)
})

function formatTime(time: string | null): string {
  if (!time) return ''
  const [h, m] = time.split(':').map(Number)
  const suffix = h >= 12 ? 'PM' : 'AM'
  const hour = h % 12 || 12
  return `${hour}:${m.toString().padStart(2, '0')} ${suffix}`
}

function openCreate() {
  editing.value = null
  form.value = {
    title: '',
    description: '',
    cadence: 'daily',
    scheduled_time: '',
    weekday: '',
    month_day: '',
    color: '#6366F1',
  }
  showForm.value = true
}

function openEdit(routine: Routine) {
  editing.value = routine
  form.value = {
    title: routine.title,
    description: routine.description || '',
    cadence: routine.cadence,
    scheduled_time: routine.scheduled_time || '',
    weekday: routine.weekday ? String(routine.weekday) : '',
    month_day: routine.month_day ? String(routine.month_day) : '',
    color: routine.color || '#6366F1',
  }
  showForm.value = true
}

async function submit() {
  const payload = {
    ...form.value,
    scheduled_time: form.value.scheduled_time || null,
    weekday: form.value.cadence === 'weekly' && form.value.weekday ? Number(form.value.weekday) : null,
    month_day: form.value.cadence === 'monthly' && form.value.month_day ? Number(form.value.month_day) : null,
  }
  if (editing.value) {
    await store.update(editing.value.id, payload)
  } else {
    await store.create(payload)
  }
  showForm.value = false
}

async function remove(id: number) {
  await store.remove(id)
}

async function toggleActive(routine: Routine) {
  await store.update(routine.id, { is_active: !routine.is_active })
}

watch(
  () => form.value.cadence,
  (cadence) => {
    if (cadence === 'weekly' && !form.value.weekday) {
      form.value.weekday = '1'
    }

    if (cadence === 'monthly' && !form.value.month_day) {
      form.value.month_day = '1'
    }
  },
)

const tabs = [
  { key: 'all', label: 'All' },
  { key: 'daily', label: 'Daily' },
  { key: 'weekly', label: 'Weekly' },
  { key: 'monthly', label: 'Monthly' },
] as const

const cadenceBadgeColor: Record<string, string> = {
  daily: 'bg-blue-500/10 text-blue-500',
  weekly: 'bg-green-500/10 text-green-500',
  monthly: 'bg-purple-500/10 text-purple-500',
}

const weekdayOptions = [
  { value: '1', label: 'Monday' },
  { value: '2', label: 'Tuesday' },
  { value: '3', label: 'Wednesday' },
  { value: '4', label: 'Thursday' },
  { value: '5', label: 'Friday' },
  { value: '6', label: 'Saturday' },
  { value: '7', label: 'Sunday' },
]

const monthlyDayOptions = Array.from({ length: 28 }, (_, idx) => String(idx + 1))

function routineScheduleLabel(routine: Routine): string {
  const parts: string[] = []

  if (routine.cadence === 'weekly' && routine.weekday) {
    const weekday = weekdayOptions.find((option) => option.value === String(routine.weekday))
    if (weekday) parts.push(weekday.label)
  }

  if (routine.cadence === 'monthly' && routine.month_day) {
    parts.push(`Day ${routine.month_day}`)
  }

  if (routine.scheduled_time) {
    parts.push(formatTime(routine.scheduled_time))
  }

  return parts.join(' at ')
}

onMounted(() => store.fetch())
</script>

<template>
  <div class="max-w-2xl mx-auto px-6 py-8">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-foreground">Routines</h2>
        <p class="text-sm text-muted-foreground mt-1">Manage your recurring habits.</p>
      </div>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 transition-colors">
        <Plus class="w-4 h-4" /> Add
      </button>
    </div>

    <!-- Tabs -->
    <div class="mt-6 flex gap-1 border-b border-border">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
        :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Routine list -->
    <div class="mt-4 space-y-2">
      <div
        v-for="routine in filtered"
        :key="routine.id"
        class="flex items-center gap-3 px-4 py-3 rounded-lg border border-border"
        :class="!routine.is_active ? 'opacity-50' : ''"
      >
        <div class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: routine.color || '#6366F1' }" />
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-foreground truncate">{{ routine.title }}</p>
          <div class="flex items-center gap-2">
            <p v-if="routineScheduleLabel(routine)" class="text-xs text-muted-foreground">{{ routineScheduleLabel(routine) }}</p>
            <span v-if="routineScheduleLabel(routine) && routine.description" class="text-xs text-muted-foreground/40">·</span>
            <p v-if="routine.description" class="text-xs text-muted-foreground truncate">{{ routine.description }}</p>
          </div>
        </div>
        <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="cadenceBadgeColor[routine.cadence]">
          {{ routine.cadence }}
        </span>
        <span v-if="routine.streak?.current_streak" class="flex items-center gap-1 text-xs text-orange-400">
          <Flame class="w-3 h-3" />
          {{ routine.streak.current_streak }}
        </span>
        <button @click="openEdit(routine)" class="text-muted-foreground hover:text-foreground">
          <Pencil class="w-3.5 h-3.5" />
        </button>
        <button @click="remove(routine.id)" class="text-muted-foreground hover:text-destructive">
          <Trash2 class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <p v-if="!filtered.length" class="text-sm text-muted-foreground py-8 text-center">
      No routines{{ activeTab !== 'all' ? ` with ${activeTab} cadence` : '' }}.
    </p>

    <!-- Form dialog (simple overlay) -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" @click.self="showForm = false">
      <div class="bg-card border border-border rounded-xl p-6 w-full max-w-md shadow-lg">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-foreground">{{ editing ? 'Edit' : 'New' }} Routine</h3>
          <button @click="showForm = false" class="text-muted-foreground hover:text-foreground"><X class="w-5 h-5" /></button>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">Title</label>
            <input v-model="form.title" required class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">Description</label>
            <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring resize-none" />
          </div>
          <div class="flex gap-4">
            <div class="space-y-2 flex-1">
              <label class="text-sm font-medium text-foreground">Cadence</label>
              <select v-model="form.cadence" class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
              </select>
            </div>
            <div class="space-y-2 flex-1">
              <label class="text-sm font-medium text-foreground">Time</label>
              <input v-model="form.scheduled_time" type="time" class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
            </div>
            <div v-if="form.cadence === 'weekly'" class="space-y-2 flex-1">
              <label class="text-sm font-medium text-foreground">Day</label>
              <select v-model="form.weekday" required class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                <option v-for="day in weekdayOptions" :key="day.value" :value="day.value">{{ day.label }}</option>
              </select>
            </div>
            <div v-if="form.cadence === 'monthly'" class="space-y-2 flex-1">
              <label class="text-sm font-medium text-foreground">Day</label>
              <select v-model="form.month_day" required class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                <option v-for="day in monthlyDayOptions" :key="day" :value="day">{{ day }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="text-sm font-medium text-foreground">Color</label>
              <input v-model="form.color" type="color" class="w-10 h-10 rounded-md border border-input cursor-pointer" />
            </div>
          </div>
          <button type="submit" class="w-full py-2 px-4 rounded-md bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 transition-colors">
            {{ editing ? 'Save Changes' : 'Create Routine' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
