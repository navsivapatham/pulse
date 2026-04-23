<script setup lang="ts">
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useInboxStore } from '@/stores/inbox'
import {
  LayoutDashboard,
  Inbox,
  ListChecks,
  Target,
  BarChart3,
  LogOut,
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const inboxStore = useInboxStore()

const navItems = [
  { name: 'Dashboard', path: '/dashboard', icon: LayoutDashboard },
  { name: 'Inbox', path: '/inbox', icon: Inbox },
  { name: 'Review', path: '/review', icon: ListChecks },
  { name: 'Routines', path: '/routines', icon: Target },
  { name: 'Stats', path: '/stats', icon: BarChart3 },
]

function logout() {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <aside class="w-60 border-r border-border bg-card min-h-screen flex flex-col">
    <div class="p-5 border-b border-border">
      <h1 class="text-lg font-bold tracking-tight text-foreground">Pulse</h1>
      <p class="text-xs text-muted-foreground mt-0.5">Daily rhythm tracker</p>
    </div>

    <nav class="flex-1 p-3 space-y-1">
      <router-link
        v-for="item in navItems"
        :key="item.path"
        :to="item.path"
        class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors"
        :class="
          route.path === item.path
            ? 'bg-primary/10 text-primary'
            : 'text-muted-foreground hover:text-foreground hover:bg-accent'
        "
      >
        <component :is="item.icon" class="w-4 h-4" />
        {{ item.name }}
        <span
          v-if="item.name === 'Inbox' && inboxStore.pendingCount > 0"
          class="ml-auto bg-primary/15 text-primary text-xs font-semibold px-1.5 py-0.5 rounded-full"
        >
          {{ inboxStore.pendingCount }}
        </span>
      </router-link>
    </nav>

    <div class="p-3 border-t border-border">
      <div class="flex items-center gap-3 px-3 py-2">
        <div class="w-7 h-7 rounded-full bg-primary/15 flex items-center justify-center text-xs font-semibold text-primary">
          {{ auth.user?.name?.[0]?.toUpperCase() ?? '?' }}
        </div>
        <span class="text-sm font-medium text-foreground flex-1 truncate">{{ auth.user?.name }}</span>
        <button @click="logout" class="text-muted-foreground hover:text-foreground transition-colors">
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </div>
  </aside>
</template>
