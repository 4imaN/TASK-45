<template>
  <div class="animate-fade-in">
    <div class="mb-6">
      <h1 class="page-title">Lab Checkout</h1>
      <p class="page-subtitle">Process resource check-out and check-in for lab sessions</p>
    </div>

    <ConflictBanner :message="error" />

    <!-- Mode tabs -->
    <div class="flex items-center gap-1 border-b border-slate-200 mb-6">
      <button
        v-for="mode in ['checkout', 'checkin']"
        :key="mode"
        @click="activeMode = mode"
        class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors capitalize"
        :class="activeMode === mode ? 'border-slate-800 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'"
      >{{ mode === 'checkout' ? 'Check Out' : 'Check In' }}</button>
    </div>

    <!-- Checkout panel -->
    <div v-if="activeMode === 'checkout'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Find approved loan -->
      <div class="card p-6">
        <h3 class="font-semibold text-slate-900 mb-4">Find Approved Loan</h3>
        <div class="space-y-3">
          <div>
            <label class="label">Search by student or loan ID</label>
            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <input v-model="coSearch" @input="searchLoans" type="text" class="input pl-9" placeholder="Student name or loan ID..." />
            </div>
          </div>

          <div v-if="coLoading" class="flex items-center justify-center py-4">
            <div class="animate-spin h-5 w-5 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
          </div>

          <div v-if="approvedLoans.length > 0" class="space-y-2 max-h-60 overflow-y-auto">
            <div
              v-for="loan in approvedLoans"
              :key="loan.id"
              @click="selectedLoan = loan"
              class="p-3 border rounded-lg cursor-pointer transition-all text-sm"
              :class="selectedLoan?.id === loan.id ? 'border-slate-700 bg-slate-50' : 'border-slate-200 hover:border-slate-300'"
            >
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-medium text-slate-900">{{ loan.user?.display_name }}</div>
                  <div class="text-xs text-slate-500">{{ loan.resource?.name }}</div>
                </div>
                <span class="badge badge-green text-xs">Approved</span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="selectedLoan" class="mt-4 pt-4 border-t border-slate-100">
          <div class="bg-slate-50 rounded-lg p-4 mb-4">
            <h4 class="font-semibold text-slate-900 text-sm mb-2">Selected: {{ selectedLoan.resource?.name }}</h4>
            <div class="text-xs text-slate-500 space-y-1">
              <div>Student: {{ selectedLoan.user?.display_name }}</div>
              <div v-if="selectedLoan.notes">Notes: {{ selectedLoan.notes }}</div>
            </div>
          </div>
          <ConflictBanner :message="coError" />
          <div v-if="coSuccess" class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2 rounded-lg mb-3 text-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Checked out successfully
          </div>
          <button
            @click="processCheckout()"
            :disabled="coLoading || coSuccess"
            class="btn-primary w-full justify-center"
          >
            <span v-if="coLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            <span v-else>Process Check-Out</span>
          </button>
        </div>
      </div>

      <!-- Active checkouts info -->
      <div class="card p-6">
        <h3 class="font-semibold text-slate-900 mb-4">Currently Checked Out</h3>
        <div v-if="activeCheckouts.length === 0 && !loadingActive" class="text-slate-400 text-sm text-center py-6">No active checkouts</div>
        <div v-if="loadingActive" class="flex justify-center py-6">
          <div class="animate-spin h-5 w-5 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
        </div>
        <div class="space-y-2 max-h-80 overflow-y-auto">
          <div v-for="co in activeCheckouts" :key="co.id" class="p-3 border border-slate-100 rounded-lg">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm font-medium text-slate-900">{{ co.loan_request?.resource?.name }}</div>
                <div class="text-xs text-slate-500">{{ co.loan_request?.user?.display_name }}</div>
              </div>
              <OverdueCountdown :due-date="co.due_date" :returned-at="co.returned_at" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Checkin panel -->
    <div v-if="activeMode === 'checkin'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="card p-6">
        <h3 class="font-semibold text-slate-900 mb-4">Find Checkout to Return</h3>
        <div class="space-y-3">
          <div>
            <label class="label">Search checked-out items</label>
            <input v-model="ciSearch" @input="searchCheckouts" type="text" class="input" placeholder="Student name or resource..." />
          </div>

          <div v-if="ciLoading" class="flex items-center justify-center py-4">
            <div class="animate-spin h-5 w-5 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
          </div>

          <div v-if="foundCheckouts.length > 0" class="space-y-2 max-h-60 overflow-y-auto">
            <div
              v-for="co in foundCheckouts"
              :key="co.id"
              @click="selectedCheckout = co"
              class="p-3 border rounded-lg cursor-pointer transition-all text-sm"
              :class="selectedCheckout?.id === co.id ? 'border-slate-700 bg-slate-50' : 'border-slate-200 hover:border-slate-300'"
            >
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-medium text-slate-900">{{ co.loan_request?.resource?.name }}</div>
                  <div class="text-xs text-slate-500">{{ co.loan_request?.user?.display_name }}</div>
                </div>
                <OverdueCountdown :due-date="co.due_date" />
              </div>
            </div>
          </div>
        </div>

        <div v-if="selectedCheckout" class="mt-4 pt-4 border-t border-slate-100 space-y-3">
          <div>
            <label class="label">Return Condition</label>
            <select v-model="ciForm.condition" class="input">
              <option value="">Select condition</option>
              <option value="new">New</option>
              <option value="good">Good</option>
              <option value="fair">Fair</option>
              <option value="poor">Poor</option>
              <option value="damaged">Damaged</option>
            </select>
          </div>
          <div>
            <label class="label">Return Notes</label>
            <textarea v-model="ciForm.notes" class="input resize-none" rows="2" placeholder="Any damage, missing parts..."></textarea>
          </div>
          <ConflictBanner :message="ciError" />
          <div v-if="ciSuccess" class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2 rounded-lg text-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Checked in successfully
          </div>
          <button
            @click="processCheckin()"
            :disabled="!ciForm.condition || ciLoading || ciSuccess"
            class="btn-success w-full justify-center"
          >
            <span v-if="ciLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            <span v-else>Process Return</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useLoansStore } from '../../stores/loans.js';
import api from '../../services/api.js';
import ConflictBanner from '../../components/ConflictBanner.vue';
import OverdueCountdown from '../../components/OverdueCountdown.vue';

const loansStore = useLoansStore();
const activeMode = ref('checkout');
const error = ref('');

// Checkout state
const coSearch = ref('');
const coLoading = ref(false);
const coError = ref('');
const coSuccess = ref(false);
const approvedLoans = ref([]);
const selectedLoan = ref(null);
const activeCheckouts = ref([]);
const loadingActive = ref(false);

// Checkin state
const ciSearch = ref('');
const ciLoading = ref(false);
const ciError = ref('');
const ciSuccess = ref(false);
const foundCheckouts = ref([]);
const selectedCheckout = ref(null);
const ciForm = ref({ condition: '', notes: '' });

let coTimer = null;
const searchLoans = () => {
  clearTimeout(coTimer);
  coTimer = setTimeout(async () => {
    if (!coSearch.value) { approvedLoans.value = []; return; }
    coLoading.value = true;
    try {
      const { data } = await api.get('/loans', { params: { status: 'approved', search: coSearch.value, all: true } });
      approvedLoans.value = data.data || data;
    } catch {} finally { coLoading.value = false; }
  }, 300);
};

let ciTimer = null;
const searchCheckouts = () => {
  clearTimeout(ciTimer);
  ciTimer = setTimeout(async () => {
    if (!ciSearch.value) { foundCheckouts.value = []; return; }
    ciLoading.value = true;
    try {
      const { data } = await api.get('/checkouts', { params: { status: 'active', search: ciSearch.value } });
      foundCheckouts.value = data.data || data;
    } catch {} finally { ciLoading.value = false; }
  }, 300);
};

const processCheckout = async () => {
  coError.value = '';
  coLoading.value = true;
  try {
    await loansStore.checkoutLoan(selectedLoan.value.id);
    coSuccess.value = true;
    selectedLoan.value = null;
    approvedLoans.value = [];
    coSearch.value = '';
    await loadActiveCheckouts();
  } catch (e) {
    coError.value = e.response?.data?.message || 'Failed to check out.';
  } finally {
    coLoading.value = false;
  }
};

const processCheckin = async () => {
  ciError.value = '';
  ciLoading.value = true;
  try {
    await loansStore.checkinCheckout(selectedCheckout.value.id, ciForm.value.condition, ciForm.value.notes);
    ciSuccess.value = true;
    selectedCheckout.value = null;
    foundCheckouts.value = [];
    ciSearch.value = '';
    ciForm.value = { condition: '', notes: '' };
    await loadActiveCheckouts();
  } catch (e) {
    ciError.value = e.response?.data?.message || 'Failed to check in.';
  } finally {
    ciLoading.value = false;
  }
};

const loadActiveCheckouts = async () => {
  loadingActive.value = true;
  try {
    const { data } = await api.get('/checkouts', { params: { status: 'active' } });
    activeCheckouts.value = (data.data || data).slice(0, 10);
  } catch {} finally { loadingActive.value = false; }
};

onMounted(loadActiveCheckouts);
</script>
