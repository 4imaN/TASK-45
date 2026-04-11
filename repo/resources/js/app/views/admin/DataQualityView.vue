<template>
  <div class="animate-fade-in">
    <div class="mb-6 flex items-start justify-between">
      <div>
        <h1 class="page-title">Data Quality</h1>
        <p class="page-subtitle">Remediation queue, deduplication, and data integrity tools</p>
      </div>
      <router-link to="/data-quality/import" class="btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
        </svg>
        Import Data
      </router-link>
    </div>

    <!-- Tab nav -->
    <div class="flex items-center gap-1 border-b border-slate-200 mb-6">
      <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id; loadTab()"
        class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === tab.id ? 'border-slate-800 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'">
        {{ tab.label }}
        <span v-if="tab.count > 0" class="ml-1.5 bg-red-100 text-red-700 text-xs rounded-full px-1.5 py-0.5">{{ tab.count }}</span>
      </button>
    </div>

    <ConflictBanner :message="error" />

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
    </div>

    <!-- Remediation Queue -->
    <div v-if="activeTab === 'remediation' && !loading">
      <div v-if="remediationItems.length === 0" class="card p-10 text-center">
        <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <p class="text-slate-500 font-medium">Remediation queue is empty</p>
        <p class="text-slate-400 text-sm mt-1">All data issues have been resolved</p>
      </div>
      <div v-else class="space-y-3">
        <div v-for="item in remediationItems" :key="item.id" class="card p-5">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span :class="severityClass(item.severity)" class="badge">{{ item.severity }}</span>
                <span class="badge badge-slate">{{ item.type }}</span>
                <h3 class="font-medium text-slate-900 text-sm">{{ item.title }}</h3>
              </div>
              <p class="text-sm text-slate-600 mb-2">{{ item.description }}</p>
              <div v-if="item.affected_resource" class="text-xs text-slate-400">
                Resource: <span class="font-medium text-slate-600">{{ item.affected_resource.name }}</span>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <button
                v-if="item.auto_fixable"
                @click="autoFix(item.id)"
                :disabled="fixLoading === item.id"
                class="btn-success text-xs px-3 py-1.5"
              >
                <span v-if="fixLoading === item.id" class="animate-spin h-3 w-3 border border-white border-t-transparent rounded-full"></span>
                <span v-else>Auto-Fix</span>
              </button>
              <button @click="dismissItem(item.id)" :disabled="dismissLoading === item.id" class="btn-secondary text-xs px-3 py-1.5">Dismiss</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Duplicate Candidates -->
    <div v-if="activeTab === 'duplicates' && !loading">
      <div v-if="duplicates.length === 0" class="card p-10 text-center">
        <p class="text-slate-500 font-medium">No duplicate candidates found</p>
      </div>
      <div v-else class="space-y-4">
        <div v-for="group in duplicates" :key="group.id" class="card overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100 bg-amber-50 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="badge badge-amber">{{ Math.round(group.confidence * 100) }}% similar</span>
              <span class="text-sm font-semibold text-amber-900">Potential Duplicate</span>
            </div>
            <div class="flex items-center gap-2">
              <button @click="mergeDuplicates(group)" :disabled="mergeLoading === group.id" class="btn-primary text-xs px-3 py-1.5">
                <span v-if="mergeLoading === group.id" class="animate-spin h-3 w-3 border border-white border-t-transparent rounded-full"></span>
                <span v-else>Merge</span>
              </button>
              <button @click="dismissDuplicate(group.id)" class="btn-secondary text-xs px-3 py-1.5">Not Duplicate</button>
            </div>
          </div>
          <div class="divide-y divide-slate-100">
            <div v-for="record in group.records" :key="record.id" class="px-5 py-3 flex items-center gap-4">
              <div class="flex-1">
                <div class="font-medium text-slate-900 text-sm">{{ record.name }}</div>
                <div class="text-xs text-slate-500">{{ record.type }} | {{ record.department }}</div>
              </div>
              <div class="text-xs text-slate-400 font-mono">ID: {{ record.id }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics -->
    <div v-if="activeTab === 'stats' && !loading && dataStats">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="card p-5 text-center">
          <div class="text-3xl font-bold text-slate-900">{{ dataStats.total_records ?? '—' }}</div>
          <div class="text-xs text-slate-500 mt-1">Total Records</div>
        </div>
        <div class="card p-5 text-center">
          <div class="text-3xl font-bold text-red-600">{{ dataStats.records_with_issues ?? '—' }}</div>
          <div class="text-xs text-slate-500 mt-1">With Issues</div>
        </div>
        <div class="card p-5 text-center">
          <div class="text-3xl font-bold text-amber-600">{{ dataStats.duplicate_candidates ?? '—' }}</div>
          <div class="text-xs text-slate-500 mt-1">Potential Duplicates</div>
        </div>
        <div class="card p-5 text-center">
          <div class="text-3xl font-bold text-emerald-600">{{ dataStats.completeness_pct ?? '—' }}%</div>
          <div class="text-xs text-slate-500 mt-1">Data Completeness</div>
        </div>
      </div>

      <div class="card p-6">
        <h3 class="font-semibold text-slate-900 mb-4">Field Completeness</h3>
        <div v-if="dataStats.field_stats" class="space-y-3">
          <div v-for="(pct, field) in dataStats.field_stats" :key="field">
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm font-medium text-slate-700 capitalize">{{ field.replace(/_/g, ' ') }}</span>
              <span class="text-xs font-semibold" :class="pct >= 90 ? 'text-emerald-600' : pct >= 70 ? 'text-amber-600' : 'text-red-600'">{{ pct }}%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2">
              <div class="h-2 rounded-full transition-all" :class="pct >= 90 ? 'bg-emerald-500' : pct >= 70 ? 'bg-amber-400' : 'bg-red-500'" :style="`width: ${pct}%`"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../services/api.js';
import ConflictBanner from '../../components/ConflictBanner.vue';

const activeTab = ref('remediation');
const loading = ref(true);
const error = ref('');
const remediationItems = ref([]);
const duplicates = ref([]);
const dataStats = ref(null);
const fixLoading = ref(null);
const dismissLoading = ref(null);
const mergeLoading = ref(null);

const tabs = computed(() => [
  { id: 'remediation', label: 'Remediation Queue', count: remediationItems.value.length },
  { id: 'duplicates', label: 'Duplicate Candidates', count: duplicates.value.length },
  { id: 'stats', label: 'Statistics', count: 0 },
]);

const severityClass = (s) => {
  const m = { critical: 'badge-red', high: 'badge-red', medium: 'badge-amber', low: 'badge-slate', info: 'badge-blue' };
  return m[s] || 'badge-slate';
};

const loadTab = async () => {
  loading.value = true;
  error.value = '';
  try {
    if (activeTab.value === 'remediation') {
      const { data } = await api.get('/data-quality/remediation');
      remediationItems.value = data.data || data;
    } else if (activeTab.value === 'duplicates') {
      const { data } = await api.get('/data-quality/duplicates');
      duplicates.value = data.data || data;
    } else if (activeTab.value === 'stats') {
      const { data } = await api.get('/data-quality/stats');
      dataStats.value = data;
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load data.';
  } finally {
    loading.value = false;
  }
};

const autoFix = async (id) => {
  fixLoading.value = id;
  try {
    await api.post(`/data-quality/remediation/${id}`, { action: 'remediate' }, { headers: { 'X-Idempotency-Key': 'fix-' + id + '-' + Date.now() } });
    remediationItems.value = remediationItems.value.filter(i => i.id !== id);
  } catch (e) {
    error.value = e.response?.data?.message || 'Auto-fix failed.';
  } finally {
    fixLoading.value = null;
  }
};

const dismissItem = async (id) => {
  dismissLoading.value = id;
  try {
    await api.post(`/data-quality/remediation/${id}`, { action: 'skip' }, { headers: { 'X-Idempotency-Key': 'dismiss-' + id + '-' + Date.now() } });
    remediationItems.value = remediationItems.value.filter(i => i.id !== id);
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to dismiss.';
  } finally {
    dismissLoading.value = null;
  }
};

const mergeDuplicates = async (group) => {
  mergeLoading.value = group.id;
  try {
    await api.post(`/data-quality/duplicates/${group.id}`, { action: 'confirmed' }, { headers: { 'X-Idempotency-Key': 'merge-' + group.id + '-' + Date.now() } });
    duplicates.value = duplicates.value.filter(d => d.id !== group.id);
  } catch (e) {
    error.value = e.response?.data?.message || 'Merge failed.';
  } finally {
    mergeLoading.value = null;
  }
};

const dismissDuplicate = async (id) => {
  try {
    await api.post(`/data-quality/duplicates/${id}`, { action: 'dismissed' }, { headers: { 'X-Idempotency-Key': 'dismiss-dup-' + id + '-' + Date.now() } });
    duplicates.value = duplicates.value.filter(d => d.id !== id);
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to dismiss.';
  }
};

onMounted(() => loadTab());
</script>
