<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRoutinesStore } from '@/stores/routines'
import { useStatsStore } from '@/stores/stats'
import { useInboxStore } from '@/stores/inbox'
import { Flame, CheckCircle2, Circle, Inbox } from 'lucide-vue-next'

const auth = useAuthStore()
const routinesStore = useRoutinesStore()
const statsStore = useStatsStore()
const inboxStore = useInboxStore()

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 17) return 'Good afternoon'
  return 'Good evening'
})

const dailyRoutines = computed(() =>
  routinesStore.routines.filter((r) => r.cadence === 'daily' && r.is_active)
)

const completedCount = computed(() => dailyRoutines.value.filter((r) => r.is_completed_today).length)
const totalCount = computed(() => dailyRoutines.value.length)
const completionPct = computed(() => (totalCount.value > 0 ? Math.round((completedCount.value / totalCount.value) * 100) : 0))

const circumference = 2 * Math.PI * 42

async function toggle(routine: any) {
  if (routine.is_completed_today) {
    await routinesStore.uncomplete(routine.id)
  } else {
    await routinesStore.complete(routine.id)
  }
}

onMounted(async () => {
  await Promise.all([
    routinesStore.fetch({ active: true }),
    statsStore.fetchAll(),
    inboxStore.fetch('pending'),
  ])
})
</script>

<template>
  <div class="max-w-2xl mx-auto px-6 py-8">
    <h2 class="text-xl font-semibold text-foreground">{{ greeting }}, {{ auth.user?.name?.split(' ')[0] }}</h2>
    <p class="text-sm text-muted-foreground mt-1">Here's your pulse for today.</p>

    <!-- Completion ring + summary -->
    <div class="mt-8 flex items-center gap-8">
      <div class="relative w-28 h-28 flex-shrink-0">
        <svg class="w-28 h-28 -rotate-90" viewBox="0 0 100 100">
          <circle cx="50" cy="50" r="42" fill="none" stroke="hsl(var(--muted))" stroke-width="6" />
          <circle
            cx="50" cy="50" r="42" fill="none"
            stroke="hsl(var(--primary))"
            stroke-width="6"
            stroke-linecap="round"
            :stroke-dasharray="circumference"
            :stroke-dashoffset="circumference - (circumference * completionPct) / 100"
            class="transition-all duration-500"
          />
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
          <span class="text-2xl font-bold text-foreground">{{ completionPct }}%</span>
        </div>
      </div>

      <div class="space-y-2">
        <p class="text-sm text-muted-foreground">
          <span class="font-semibold text-foreground">{{ completedCount }}</span> of
          <span class="font-semibold text-foreground">{{ totalCount }}</span> daily routines done
        </p>
        <div v-if="statsStore.daily?.streak_leaders?.length" class="space-y-1">
          <p class="text-xs text-muted-foreground uppercase tracking-wide font-medium">Top streaks</p>
          <div v-for="s in statsStore.daily.streak_leaders.slice(0, 3)" :key="s.id" class="flex items-center gap-2 text-sm">
            <Flame class="w-3.5 h-3.5 text-orange-400" />
            <span class="text-foreground">{{ s.title }}</span>
            <span class="text-muted-foreground">{{ s.current_streak }}d</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Today's checklist -->
    <div class="mt-8">
      <h3 class="text-sm font-medium text-muted-foreground uppercase tracking-wide mb-3">Today's routines</h3>
      <div class="space-y-1">
        <button
          v-for="routine in dailyRoutines"
          :key="routine.id"
          @click="toggle(routine)"
          class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-border hover:bg-accent/50 transition-colors text-left"
          :class="routine.is_completed_today ? 'bg-primary/5' : ''"
        >
          <CheckCircle2 v-if="routine.is_completed_today" class="w-5 h-5 text-primary flex-shrink-0" />
          <Circle v-else class="w-5 h-5 text-muted-foreground flex-shrink-0" />
          <span class="flex-1 text-sm font-medium" :class="routine.is_completed_today ? 'text-muted-foreground line-through' : 'text-foreground'">
            {{ routine.title }}
          </span>
          <span v-if="routine.streak?.current_streak" class="flex items-center gap-1 text-xs text-orange-400">
            <Flame class="w-3 h-3" />
            {{ routine.streak.current_streak }}
          </span>
        </button>
      </div>
      <p v-if="!dailyRoutines.length" class="text-sm text-muted-foreground py-4 text-center">
        No daily routines yet. <router-link to="/routines" class="text-primary hover:underline">Add one</router-link>
      </p>
    </div>

    <!-- Inbox preview -->
    <div v-if="inboxStore.pendingCount > 0" class="mt-8">
      <router-link to="/review" class="flex items-center gap-3 px-4 py-3 rounded-lg border border-border hover:bg-accent/50 transition-colors">
        <Inbox class="w-5 h-5 text-primary" />
        <span class="text-sm font-medium text-foreground">{{ inboxStore.pendingCount }} items to review</span>
        <span class="ml-auto text-xs text-primary">Review &rarr;</span>
      </router-link>
    </div>
  </div>
</template>
