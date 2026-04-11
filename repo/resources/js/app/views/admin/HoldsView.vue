<template>
  <div class="animate-fade-in">
    <div class="mb-6 flex items-start justify-between">
      <div>
        <h1 class="page-title">Account Holds</h1>
        <p class="page-subtitle">Review and manage account restrictions</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="fetchHolds()" class="btn-secondary text-xs">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Refresh
        </button>
        <button @click="showPlace = true" class="btn-danger">Place Hold</button>
      </div>
    </div>

    <ConflictBanner :message="error" />

    <!-- Summary -->
    <div v-if="!loading" class="grid grid-cols-3 gap-4 mb-6">
      <div class="card p-4">
        <div class="text-2xl font-bold text-red-600">{{ holds.filter(h => h.status === 'active').length }}</div>
        <div class="text-xs text-slate-500 mt-0.5">Active Holds</div>
      </div>
      <div class="card p-4">
        <div class="text-2xl font-bold text-slate-400">{{ holds.filter(h => h.status !== 'active').length }}</div>
        <div class="text-xs text-slate-500 mt-0.5">Released</div>
      </div>
      <div class="card p-4">
        <div class="text-2xl font-bold text-slate-900">{{ holds.length }}</div>
        <div class="text-xs text-slate-500 mt-0.5">Total</div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
    </div>

    <!-- Empty -->
    <div v-if="!loading && holds.length === 0" class="card p-12 text-center">
      <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
        </svg>
      </div>
      <p class="text-slate-500 font-medium">No holds found</p>
    </div>

    <!-- Holds table -->
    <div v-if="!loading && holds.length > 0" class="card overflow-hidden">
      <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-100">
        <label class="label mb-0">Filter:</label>
        <select v-model="filter" @change="fetchHolds()" class="input w-auto text-sm">
          <option value="">All</option>
          <option value="active">Active only</option>
          <option value="released">Released only</option>
        </select>
      </div>
      <table class="table-base">
        <thead>
          <tr>
            <th>User</th>
            <th>Reason</th>
            <th>Type</th>
            <th>Placed</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="hold in holds" :key="hold.id">
            <td>
              <div class="font-medium text-slate-900">{{ hold.user?.display_name }}</div>
              <div class="text-xs text-slate-400">{{ hold.user?.username }}</div>
            </td>
            <td class="text-slate-600 max-w-xs">
              <span class="line-clamp-2 text-sm">{{ hold.reason }}</span>
            </td>
            <td>
              <span class="badge badge-slate capitalize">{{ hold.hold_type || 'manual' }}</span>
            </td>
            <td class="text-xs text-slate-500">{{ formatDate(hold.triggered_at) }}</td>
            <td>
              <span :class="hold.status === 'active' ? 'badge-red' : 'badge-green'" class="badge">
                {{ hold.status === 'active' ? 'Active' : hold.status === 'released' ? 'Released' : 'Expired' }}
              </span>
            </td>
            <td>
              <button
                v-if="hold.status === 'active'"
                @click="openRelease(hold)"
                :disabled="actionLoading === hold.id"
                class="btn-success text-xs px-3 py-1.5"
              >
                <span v-if="actionLoading === hold.id" class="animate-spin h-3 w-3 border border-white border-t-transparent rounded-full"></span>
                <span v-else>Release</span>
              </button>
              <div v-else class="text-xs text-slate-400">
                Released {{ formatDate(hold.released_at) }}
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Release modal -->
    <div v-if="releaseModal.show" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-semibold text-slate-900 mb-1">Release Hold</h3>
        <p class="text-sm text-slate-500 mb-4">Release hold for <span class="font-medium">{{ releaseModal.hold?.user?.display_name }}</span></p>
        <div>
          <label class="label">Release Reason</label>
          <textarea v-model="releaseModal.reason" class="input resize-none" rows="3" placeholder="Reason for releasing this hold..."></textarea>
        </div>
        <div class="flex gap-3 mt-4">
          <button @click="releaseModal.show = false" class="btn-secondary flex-1 justify-center">Cancel</button>
          <button @click="releaseHold()" :disabled="actionLoading === releaseModal.hold?.id" class="btn-success flex-1 justify-center">
            <span v-if="actionLoading === releaseModal.hold?.id" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            <span v-else>Release</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Place hold modal -->
    <div v-if="showPlace" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-semibold text-slate-900 mb-4">Place Account Hold</h3>
        <ConflictBanner :message="placeError" />
        <div class="space-y-3">
          <div>
            <label class="label">User ID</label>
            <input v-model="placeForm.user_id" type="text" class="input" placeholder="User ID or username" />
          </div>
          <div>
            <label class="label">Hold Type</label>
            <select v-model="placeForm.type" class="input">
              <option value="manual">Manual</option>
              <option value="system">System</option>
            </select>
          </div>
          <div>
            <label class="label">Reason</label>
            <textarea v-model="placeForm.reason" class="input resize-none" rows="3" placeholder="Reason for this hold..."></textarea>
          </div>
        </div>
        <div class="flex gap-3 mt-4">
          <button @click="showPlace = false; placeError = ''" class="btn-secondary flex-1 justify-center">Cancel</button>
          <button @click="placeHold()" :disabled="placeLoading" class="btn-danger flex-1 justify-center">
            <span v-if="placeLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            <span v-else>Place Hold</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api.js';
import ConflictBanner from '../../components/ConflictBanner.vue';

const holds = ref([]);
const loading = ref(true);
const error = ref('');
const actionLoading = ref(null);
const filter = ref('active');

const releaseModal = ref({ show: false, hold: null, reason: '' });
const showPlace = ref(false);
const placeForm = ref({ user_id: '', type: 'manual', reason: '' });
const placeLoading = ref(false);
const placeError = ref('');

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

const fetchHolds = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params = {};
    if (filter.value === 'active') params.active = true;
    if (filter.value === 'released') params.released = true;
    const { data } = await api.get('/admin/holds', { params });
    holds.value = data.data || data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load holds.';
  } finally {
    loading.value = false;
  }
};

const openRelease = (hold) => {
  releaseModal.value = { show: true, hold, reason: '' };
};

const releaseHold = async () => {
  const hold = releaseModal.value.hold;
  actionLoading.value = hold.id;
  error.value = '';
  try {
    await api.post(`/admin/holds/${hold.id}/release`, { reason: releaseModal.value.reason }, { headers: { 'X-Idempotency-Key': 'release-' + hold.id + '-' + Date.now() } });
    releaseModal.value.show = false;
    await fetchHolds();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to release hold.';
  } finally {
    actionLoading.value = null;
  }
};

const placeHold = async () => {
  placeError.value = '';
  placeLoading.value = true;
  try {
    const key = 'hold-' + Date.now();
    await api.post('/admin/holds', {
      user_id: placeForm.value.user_id,
      hold_type: placeForm.value.type,
      reason: placeForm.value.reason,
    }, { headers: { 'X-Idempotency-Key': key } });
    showPlace.value = false;
    placeForm.value = { user_id: '', type: 'manual', reason: '' };
    await fetchHolds();
  } catch (e) {
    placeError.value = e.response?.data?.message || 'Failed to place hold.';
  } finally {
    placeLoading.value = false;
  }
};

onMounted(fetchHolds);
</script>
