<template>
  <div class="animate-fade-in">
    <div class="mb-6">
      <h1 class="page-title">Audit Log</h1>
      <p class="page-subtitle">Complete system activity trail</p>
    </div>

    <!-- Filters -->
    <div class="card p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-2">
          <label class="label">Search</label>
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input v-model="filters.search" @input="debouncedFetch" type="text" class="input pl-9" placeholder="Search by user, event, or description..." />
          </div>
        </div>
        <div>
          <label class="label">Event Type</label>
          <select v-model="filters.event" @change="fetchLogs()" class="input">
            <option value="">All Events</option>
            <option value="login">Login</option>
            <option value="logout">Logout</option>
            <option value="loan.created">Loan Created</option>
            <option value="loan.approved">Loan Approved</option>
            <option value="loan.rejected">Loan Rejected</option>
            <option value="checkout.created">Checkout</option>
            <option value="checkin.completed">Checkin</option>
            <option value="hold.placed">Hold Placed</option>
            <option value="hold.released">Hold Released</option>
            <option value="scope.assigned">Scope Assigned</option>
            <option value="import.completed">Import</option>
          </select>
        </div>
        <div>
          <label class="label">Date Range</label>
          <select v-model="filters.range" @change="fetchLogs()" class="input">
            <option value="">All Time</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
          </select>
        </div>
      </div>
      <div class="flex items-center justify-between mt-3">
        <p class="text-xs text-slate-400">Showing {{ logs.length }} entries</p>
        <button @click="exportLogs()" class="btn-secondary text-xs">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          Export CSV
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
    </div>

    <ConflictBanner :message="error" />

    <!-- Log table -->
    <div v-if="!loading && logs.length > 0" class="card overflow-hidden">
      <table class="table-base">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>User</th>
            <th>Action</th>
            <th>Target</th>
            <th>IP</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="entry in logs" :key="entry.id">
            <td class="font-mono text-xs text-slate-500 whitespace-nowrap">{{ formatDateTime(entry.created_at) }}</td>
            <td>
              <div class="font-medium text-slate-900 text-sm">{{ entry.user?.display_name || entry.user?.username || 'System' }}</div>
              <div v-if="entry.user?.email" class="text-xs text-slate-400">{{ entry.user.email }}</div>
            </td>
            <td>
              <span :class="eventClass(entry.action)" class="badge font-mono text-xs">{{ entry.action }}</span>
            </td>
            <td class="text-slate-600 text-sm max-w-xs truncate" :title="entry.auditable_type ? (entry.auditable_type.split('\\').pop() + ' #' + entry.auditable_id) : ''">{{ entry.auditable_type ? (entry.auditable_type.split('\\').pop() + ' #' + entry.auditable_id) : '' }}</td>
            <td class="font-mono text-xs text-slate-400">{{ entry.ip_address || '—' }}</td>
            <td>
              <button
                v-if="entry.context && Object.keys(entry.context).length > 0"
                @click="selectedEntry = entry"
                class="text-xs text-blue-600 hover:underline"
              >View</button>
              <span v-else class="text-xs text-slate-300">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty -->
    <div v-if="!loading && logs.length === 0 && !error" class="card p-12 text-center">
      <p class="text-slate-500">No audit entries found</p>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between mt-4">
      <p class="text-sm text-slate-500">
        {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
      </p>
      <div class="flex items-center gap-2">
        <button :disabled="pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)" class="btn-secondary text-xs px-3">Previous</button>
        <span class="text-sm text-slate-600 px-2">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button :disabled="pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)" class="btn-secondary text-xs px-3">Next</button>
      </div>
    </div>

    <!-- Entry detail modal -->
    <div v-if="selectedEntry" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 backdrop-blur-sm" @click.self="selectedEntry = null">
      <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-lg w-full mx-4">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-slate-900">Event Details</h3>
          <button @click="selectedEntry = null" class="text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="space-y-2">
          <div class="flex items-start gap-3">
            <span class="label mb-0 w-24 flex-shrink-0">Action</span>
            <span :class="eventClass(selectedEntry.action)" class="badge font-mono text-xs">{{ selectedEntry.action }}</span>
          </div>
          <div class="flex items-start gap-3">
            <span class="label mb-0 w-24 flex-shrink-0">Time</span>
            <span class="text-sm text-slate-700 font-mono">{{ formatDateTime(selectedEntry.created_at) }}</span>
          </div>
          <div class="flex items-start gap-3">
            <span class="label mb-0 w-24 flex-shrink-0">User</span>
            <span class="text-sm text-slate-700">{{ selectedEntry.user?.display_name || 'System' }}</span>
          </div>
          <div class="flex items-start gap-3">
            <span class="label mb-0 w-24 flex-shrink-0">Description</span>
            <span class="text-sm text-slate-700">{{ selectedEntry.description }}</span>
          </div>
          <div v-if="selectedEntry.metadata" class="mt-4">
            <span class="label">Metadata</span>
            <pre class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs text-slate-700 overflow-auto max-h-48 font-mono">{{ JSON.stringify(selectedEntry.metadata, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../services/api.js';
import ConflictBanner from '../../components/ConflictBanner.vue';

const logs = ref([]);
const pagination = ref(null);
const loading = ref(true);
const error = ref('');
const selectedEntry = ref(null);
const filters = reactive({ search: '', event: '', range: '' });

let debounceTimer = null;
const debouncedFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchLogs(), 350);
};

const eventClass = (event) => {
  if (!event) return 'badge-slate';
  if (event.includes('login') || event.includes('logout')) return 'badge-purple';
  if (event.includes('error') || event.includes('reject') || event.includes('hold')) return 'badge-red';
  if (event.includes('approv') || event.includes('complete') || event.includes('release')) return 'badge-green';
  if (event.includes('loan') || event.includes('checkout') || event.includes('checkin')) return 'badge-blue';
  if (event.includes('import') || event.includes('scope')) return 'badge-amber';
  return 'badge-slate';
};

const formatDateTime = (d) => d ? new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—';

const fetchLogs = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/admin/audit-logs', { params: { ...filters, page, per_page: 25 } });
    logs.value = data.data || data;
    pagination.value = data.meta || null;
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load audit log.';
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => fetchLogs(page);

const exportLogs = async () => {
  try {
    const response = await api.get('/admin/audit-logs/export', { params: { ...filters }, responseType: 'blob' });
    const url = URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `audit-log-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    error.value = 'Export failed.';
  }
};

onMounted(() => fetchLogs());
</script>
