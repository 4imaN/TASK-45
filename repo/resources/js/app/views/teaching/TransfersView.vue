<template>
  <div class="animate-fade-in">
    <div class="mb-6 flex items-start justify-between">
      <div>
        <h1 class="page-title">Transfers</h1>
        <p class="page-subtitle">Manage inter-department resource transfers</p>
      </div>
      <button @click="showInitiate = true" class="btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 3M21 7.5H7.5" />
        </svg>
        Initiate Transfer
      </button>
    </div>

    <ConflictBanner :message="error" />

    <!-- Tabs -->
    <div class="flex items-center gap-1 border-b border-slate-200 mb-6">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        @click="activeTab = tab.value; fetchTransfers()"
        class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === tab.value ? 'border-slate-800 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'"
      >{{ tab.label }}</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
    </div>

    <!-- Empty -->
    <div v-if="!loading && transfers.length === 0" class="card p-12 text-center">
      <p class="text-slate-500 font-medium">No transfers found</p>
    </div>

    <!-- Transfers table -->
    <div v-if="!loading && transfers.length > 0" class="card overflow-hidden">
      <table class="table-base">
        <thead>
          <tr>
            <th>ID</th>
            <th>Resource</th>
            <th>From</th>
            <th>To</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="transfer in transfers" :key="transfer.id">
            <td class="font-mono text-xs text-slate-400">{{ transfer.id?.toString().padStart(6, '0') }}</td>
            <td>
              <div class="font-medium text-slate-900">{{ transfer.resource?.name }}</div>
              <div class="text-xs text-slate-400">Qty: {{ transfer.quantity || 1 }}</div>
            </td>
            <td class="text-slate-600">{{ transfer.from_department?.name || transfer.from_department || '—' }}</td>
            <td class="text-slate-600">{{ transfer.to_department?.name || transfer.to_department || '—' }}</td>
            <td>
              <span :class="transferStatusClass(transfer.status)" class="badge">{{ formatStatus(transfer.status) }}</span>
            </td>
            <td class="text-xs text-slate-500">{{ formatDate(transfer.created_at) }}</td>
            <td>
              <div class="flex items-center gap-2">
                <button
                  v-if="transfer.status === 'pending'"
                  @click="approveTransfer(transfer.id)"
                  :disabled="actionLoading === transfer.id"
                  class="btn-success text-xs px-3 py-1.5"
                >
                  <span v-if="actionLoading === transfer.id" class="animate-spin h-3 w-3 border border-white border-t-transparent rounded-full"></span>
                  <span v-else>Approve</span>
                </button>
                <button
                  v-if="transfer.status === 'approved'"
                  @click="markInTransit(transfer.id)"
                  :disabled="actionLoading === transfer.id"
                  class="btn-primary text-xs px-3 py-1.5"
                >Mark In Transit</button>
                <button
                  v-if="transfer.status === 'in_transit'"
                  @click="completeTransfer(transfer.id)"
                  :disabled="actionLoading === transfer.id"
                  class="btn-success text-xs px-3 py-1.5"
                >Complete</button>
                <button
                  v-if="transfer.status === 'pending' || transfer.status === 'approved'"
                  @click="cancelTransfer(transfer.id)"
                  :disabled="actionLoading === transfer.id"
                  class="btn-secondary text-xs px-3 py-1.5"
                >Cancel</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Initiate modal -->
    <div v-if="showInitiate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full mx-4">
        <h3 class="font-semibold text-slate-900 mb-4">Initiate Resource Transfer</h3>
        <ConflictBanner :message="initiateError" />
        <div class="space-y-4">
          <div>
            <label class="label">Inventory Lot ID</label>
            <input v-model="initiateForm.inventory_lot_id" type="number" class="input" placeholder="Lot ID" required />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="label">From Department ID</label>
              <input v-model="initiateForm.from_department_id" type="number" class="input" placeholder="Source dept ID" required />
            </div>
            <div>
              <label class="label">To Department ID</label>
              <input v-model="initiateForm.to_department_id" type="number" class="input" placeholder="Dest dept ID" required />
            </div>
          </div>
          <div>
            <label class="label">Reason</label>
            <textarea v-model="initiateForm.reason" class="input resize-none" rows="2"></textarea>
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showInitiate = false; initiateError = ''" class="btn-secondary flex-1 justify-center">Cancel</button>
          <button @click="initiateTransfer()" :disabled="initiateLoading" class="btn-primary flex-1 justify-center">
            <span v-if="initiateLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            <span v-else>Initiate</span>
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

const transfers = ref([]);
const loading = ref(true);
const error = ref('');
const actionLoading = ref(null);
const activeTab = ref('pending');
const showInitiate = ref(false);
const initiateLoading = ref(false);
const initiateError = ref('');
const initiateForm = ref({ inventory_lot_id: '', from_department_id: '', to_department_id: '', reason: '' });

const tabs = [
  { label: 'Pending', value: 'pending' },
  { label: 'Approved', value: 'approved' },
  { label: 'In Transit', value: 'in_transit' },
  { label: 'Completed', value: 'completed' },
  { label: 'All', value: '' },
];

const transferStatusClass = (s) => {
  const m = { pending: 'badge-amber', approved: 'badge-blue', in_transit: 'badge-purple', completed: 'badge-green', cancelled: 'badge-slate', rejected: 'badge-red' };
  return m[s] || 'badge-slate';
};
const formatStatus = (s) => s?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '';
const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

const fetchTransfers = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params = activeTab.value ? { status: activeTab.value } : {};
    const { data } = await api.get('/transfers', { params });
    transfers.value = data.data || data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load transfers.';
  } finally {
    loading.value = false;
  }
};

const approveTransfer = async (id) => {
  actionLoading.value = id;
  try {
    await api.post(`/transfers/${id}/approve`, {}, { headers: { 'X-Idempotency-Key': 'approve-' + id + '-' + Date.now() } });
    await fetchTransfers();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to approve transfer.';
  } finally {
    actionLoading.value = null;
  }
};

const markInTransit = async (id) => {
  actionLoading.value = id;
  try {
    await api.post(`/transfers/${id}/in-transit`, {}, { headers: { 'X-Idempotency-Key': 'transit-' + id + '-' + Date.now() } });
    await fetchTransfers();
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || 'Failed to mark in transit.';
  } finally {
    actionLoading.value = null;
  }
};

const completeTransfer = async (id) => {
  actionLoading.value = id;
  try {
    await api.post(`/transfers/${id}/complete`, {}, { headers: { 'X-Idempotency-Key': 'complete-' + id + '-' + Date.now() } });
    await fetchTransfers();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to complete transfer.';
  } finally {
    actionLoading.value = null;
  }
};

const cancelTransfer = async (id) => {
  actionLoading.value = id;
  try {
    await api.post(`/transfers/${id}/cancel`, {}, { headers: { 'X-Idempotency-Key': 'cancel-' + id + '-' + Date.now() } });
    await fetchTransfers();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to cancel transfer.';
  } finally {
    actionLoading.value = null;
  }
};

const initiateTransfer = async () => {
  initiateError.value = '';
  initiateLoading.value = true;
  try {
    const key = 'transfer-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    await api.post('/transfers', {
      ...initiateForm.value,
      idempotency_key: key,
    }, { headers: { 'X-Idempotency-Key': key } });
    showInitiate.value = false;
    initiateForm.value = { inventory_lot_id: '', from_department_id: '', to_department_id: '', reason: '' };
    await fetchTransfers();
  } catch (e) {
    initiateError.value = e.response?.data?.error || e.response?.data?.message || 'Failed to initiate transfer.';
  } finally {
    initiateLoading.value = false;
  }
};

onMounted(fetchTransfers);
</script>
