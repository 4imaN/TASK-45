<template>
  <div v-if="authStore.user?.reminders_count > 0" class="relative">
    <button @click="showPanel = !showPanel" class="relative flex items-center gap-1.5 bg-red-500 hover:bg-red-600 transition-colors text-white text-xs font-medium rounded-full px-2.5 py-1">
      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
      </svg>
      {{ authStore.user.reminders_count }} due soon
    </button>

    <!-- Reminder panel dropdown -->
    <div v-if="showPanel" class="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl shadow-xl border border-slate-200 z-50 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-semibold text-slate-900 text-sm">Reminders</h3>
        <button @click="showPanel = false" class="text-slate-400 hover:text-slate-600 text-xs">Close</button>
      </div>
      <div v-if="loading" class="p-4 text-center">
        <div class="animate-spin h-5 w-5 border-2 border-slate-200 border-t-slate-700 rounded-full mx-auto"></div>
      </div>
      <div v-else-if="!reminders.length" class="p-4 text-center text-sm text-slate-400">No active reminders</div>
      <div v-else class="max-h-64 overflow-y-auto divide-y divide-slate-100">
        <div v-for="r in reminders" :key="r.id" class="px-4 py-3 flex items-start gap-3">
          <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" :class="r.reminder_type === 'overdue' ? 'bg-red-500' : 'bg-amber-400'"></div>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-slate-800 font-medium">
              {{ r.reminder_type === 'overdue' ? 'Overdue' : 'Due soon' }}
            </p>
            <p class="text-xs text-slate-500 truncate">{{ formatDate(r.scheduled_at) }}</p>
          </div>
          <button @click="acknowledge(r.id)" class="text-xs text-slate-400 hover:text-slate-600 flex-shrink-0">Dismiss</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import api from '../services/api.js';

const authStore = useAuthStore();
const showPanel = ref(false);
const reminders = ref([]);
const loading = ref(false);

const formatDate = (d) => d ? new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

watch(showPanel, async (open) => {
  if (open && !reminders.value.length) {
    loading.value = true;
    try {
      const { data } = await api.get('/reminders');
      reminders.value = data || [];
    } catch {} finally { loading.value = false; }
  }
});

const acknowledge = async (id) => {
  try {
    const key = 'ack-' + id + '-' + Date.now();
    await api.post(`/reminders/${id}/acknowledge`, {}, { headers: { 'X-Idempotency-Key': key } });
    reminders.value = reminders.value.filter(r => r.id !== id);
    if (authStore.user) {
      authStore.user.reminders_count = Math.max(0, (authStore.user.reminders_count || 0) - 1);
    }
  } catch {}
};
</script>
