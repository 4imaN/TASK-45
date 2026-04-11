<template>
  <div class="animate-fade-in">
    <div class="mb-6 flex items-start justify-between">
      <div>
        <h1 class="page-title">My Reservations</h1>
        <p class="page-subtitle">Resources you've reserved for future availability</p>
      </div>
      <router-link to="/catalog" class="btn-secondary text-xs">
        + New Reservation
      </router-link>
    </div>

    <ConflictBanner :message="error" />

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
    </div>

    <!-- Empty -->
    <div v-if="!loading && reservations.length === 0" class="card p-12 text-center">
      <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
        </svg>
      </div>
      <p class="text-slate-500 font-medium">No active reservations</p>
      <p class="text-slate-400 text-sm mt-1">Reserve resources from the catalog when they're unavailable</p>
    </div>

    <!-- Reservations grid -->
    <div v-if="!loading && reservations.length > 0" class="space-y-3">
      <div v-for="res in reservations" :key="res.id" class="card p-5">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <h3 class="font-semibold text-slate-900">{{ res.resource?.name || 'Unknown Resource' }}</h3>
              <span :class="reservationStatusClass(res.status)" class="badge">{{ formatStatus(res.status) }}</span>
            </div>
            <div class="flex items-center gap-4 text-xs text-slate-500">
              <span v-if="res.resource?.type || res.resource?.resource_type">{{ res.resource.type || res.resource.resource_type }}</span>
              <span>Reserved {{ formatDate(res.created_at) }}</span>
              <span v-if="res.end_date" class="text-amber-600 font-medium">Expires {{ formatDate(res.end_date) }}</span>
            </div>
            <div v-if="res.notes" class="mt-2 text-xs text-slate-500 italic">{{ res.notes }}</div>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="res.status === 'pending' || res.status === 'approved'"
              @click="cancelReservation(res.id)"
              :disabled="cancelLoading === res.id"
              class="btn-secondary text-xs px-3 py-1.5"
            >
              <span v-if="cancelLoading === res.id" class="animate-spin h-3 w-3 border border-slate-400 border-t-slate-700 rounded-full"></span>
              <span v-else>Cancel</span>
            </button>
            <router-link v-if="res.status === 'approved'" :to="`/catalog/${res.resource_id}`" class="btn-success text-xs px-3 py-1.5">
              Request Loan
            </router-link>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api.js';
import ConflictBanner from '../../components/ConflictBanner.vue';

const reservations = ref([]);
const loading = ref(true);
const error = ref('');
const cancelLoading = ref(null);

const reservationStatusClass = (status) => {
  const m = { pending: 'badge-amber', approved: 'badge-green', rejected: 'badge-red', expired: 'badge-slate', cancelled: 'badge-slate', fulfilled: 'badge-blue' };
  return m[status] || 'badge-slate';
};
const formatStatus = (s) => s?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '';
const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

const fetchReservations = async () => {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/reservations');
    reservations.value = data.data || data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load reservations.';
  } finally {
    loading.value = false;
  }
};

const cancelReservation = async (id) => {
  cancelLoading.value = id;
  try {
    const key = 'cancel-res-' + id + '-' + Date.now();
    await api.post(`/reservations/${id}/cancel`, {}, { headers: { 'X-Idempotency-Key': key } });
    await fetchReservations();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to cancel reservation.';
  } finally {
    cancelLoading.value = null;
  }
};

onMounted(fetchReservations);
</script>
