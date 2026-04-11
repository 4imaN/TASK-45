<template>
  <div class="animate-fade-in">
    <div class="mb-6 flex items-start justify-between">
      <div>
        <h1 class="page-title">My Loans</h1>
        <p class="page-subtitle">Track your current and past resource loans</p>
      </div>
      <div class="flex items-center gap-2">
        <select v-model="statusFilter" @change="fetchLoans()" class="input w-auto text-sm">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="checked_out">Checked Out</option>
          <option value="returned">Returned</option>
          <option value="rejected">Rejected</option>
          <option value="overdue">Overdue</option>
        </select>
      </div>
    </div>

    <ConflictBanner :message="error" />

    <!-- Stats bar -->
    <div v-if="!loading && loans.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
      <div class="card p-4">
        <div class="text-2xl font-bold text-slate-900">{{ stats.active }}</div>
        <div class="text-xs text-slate-500 mt-0.5">Active Loans</div>
      </div>
      <div class="card p-4">
        <div class="text-2xl font-bold text-amber-600">{{ stats.pending }}</div>
        <div class="text-xs text-slate-500 mt-0.5">Pending Approval</div>
      </div>
      <div class="card p-4">
        <div class="text-2xl font-bold text-red-600">{{ stats.overdue }}</div>
        <div class="text-xs text-slate-500 mt-0.5">Overdue</div>
      </div>
      <div class="card p-4">
        <div class="text-2xl font-bold text-slate-400">{{ stats.returned }}</div>
        <div class="text-xs text-slate-500 mt-0.5">Returned</div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
    </div>

    <!-- Empty -->
    <div v-if="!loading && loans.length === 0" class="card p-12 text-center">
      <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
        </svg>
      </div>
      <p class="text-slate-500 font-medium">No loans found</p>
      <p class="text-slate-400 text-sm mt-1">Visit the catalog to request resources</p>
      <router-link to="/catalog" class="btn-primary mt-4 inline-flex">Browse Catalog</router-link>
    </div>

    <!-- Loans list -->
    <div v-if="!loading && loans.length > 0" class="space-y-3">
      <div
        v-for="loan in loans"
        :key="loan.id"
        class="card p-5 hover:shadow-md transition-shadow"
        :class="{ 'border-red-200 bg-red-50/30': isOverdue(loan) }"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <h3 class="font-semibold text-slate-900">{{ loan.resource?.name || 'Unknown Resource' }}</h3>
              <span :class="statusClass(loan.status)" class="badge">{{ formatStatus(loan.status) }}</span>
              <span v-if="isOverdue(loan)" class="badge badge-red animate-pulse">OVERDUE</span>
            </div>
            <div class="flex items-center gap-4 text-xs text-slate-500">
              <span v-if="loan.resource?.type">{{ loan.resource.type }}</span>
              <span>Requested {{ formatDate(loan.created_at) }}</span>
              <span v-if="loan.checkout?.checked_out_at">Checked out {{ formatDate(loan.checkout.checked_out_at) }}</span>
            </div>
            <div v-if="loan.notes" class="mt-2 text-xs text-slate-500 italic">{{ loan.notes }}</div>
            <div v-if="loan.rejection_reason" class="mt-2 text-xs text-red-600">
              Reason: {{ loan.rejection_reason }}
            </div>
          </div>

          <div class="text-right flex-shrink-0">
            <div v-if="loan.checkout?.due_date" class="mb-2">
              <OverdueCountdown :due-date="loan.checkout.due_date" :returned-at="loan.checkout.returned_at" />
            </div>
            <div class="flex items-center gap-2 justify-end mt-2">
              <button
                v-if="loan.status === 'checked_out' && !loan.checkout?.returned_at && canRenew(loan)"
                @click="renew(loan)"
                :disabled="actionLoading === loan.id"
                class="btn-secondary text-xs px-3 py-1.5"
              >
                <span v-if="actionLoading === loan.id" class="animate-spin h-3 w-3 border border-slate-400 border-t-slate-700 rounded-full"></span>
                <span v-else>Renew</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="actionError" class="mt-4">
      <ConflictBanner :message="actionError" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useLoansStore } from '../../stores/loans.js';
import { storeToRefs } from 'pinia';
import ConflictBanner from '../../components/ConflictBanner.vue';
import OverdueCountdown from '../../components/OverdueCountdown.vue';

const loansStore = useLoansStore();
const { loans, loading } = storeToRefs(loansStore);
const error = ref('');
const actionError = ref('');
const actionLoading = ref(null);
const statusFilter = ref('');

const stats = computed(() => ({
  active: loans.value.filter(l => l.status === 'checked_out').length,
  pending: loans.value.filter(l => l.status === 'pending' || l.status === 'approved').length,
  overdue: loans.value.filter(l => isOverdue(l)).length,
  returned: loans.value.filter(l => l.status === 'returned').length,
}));

const isOverdue = (loan) => {
  if (!loan.checkout?.due_date || loan.checkout?.returned_at) return false;
  return new Date(loan.checkout.due_date) < new Date();
};

const canRenew = (loan) => {
  if (!loan.checkout) return false;
  const renewals = loan.checkout.renewal_count || 0;
  const maxRenewals = loan.resource?.loan_rules?.max_renewals ?? 1;
  return renewals < maxRenewals;
};

const statusClass = (status) => {
  const m = {
    pending: 'badge-amber',
    approved: 'badge-blue',
    checked_out: 'badge-blue',
    returned: 'badge-slate',
    rejected: 'badge-red',
    overdue: 'badge-red',
    cancelled: 'badge-slate',
  };
  return m[status] || 'badge-slate';
};

const formatStatus = (status) => status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Unknown';
const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

const fetchLoans = async () => {
  error.value = '';
  try {
    const params = statusFilter.value ? { status: statusFilter.value } : {};
    await loansStore.fetchLoans(params);
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load loans.';
  }
};

const renew = async (loan) => {
  actionError.value = '';
  actionLoading.value = loan.id;
  try {
    await loansStore.renewCheckout(loan.checkout.id);
    await fetchLoans();
  } catch (e) {
    actionError.value = e.response?.data?.message || 'Failed to renew loan.';
  } finally {
    actionLoading.value = null;
  }
};

onMounted(fetchLoans);
</script>
