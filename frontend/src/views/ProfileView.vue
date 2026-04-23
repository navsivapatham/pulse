<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const name = ref('')
const email = ref('')
const profileMsg = ref('')
const profileErr = ref('')
const profileLoading = ref(false)

const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const passwordMsg = ref('')
const passwordErr = ref('')
const passwordLoading = ref(false)

onMounted(() => {
  name.value = auth.user?.name || ''
  email.value = auth.user?.email || ''
})

async function saveProfile() {
  profileMsg.value = ''
  profileErr.value = ''
  profileLoading.value = true
  try {
    await auth.updateProfile(name.value, email.value)
    profileMsg.value = 'Profile updated.'
  } catch (e: any) {
    profileErr.value = e.response?.data?.message || 'Update failed'
  } finally {
    profileLoading.value = false
  }
}

async function savePassword() {
  passwordMsg.value = ''
  passwordErr.value = ''
  passwordLoading.value = true
  try {
    await auth.changePassword(currentPassword.value, newPassword.value, confirmPassword.value)
    passwordMsg.value = 'Password changed.'
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
  } catch (e: any) {
    const errors = e.response?.data?.errors
    if (errors?.current_password) {
      passwordErr.value = errors.current_password[0]
    } else if (errors?.password) {
      passwordErr.value = errors.password[0]
    } else {
      passwordErr.value = e.response?.data?.message || 'Password change failed'
    }
  } finally {
    passwordLoading.value = false
  }
}
</script>

<template>
  <div class="p-6 max-w-lg">
    <h1 class="text-xl font-bold text-foreground mb-6">Profile</h1>

    <form @submit.prevent="saveProfile" class="space-y-4 mb-10">
      <h2 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Account details</h2>

      <div v-if="profileMsg" class="text-sm text-emerald-500 bg-emerald-500/10 px-3 py-2 rounded-md">{{ profileMsg }}</div>
      <div v-if="profileErr" class="text-sm text-destructive bg-destructive/10 px-3 py-2 rounded-md">{{ profileErr }}</div>

      <div class="space-y-2">
        <label class="text-sm font-medium text-foreground">Name</label>
        <input
          v-model="name"
          type="text"
          required
          class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
        />
      </div>

      <div class="space-y-2">
        <label class="text-sm font-medium text-foreground">Email</label>
        <input
          v-model="email"
          type="email"
          required
          class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
        />
      </div>

      <button
        type="submit"
        :disabled="profileLoading"
        class="py-2 px-4 rounded-md bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 transition-colors disabled:opacity-50"
      >
        {{ profileLoading ? 'Saving...' : 'Save changes' }}
      </button>
    </form>

    <form @submit.prevent="savePassword" class="space-y-4">
      <h2 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Change password</h2>

      <div v-if="passwordMsg" class="text-sm text-emerald-500 bg-emerald-500/10 px-3 py-2 rounded-md">{{ passwordMsg }}</div>
      <div v-if="passwordErr" class="text-sm text-destructive bg-destructive/10 px-3 py-2 rounded-md">{{ passwordErr }}</div>

      <div class="space-y-2">
        <label class="text-sm font-medium text-foreground">Current password</label>
        <input
          v-model="currentPassword"
          type="password"
          required
          class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
        />
      </div>

      <div class="space-y-2">
        <label class="text-sm font-medium text-foreground">New password</label>
        <input
          v-model="newPassword"
          type="password"
          required
          minlength="8"
          class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
        />
      </div>

      <div class="space-y-2">
        <label class="text-sm font-medium text-foreground">Confirm new password</label>
        <input
          v-model="confirmPassword"
          type="password"
          required
          minlength="8"
          class="w-full px-3 py-2 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring"
        />
      </div>

      <button
        type="submit"
        :disabled="passwordLoading"
        class="py-2 px-4 rounded-md bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 transition-colors disabled:opacity-50"
      >
        {{ passwordLoading ? 'Changing...' : 'Change password' }}
      </button>
    </form>
  </div>
</template>
