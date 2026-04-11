<template>
  <div class="min-h-screen flex items-center justify-center" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);">
    <div class="w-full max-w-sm px-4">
      <!-- Logo mark -->
      <div class="flex flex-col items-center mb-8">
        <div class="w-14 h-14 bg-amber-500 rounded-2xl flex items-center justify-center shadow-lg mb-4">
          <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
        </div>
        <h1 class="text-xl font-bold text-white tracking-tight">Campus Resources</h1>
        <p class="text-slate-400 text-sm mt-1">Resource Lending & Membership Platform</p>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-8 pt-8 pb-6">
          <h2 class="text-lg font-semibold text-slate-900 mb-6">Sign in to your account</h2>

          <ConflictBanner :message="error" />

          <form @submit.prevent="handleLogin" class="space-y-4">
            <div>
              <label class="label">Username</label>
              <input
                v-model="username"
                type="text"
                class="input"
                placeholder="Enter your username"
                autocomplete="username"
                :disabled="loading"
                required
              />
            </div>
            <div>
              <label class="label">Password</label>
              <div class="relative">
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  class="input pr-10"
                  placeholder="Enter your password"
                  autocomplete="current-password"
                  :disabled="loading"
                  required
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                >
                  <svg v-if="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                  </svg>
                </button>
              </div>
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="w-full btn-primary justify-center py-2.5 mt-2"
            >
              <span v-if="loading" class="loading-spinner w-4 h-4"></span>
              <span v-else>Sign In</span>
            </button>
          </form>
        </div>
        <div class="px-8 py-4 bg-slate-50 border-t border-slate-100">
          <p class="text-xs text-slate-400 text-center">Local authentication — HTTPS secured</p>
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

const username = ref('');
const password = ref('');
const showPassword = ref(false);
const loading = ref(false);
const error = ref('');

const handleLogin = async () => {
  error.value = '';
  loading.value = true;
  try {
    const data = await authStore.login(username.value, password.value);
    if (data.user?.force_password_change) {
      router.push('/change-password');
    } else {
      router.push('/catalog');
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Invalid credentials. Please try again.';
  } finally {
    loading.value = false;
  }
};
</script>
