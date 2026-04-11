<template>
  <div class="min-h-screen flex items-center justify-center bg-stone-50">
    <div class="w-full max-w-md px-4">
      <div class="card overflow-hidden">
        <div class="bg-amber-500 px-8 py-6">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
              </svg>
            </div>
            <div>
              <h1 class="text-lg font-bold text-white">Change Password Required</h1>
              <p class="text-amber-100 text-sm">You must set a new password before continuing</p>
            </div>
          </div>
        </div>

        <div class="px-8 py-6">
          <ConflictBanner :message="error" />

          <div v-if="success" class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm">Password changed successfully. Redirecting...</span>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
              <label class="label">Current Password</label>
              <input
                v-model="form.current_password"
                type="password"
                class="input"
                placeholder="Your current password"
                autocomplete="current-password"
                :disabled="loading || success"
                required
              />
            </div>
            <div>
              <label class="label">New Password</label>
              <input
                v-model="form.new_password"
                type="password"
                class="input"
                placeholder="At least 8 characters"
                autocomplete="new-password"
                :disabled="loading || success"
                required
              />
              <p class="text-xs text-slate-400 mt-1">Must be at least 8 characters</p>
            </div>
            <div>
              <label class="label">Confirm New Password</label>
              <input
                v-model="form.new_password_confirmation"
                type="password"
                class="input"
                :class="{ 'border-red-300 focus:ring-red-500': form.new_password_confirmation && form.new_password !== form.new_password_confirmation }"
                placeholder="Repeat new password"
                autocomplete="new-password"
                :disabled="loading || success"
                required
              />
              <p v-if="form.new_password_confirmation && form.new_password !== form.new_password_confirmation" class="text-xs text-red-500 mt-1">Passwords do not match</p>
            </div>
            <button
              type="submit"
              :disabled="loading || success || form.new_password !== form.new_password_confirmation"
              class="w-full btn-primary justify-center py-2.5"
            >
              <span v-if="loading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
              <span v-else>Update Password</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth.js';
import ConflictBanner from '../../components/ConflictBanner.vue';

const authStore = useAuthStore();
const router = useRouter();

const form = ref({ current_password: '', new_password: '', new_password_confirmation: '' });
const loading = ref(false);
const error = ref('');
const success = ref(false);

const handleSubmit = async () => {
  error.value = '';
  loading.value = true;
  try {
    await authStore.changePassword(form.value);
    success.value = true;
    setTimeout(() => router.push('/catalog'), 1500);
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to change password. Please try again.';
  } finally {
    loading.value = false;
  }
};
</script>
