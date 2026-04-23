<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useInboxStore, type InboxItem } from '@/stores/inbox'
import { Plus, Clock, CheckCircle2, X, RotateCcw } from 'lucide-vue-next'
import { formatDistanceToNow } from 'date-fns'

const inboxStore = useInboxStore()
const newItem = ref('')
const inputRef = ref<HTMLInputElement>()

async function capture() {
  const body = newItem.value.trim()
  if (!body) return
  await inboxStore.capture(body)
  newItem.value = ''
  inputRef.value?.focus()
}

function statusIcon(status: string) {
  switch (status) {
    case 'pending': return Clock
    case 'converted': return CheckCircle2
    case 'dismissed': return X
    case 'reviewed': return RotateCcw
    default: return Clock
  }
}

function statusColor(status: string) {
  switch (status) {
    case 'pending': return 'text-yellow-500'
    case 'converted': return 'text-green-500'
    case 'dismissed': return 'text-muted-foreground'
    case 'reviewed': return 'text-blue-500'
    default: return 'text-muted-foreground'
  }
}

function timeAgo(date: string) {
  return formatDistanceToNow(new Date(date), { addSuffix: true })
}

onMounted(() => {
  inboxStore.fetch()
  inputRef.value?.focus()
})
</script>

<template>
  <div class="max-w-2xl mx-auto px-6 py-8">
    <h2 class="text-xl font-semibold text-foreground">Inbox</h2>
    <p class="text-sm text-muted-foreground mt-1">Capture thoughts before they disappear.</p>

    <!-- Quick capture -->
    <form @submit.prevent="capture" class="mt-6 flex gap-2">
      <input
        ref="inputRef"
        v-model="newItem"
        placeholder="What's on your mind?"
        class="flex-1 px-4 py-3 rounded-lg border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring placeholder:text-muted-foreground"
        maxlength="1000"
      />
      <button
        type="submit"
        :disabled="!newItem.trim()"
        class="px-4 py-3 rounded-lg bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 transition-colors disabled:opacity-30"
      >
        <Plus class="w-4 h-4" />
      </button>
    </form>

    <!-- Items list -->
    <div class="mt-6 space-y-2">
      <div
        v-for="item in inboxStore.items"
        :key="item.id"
        class="flex items-start gap-3 px-4 py-3 rounded-lg border border-border"
        :class="item.status === 'dismissed' ? 'opacity-50' : ''"
      >
        <component :is="statusIcon(item.status)" class="w-4 h-4 mt-0.5 flex-shrink-0" :class="statusColor(item.status)" />
        <div class="flex-1 min-w-0">
          <p class="text-sm text-foreground">{{ item.body }}</p>
          <p class="text-xs text-muted-foreground mt-1">{{ timeAgo(item.created_at) }}</p>
        </div>
        <span class="text-xs px-2 py-0.5 rounded-full border border-border text-muted-foreground capitalize flex-shrink-0">
          {{ item.status }}
        </span>
      </div>
    </div>

    <p v-if="!inboxStore.items.length && !inboxStore.loading" class="text-sm text-muted-foreground py-8 text-center">
      Your inbox is empty. Capture a thought above.
    </p>
  </div>
</template>
