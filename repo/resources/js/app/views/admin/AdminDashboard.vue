<template>
  <div class="animate-fade-in">
    <div class="mb-8">
      <h1 class="page-title">Admin Dashboard</h1>
      <p class="page-subtitle">System overview and management tools</p>
    </div>

    <!-- Stats overview -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div class="card p-5">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Resources</span>
        </div>
        <div class="text-3xl font-bold text-slate-900">{{ stats.total_resources ?? '—' }}</div>
      </div>
      <div class="card p-5">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-9 h-9 bg-amber-50 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
          </div>
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Members</span>
        </div>
        <div class="text-3xl font-bold text-slate-900">{{ stats.total_members ?? '—' }}</div>
      </div>
      <div class="card p-5">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-9 h-9 bg-emerald-50 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Active Loans</span>
        </div>
        <div class="text-3xl font-bold text-slate-900">{{ stats.active_loans ?? '—' }}</div>
      </div>
      <div class="card p-5">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-9 h-9 bg-red-50 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
          </div>
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Overdue</span>
        </div>
        <div class="text-3xl font-bold text-red-600">{{ stats.overdue_items ?? '—' }}</div>
      </div>
    </div>

    <!-- Quick links -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
      <router-link
        v-for="link in adminLinks"
        :key="link.to"
        :to="link.to"
        class="card p-5 hover:shadow-md transition-all hover:-translate-y-0.5 group"
      >
        <div class="flex items-center gap-4">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0" :class="link.bg">
            <span class="text-xl">{{ link.icon }}</span>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 group-hover:text-slate-700">{{ link.label }}</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ link.description }}</p>
          </div>
          <svg class="w-4 h-4 text-slate-300 group-hover:text-slate-500 transition-colors ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
          </svg>
        </div>
      </router-link>
    </div>

    <!-- Recent audit activity -->
    <div class="card">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-semibold text-slate-900">Recent Activity</h3>
        <router-link to="/admin/audit" class="text-xs text-slate-500 hover:text-slate-700 transition-colors">View all</router-link>
      </div>
      <div v-if="recentActivity.length === 0" class="px-6 py-6 text-center text-slate-400 text-sm">
        No recent activity
      </div>
      <div class="divide-y divide-slate-100">
        <div v-for="entry in recentActivity" :key="entry.id" class="px-6 py-3 flex items-center gap-4">
          <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
            <span class="text-xs font-semibold text-slate-500">{{ initials(entry.user?.display_name) }}</span>
          </div>
          <div class="flex-1">
            <span class="text-sm text-slate-900">
              <span class="font-medium">{{ entry.user?.display_name || ('User #' + entry.user_id) }}</span>
              {{ ' ' + (entry.action || '') }}
            </span>
          </div>
          <span class="text-xs text-slate-400 flex-shrink-0">{{ timeAgo(entry.created_at) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api.js';

const stats = ref(null);
const recentActivity = ref([]);

const adminLinks = [
  { to: '/admin/scopes', label: 'Scope Management', description: 'Assign resource access scopes to users', icon: '🎯', bg: 'bg-blue-50' },
  { to: '/admin/holds', label: 'Account Holds', description: 'View and release account holds', icon: '🔒', bg: 'bg-red-50' },
  { to: '/admin/audit', label: 'Audit Log', description: 'Full system activity trail', icon: '📋', bg: 'bg-slate-50' },
  { to: '/data-quality', label: 'Data Quality', description: 'Remediation queue and deduplication', icon: '🧹', bg: 'bg-amber-50' },
  { to: '/data-quality/import', label: 'Bulk Import', description: 'Import resources from CSV/JSON', icon: '📥', bg: 'bg-emerald-50' },
  { to: '/approvals', label: 'Loan Approvals', description: 'Pending loan requests queue', icon: '✅', bg: 'bg-purple-50' },
  { to: '/admin/allowlists', label: 'Allowlists', description: 'Manage approved access', icon: '✅', bg: 'bg-emerald-50' },
  { to: '/admin/blacklists', label: 'Blacklists', description: 'Manage restricted access', icon: '🚫', bg: 'bg-red-50' },
  { to: '/admin/interventions', label: 'Interventions', description: 'Review hold triggers and actions', icon: '⚠️', bg: 'bg-orange-50' },
  { to: '/data-quality/aliases', label: 'Alias Normalization', description: 'Vendor/manufacturer naming standards', icon: '🏷️', bg: 'bg-blue-50' },
];

const initials = (name) => (name || '?').split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
const timeAgo = (d) => {
  if (!d) return '';
  const diff = Date.now() - new Date(d).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  return `${Math.floor(hrs / 24)}d ago`;
};

onMounted(async () => {
  try {
    const [statsRes, auditRes] = await Promise.allSettled([
      api.get('/admin/stats'),
      api.get('/admin/audit-logs', { params: { per_page: 8 } }),
    ]);
    if (statsRes.status === 'fulfilled') stats.value = statsRes.value.data;
    if (auditRes.status === 'fulfilled') recentActivity.value = auditRes.value.data.data || auditRes.value.data;
  } catch {}
});
</script>
