<script setup lang="ts">
import { onMounted } from 'vue'
import { useStatsStore } from '@/stores/stats'
import { Flame, Trophy } from 'lucide-vue-next'

const stats = useStatsStore()

function pctColor(pct: number): string {
  if (pct >= 80) return 'bg-green-500'
  if (pct >= 50) return 'bg-yellow-500'
  if (pct > 0) return 'bg-orange-500'
  return 'bg-muted'
}

function pctTextColor(pct: number): string {
  if (pct >= 80) return 'text-green-500'
  if (pct >= 50) return 'text-yellow-500'
  if (pct > 0) return 'text-orange-500'
  return 'text-muted-foreground'
}

onMounted(() => stats.fetchAll())
</script>

<template>
  <div class="max-w-2xl mx-auto px-6 py-8">
    <h2 class="text-xl font-semibold text-foreground">Stats</h2>
    <p class="text-sm text-muted-foreground mt-1">Your weekly scorecard and streak leaderboard.</p>

    <!-- Weekly scorecard -->
    <div v-if="stats.weekly" class="mt-8">
      <h3 class="text-sm font-medium text-muted-foreground uppercase tracking-wide mb-3">This Week</h3>
      <div class="grid grid-cols-7 gap-2">
        <div
          v-for="day in stats.weekly.days"
          :key="day.date"
          class="rounded-lg border border-border p-3 text-center"
        >
          <p class="text-xs text-muted-foreground font-medium">{{ day.day_name }}</p>
          <div class="mt-2 w-8 h-8 rounded-full mx-auto flex items-center justify-center text-xs font-bold text-white" :class="pctColor(day.percentage)">
            {{ day.percentage }}
          </div>
          <p class="text-xs text-muted-foreground mt-1.5">{{ day.completed }}/{{ day.total }}</p>
        </div>
      </div>
    </div>

    <!-- Today's snapshot -->
    <div v-if="stats.daily" class="mt-8 rounded-lg border border-border p-5">
      <h3 class="text-sm font-medium text-muted-foreground uppercase tracking-wide mb-3">Today</h3>
      <div class="flex items-center gap-6">
        <div>
          <p class="text-3xl font-bold" :class="pctTextColor(stats.daily.completion_percentage)">
            {{ stats.daily.completion_percentage }}%
          </p>
          <p class="text-xs text-muted-foreground mt-1">
            {{ stats.daily.completed }} of {{ stats.daily.total_routines }} done
          </p>
        </div>
      </div>
    </div>

    <!-- Streak leaderboard -->
    <div v-if="stats.streaks.length" class="mt-8">
      <h3 class="text-sm font-medium text-muted-foreground uppercase tracking-wide mb-3">
        <Trophy class="w-4 h-4 inline-block mr-1 -mt-0.5" />
        Streak Leaderboard
      </h3>
      <div class="space-y-2">
        <div
          v-for="(s, i) in stats.streaks"
          :key="s.id"
          class="flex items-center gap-3 px-4 py-3 rounded-lg border border-border"
        >
          <span class="text-sm font-bold text-muted-foreground w-5">{{ i + 1 }}</span>
          <div class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: s.color || '#6366F1' }" />
          <span class="text-sm font-medium text-foreground flex-1">{{ s.title }}</span>
          <span class="text-xs px-2 py-0.5 rounded-full bg-muted text-muted-foreground capitalize">{{ s.cadence }}</span>
          <span class="flex items-center gap-1 text-sm font-semibold text-orange-400">
            <Flame class="w-4 h-4" />
            {{ s.current_streak }}
          </span>
          <span class="text-xs text-muted-foreground">best: {{ s.longest_streak }}</span>
        </div>
      </div>
    </div>

    <p v-if="!stats.streaks.length && !stats.loading" class="text-sm text-muted-foreground py-8 text-center">
      No routines yet. <router-link to="/routines" class="text-primary hover:underline">Create some</router-link> to start tracking.
    </p>
  </div>
</template>
