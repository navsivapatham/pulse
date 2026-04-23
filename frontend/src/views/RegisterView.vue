<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const name = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.register(name.value, email.value, password.value, password_confirmation.value)
    router.push('/dashboard')
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Registration failed'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-background px-4">
    <div class="w-full max-w-sm space-y-6">
      <div class="text-center">
        <h1 class="text-2xl font-bold tracking-tight text-foreground">Pulse</h1>
        <p class="text-sm text-muted-foreground mt-1">Create your account</p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div v-if="error" class="text-sm text-destructive bg-destructive/10 px-3 py-2 rounded-md">{{ error }}</div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-foreground">Name</label>
          <input v-model="name" type="text" required class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-foreground">Email</label>
          <input v-model="email" type="email" required class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-foreground">Password</label>
          <input v-model="password" type="password" required class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-foreground">Confirm password</label>
          <input v-model="password_confirmation" type="password" required class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
        </div>

        <button type="submit" :disabled="loading" class="w-full py-2 px-4 rounded-md bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 transition-colors disabled:opacity-50">
          {{ loading ? 'Creating account...' : 'Create account' }}
        </button>
      </form>

      <p class="text-center text-sm text-muted-foreground">
        Already have an account? <router-link to="/login" class="text-primary hover:underline">Sign in</router-link>
      </p>
    </div>
  </div>
</template>
