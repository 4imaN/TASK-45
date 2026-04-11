<template>
  <div class="min-h-screen" style="background:#f8f7f5">
    <nav v-if="authStore.isAuthenticated" class="bg-slate-900 text-white px-6 flex items-center justify-between" style="height:56px;">
      <div class="flex items-center h-full">
        <div class="flex items-center gap-2 pr-6 border-r border-slate-700 h-full mr-2">
          <div class="w-7 h-7 bg-amber-500 rounded-md flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <span class="text-sm font-semibold text-white tracking-tight">Campus Resources</span>
        </div>
        <router-link to="/catalog" class="nav-link">Catalog</router-link>
        <router-link to="/loans" class="nav-link">My Loans</router-link>
        <router-link to="/reservations" class="nav-link">Reservations</router-link>
        <router-link to="/membership" class="nav-link">Membership</router-link>
        <router-link v-if="authStore.isStaff" to="/approvals" class="nav-link">Approvals</router-link>
        <router-link v-if="authStore.isStaff" to="/transfers" class="nav-link">Transfers</router-link>
        <router-link v-if="authStore.isAdmin" to="/admin" class="nav-link">Admin</router-link>
        <router-link v-if="authStore.isStaff" to="/data-quality" class="nav-link">Data Quality</router-link>
      </div>
      <div class="flex items-center gap-4">
        <ReminderBadge />
        <div class="flex items-center gap-2 pl-4 border-l border-slate-700">
          <div class="w-7 h-7 bg-slate-700 rounded-full flex items-center justify-center text-xs font-semibold text-slate-300">
            {{ initials }}
          </div>
          <span class="text-sm text-slate-300">{{ authStore.user?.display_name }}</span>
          <button @click="logout" class="ml-1 text-xs text-slate-400 hover:text-white transition-colors px-2 py-1 rounded hover:bg-slate-700">
            Sign out
          </button>
        </div>
      </div>
    </nav>
    <main :class="authStore.isAuthenticated ? 'px-6 py-6 max-w-7xl mx-auto' : ''">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from './stores/auth.js';
import ReminderBadge from './components/ReminderBadge.vue';

const authStore = useAuthStore();

const initials = computed(() => {
  const name = authStore.user?.display_name || '';
  return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() || '?';
});

const logout = async () => {
  await authStore.logout();
};
</script>

<style>
.nav-link {
  position: relative;
  display: flex;
  align-items: center;
  height: 56px;
  padding: 0 12px;
  font-size: 0.875rem;
  font-weight: 500;
  color: #94a3b8;
  text-decoration: none;
  transition: color 0.15s;
}
.nav-link::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: #f59e0b;
  transform: scaleX(0);
  transition: transform 0.2s ease;
}
.nav-link:hover {
  color: #fff;
}
.nav-link.router-link-active {
  color: #fff;
}
.nav-link.router-link-active::after {
  transform: scaleX(1);
}
</style>
