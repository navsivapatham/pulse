<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppSidebar from '@/components/AppSidebar.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()
const showSidebar = computed(() => auth.isAuthenticated && !route.meta.guest)
</script>

<template>
  <div class="min-h-screen bg-background">
    <div v-if="showSidebar" class="flex">
      <AppSidebar />
      <main class="flex-1 min-h-screen">
        <router-view />
      </main>
    </div>
    <router-view v-else />
  </div>
</template>
