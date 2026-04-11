<template>
  <div class="animate-fade-in">
    <div class="mb-6 flex items-start justify-between">
      <div>
        <h1 class="page-title">Loan Approvals</h1>
        <p class="page-subtitle">Review and process pending loan requests</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="fetchPending()" class="btn-secondary text-xs">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Refresh
        </button>
        <select v-model="filter" @change="fetchPending()" class="input w-auto text-sm">
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="">All</option>
        </select>
      </div>
    </div>

    <!-- Mode tabs -->
    <div class="flex items-center gap-1 border-b border-slate-200 mb-6">
      <button @click="mode = 'loans'; fetchPending()" class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
        :class="mode === 'loans' ? 'border-slate-800 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'">
        Loan Requests
      </button>
      <button @click="mode = 'reservations'; fetchReservations()" class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
        :class="mode === 'reservations' ? 'border-slate-800 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'">
        Reservations
      </button>
    </div>

    <ConflictBanner :message="error" />

    <!-- Pending count banner -->
    <div v-if="mode === 'loans' && pendingCount > 0 && filter === 'pending'" class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 mb-6 flex items-center gap-3">
      <div class="w-9 h-9 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div>
        <p class="font-semibold text-amber-900">{{ pendingCount }} request{{ pendingCount !== 1 ? 's' : '' }} awaiting review</p>
        <p class="text-xs text-amber-700">Review and approve or reject each request below</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
    </div>

    <!-- Empty (loans) -->
    <div v-if="mode === 'loans' && !loading && loans.length === 0" class="card p-12 text-center">
      <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <p class="text-slate-500 font-medium">All clear</p>
      <p class="text-slate-400 text-sm mt-1">No requests to review</p>
    </div>

    <!-- Loans table -->
    <div v-if="mode === 'loans' && !loading && loans.length > 0" class="card overflow-hidden">
      <table class="table-base">
        <thead>
          <tr>
            <th>Student</th>
            <th>Resource</th>
            <th>Requested</th>
            <th>Notes</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="loan in loans" :key="loan.id">
            <td>
              <div class="font-medium text-slate-900">{{ loan.user?.display_name || loan.user?.username }}</div>
              <div class="text-xs text-slate-400">{{ loan.user?.email }}</div>
            </td>
            <td>
              <div class="font-medium text-slate-900">{{ loan.resource?.name }}</div>
              <div class="text-xs text-slate-400">{{ loan.resource?.type }}</div>
            </td>
            <td class="text-slate-500 text-xs">{{ formatDate(loan.created_at) }}</td>
            <td class="text-slate-500 text-xs max-w-32 truncate">{{ loan.notes || '—' }}</td>
            <td>
              <span :class="statusClass(loan.status)" class="badge">{{ formatStatus(loan.status) }}</span>
            </td>
            <td>
              <div v-if="loan.status === 'pending'" class="flex items-center gap-2">
                <button
                  @click="approve(loan)"
                  :disabled="actionLoading === loan.id"
                  class="btn-success text-xs px-3 py-1.5"
                >
                  <span v-if="actionLoading === loan.id" class="animate-spin h-3 w-3 border border-white border-t-transparent rounded-full"></span>
                  <span v-else>Approve</span>
                </button>
                <button
                  @click="openReject(loan)"
                  :disabled="actionLoading === loan.id"
                  class="btn-danger text-xs px-3 py-1.5"
                >Reject</button>
              </div>
              <div v-else-if="loan.status === 'approved'" class="flex items-center gap-2">
                <button
                  @click="checkout(loan)"
                  :disabled="checkoutLoading === loan.id"
                  class="btn-primary text-xs px-3 py-1.5"
                >
                  <span v-if="checkoutLoading === loan.id" class="animate-spin h-3 w-3 border border-white border-t-transparent rounded-full"></span>
                  <span v-else>Check Out</span>
                </button>
              </div>
              <div v-else-if="loan.rejection_reason" class="text-xs text-slate-500 italic max-w-24 truncate" :title="loan.rejection_reason">
                {{ loan.rejection_reason }}
              </div>
              <span v-else class="text-xs text-slate-400">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Reservations list -->
    <div v-if="mode === 'reservations' && !loading">
      <div v-if="reservations.length === 0" class="card p-12 text-center">
        <p class="text-slate-500">No reservations found</p>
      </div>
      <div v-else class="space-y-3">
        <div v-for="res in reservations" :key="res.id" class="card p-5">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <h3 class="font-semibold text-slate-900">{{ res.resource?.name || 'Unknown' }}</h3>
                <span :class="statusClass(res.status)" class="badge">{{ formatStatus(res.status) }}</span>
                <span class="badge badge-slate">{{ res.reservation_type }}</span>
              </div>
              <div class="text-xs text-slate-500 space-y-0.5">
                <div>Requested by: {{ res.user?.display_name || 'User #' + res.user_id }}</div>
                <div>Dates: {{ res.start_date }} to {{ res.end_date }}</div>
                <div v-if="res.venue_time_slot">Slot: {{ res.venue_time_slot.date }} {{ res.venue_time_slot.start_time }}–{{ res.venue_time_slot.end_time }}</div>
                <div v-if="res.notes">Notes: {{ res.notes }}</div>
              </div>
            </div>
            <div v-if="res.status === 'pending'" class="flex items-center gap-2 flex-shrink-0">
              <button @click="approveReservation(res)" :disabled="actionLoading === res.id" class="btn-success text-xs px-3 py-1.5">Approve</button>
              <button @click="rejectReservation(res, 'Rejected by staff')" :disabled="actionLoading === res.id" class="btn-danger text-xs px-3 py-1.5">Reject</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Reject modal -->
    <div v-if="rejectModal.show" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-semibold text-slate-900 mb-1">Reject Request</h3>
        <p class="text-sm text-slate-500 mb-4">Provide a reason for {{ rejectModal.loan?.user?.display_name }}</p>
        <div class="mb-4">
          <label class="label">Reason</label>
          <textarea v-model="rejectModal.reason" class="input resize-none" rows="3" placeholder="Explain why this request is rejected..."></textarea>
        </div>
        <div class="flex gap-3">
          <button @click="rejectModal.show = false" class="btn-secondary flex-1 justify-center">Cancel</button>
          <button @click="reject()" :disabled="actionLoading === rejectModal.loan?.id" class="btn-danger flex-1 justify-center">
            <span v-if="actionLoading === rejectModal.loan?.id" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            <span v-else>Reject</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useLoansStore } from '../../stores/loans.js';
import api from '../../services/api.js';
import ConflictBanner from '../../components/ConflictBanner.vue';

const loansStore = useLoansStore();
const loans = ref([]);
const loading = ref(true);
const error = ref('');
const actionLoading = ref(null);
const checkoutLoading = ref(null);
const filter = ref('pending');
const mode = ref('loans');
const reservations = ref([]);

const rejectModal = ref({ show: false, loan: null, reason: '' });

const pendingCount = computed(() => loans.value.filter(l => l.status === 'pending').length);

const statusClass = (s) => {
  const m = { pending: 'badge-amber', approved: 'badge-green', rejected: 'badge-red', checked_out: 'badge-blue', returned: 'badge-slate' };
  return m[s] || 'badge-slate';
};
const formatStatus = (s) => s?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '';
const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

const fetchPending = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params = filter.value ? { status: filter.value } : {};
    const { data } = await api.get('/loans', { params: { ...params, all: true } });
    loans.value = data.data || data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load loans.';
  } finally {
    loading.value = false;
  }
};

const approve = async (loan) => {
  actionLoading.value = loan.id;
  error.value = '';
  try {
    await loansStore.approveLoan(loan.id, 'approved');
    await fetchPending();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to approve loan.';
  } finally {
    actionLoading.value = null;
  }
};

const openReject = (loan) => {
  rejectModal.value = { show: true, loan, reason: '' };
};

const reject = async () => {
  const loan = rejectModal.value.loan;
  actionLoading.value = loan.id;
  error.value = '';
  try {
    await loansStore.approveLoan(loan.id, 'rejected', rejectModal.value.reason);
    rejectModal.value.show = false;
    await fetchPending();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to reject loan.';
  } finally {
    actionLoading.value = null;
  }
};

const checkout = async (loan) => {
  checkoutLoading.value = loan.id;
  error.value = '';
  try {
    await loansStore.checkoutLoan(loan.id);
    await fetchPending();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to check out loan.';
  } finally {
    checkoutLoading.value = null;
  }
};

const fetchReservations = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params = filter.value ? { status: filter.value } : {};
    const { data } = await api.get('/reservations', { params });
    reservations.value = data.data || data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load reservations.';
  } finally {
    loading.value = false;
  }
};

const approveReservation = async (res) => {
  actionLoading.value = res.id;
  error.value = '';
  try {
    const key = 'approve-res-' + res.id + '-' + Date.now();
    await api.post(`/reservations/${res.id}/approve`, { status: 'approved' }, { headers: { 'X-Idempotency-Key': key } });
    await fetchReservations();
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || 'Failed.';
  } finally {
    actionLoading.value = null;
  }
};

const rejectReservation = async (res, reason) => {
  actionLoading.value = res.id;
  try {
    const key = 'reject-res-' + res.id + '-' + Date.now();
    await api.post(`/reservations/${res.id}/approve`, { status: 'rejected', reason }, { headers: { 'X-Idempotency-Key': key } });
    await fetchReservations();
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || 'Failed.';
  } finally {
    actionLoading.value = null;
  }
};

onMounted(fetchPending);
</script>
