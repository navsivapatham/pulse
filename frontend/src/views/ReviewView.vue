<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useInboxStore, type InboxItem } from '@/stores/inbox'
import { Target, ListTodo, Eye, X, PartyPopper } from 'lucide-vue-next'

const inboxStore = useInboxStore()

const pendingItems = computed(() => inboxStore.items.filter((i) => i.status === 'pending'))
const currentIndex = ref(0)
const current = computed(() => pendingItems.value[currentIndex.value])
const showConvertForm = ref(false)
const convertType = ref<'routine' | 'task'>('routine')
const formTitle = ref('')
const formCadence = ref('daily')
const formTime = ref('')
const formWeekday = ref('')
const formMonthDay = ref('')
const processing = ref(false)

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

function startConvert(type: 'routine' | 'task') {
  convertType.value = type
  formTitle.value = current.value?.body?.slice(0, 80) ?? ''
  formCadence.value = 'daily'
  formTime.value = ''
  formWeekday.value = ''
  formMonthDay.value = ''
  showConvertForm.value = true
}

async function submitConvert() {
  if (!current.value) return
  processing.value = true
  try {
    if (convertType.value === 'routine') {
      await inboxStore.review(current.value.id, 'convert_routine', {
        title: formTitle.value,
        cadence: formCadence.value,
        scheduled_time: formTime.value || null,
        weekday: formCadence.value === 'weekly' && formWeekday.value ? Number(formWeekday.value) : null,
        month_day: formCadence.value === 'monthly' && formMonthDay.value ? Number(formMonthDay.value) : null,
      })
    } else {
      await inboxStore.review(current.value.id, 'convert_task', {
        title: formTitle.value,
      })
    }
    showConvertForm.value = false
    advance()
  } finally {
    processing.value = false
  }
}

async function keep() {
  if (!current.value) return
  processing.value = true
  try {
    await inboxStore.review(current.value.id, 'keep')
    advance()
  } finally {
    processing.value = false
  }
}

async function dismiss() {
  if (!current.value) return
  processing.value = true
  try {
    await inboxStore.review(current.value.id, 'dismiss')
    advance()
  } finally {
    processing.value = false
  }
}

function advance() {
  if (currentIndex.value >= pendingItems.value.length) {
    currentIndex.value = Math.max(0, pendingItems.value.length - 1)
  }
}

watch(formCadence, (cadence) => {
  if (cadence === 'weekly' && !formWeekday.value) {
    formWeekday.value = '1'
  }

  if (cadence === 'monthly' && !formMonthDay.value) {
    formMonthDay.value = '1'
  }
})

onMounted(() => inboxStore.fetch('pending'))
</script>

<template>
  <div class="max-w-xl mx-auto px-6 py-8">
    <h2 class="text-xl font-semibold text-foreground">Daily Review</h2>
    <p class="text-sm text-muted-foreground mt-1">Process your inbox — one item at a time.</p>

    <!-- Empty state -->
    <div v-if="!pendingItems.length && !inboxStore.loading" class="mt-16 text-center">
      <PartyPopper class="w-12 h-12 text-primary mx-auto" />
      <p class="text-foreground font-medium mt-4">All clear!</p>
      <p class="text-sm text-muted-foreground mt-1">Nothing to review. Go crush your routines.</p>
    </div>

    <!-- Review card -->
    <div v-if="current" class="mt-8">
      <p class="text-xs text-muted-foreground mb-2">{{ currentIndex + 1 }} of {{ pendingItems.length }}</p>

      <div class="rounded-xl border border-border bg-card p-6">
        <p class="text-foreground text-base leading-relaxed">{{ current.body }}</p>
        <p class="text-xs text-muted-foreground mt-4">
          Captured {{ new Date(current.created_at).toLocaleDateString() }}
        </p>
      </div>

      <!-- Convert form -->
      <div v-if="showConvertForm" class="mt-4 rounded-lg border border-border p-4 space-y-3">
        <div class="space-y-2">
          <label class="text-sm font-medium text-foreground">Title</label>
          <input
            v-model="formTitle"
            class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>
        <div v-if="convertType === 'routine'" class="flex gap-3">
          <div class="space-y-2 flex-1">
            <label class="text-sm font-medium text-foreground">Cadence</label>
            <select
              v-model="formCadence"
              class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </div>
          <div class="space-y-2 flex-1">
            <label class="text-sm font-medium text-foreground">Time</label>
            <input
              v-model="formTime"
              type="time"
              class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            />
          </div>
          <div v-if="formCadence === 'weekly'" class="space-y-2 flex-1">
            <label class="text-sm font-medium text-foreground">Day</label>
            <select
              v-model="formWeekday"
              required
              class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option v-for="day in weekdayOptions" :key="day.value" :value="day.value">{{ day.label }}</option>
            </select>
          </div>
          <div v-if="formCadence === 'monthly'" class="space-y-2 flex-1">
            <label class="text-sm font-medium text-foreground">Day</label>
            <select
              v-model="formMonthDay"
              required
              class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option v-for="day in monthlyDayOptions" :key="day" :value="day">{{ day }}</option>
            </select>
          </div>
        </div>
        <div class="flex gap-2">
          <button @click="submitConvert" :disabled="processing || !formTitle.trim()" class="px-4 py-2 rounded-md bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 disabled:opacity-50">
            {{ convertType === 'routine' ? 'Create Routine' : 'Create Task' }}
          </button>
          <button @click="showConvertForm = false" class="px-4 py-2 rounded-md border border-border text-sm text-muted-foreground hover:text-foreground">
            Cancel
          </button>
        </div>
      </div>

      <!-- Action buttons -->
      <div v-else class="mt-4 grid grid-cols-2 gap-2">
        <button @click="startConvert('routine')" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border border-border text-sm font-medium text-foreground hover:bg-accent transition-colors">
          <Target class="w-4 h-4" /> Make Routine
        </button>
        <button @click="startConvert('task')" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border border-border text-sm font-medium text-foreground hover:bg-accent transition-colors">
          <ListTodo class="w-4 h-4" /> Make Task
        </button>
        <button @click="keep" :disabled="processing" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border border-border text-sm text-muted-foreground hover:text-foreground hover:bg-accent transition-colors">
          <Eye class="w-4 h-4" /> Keep for Later
        </button>
        <button @click="dismiss" :disabled="processing" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border border-border text-sm text-muted-foreground hover:text-foreground hover:bg-accent transition-colors">
          <X class="w-4 h-4" /> Dismiss
        </button>
      </div>
    </div>
  </div>
</template>
