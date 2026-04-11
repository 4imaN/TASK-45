<template>
  <div class="animate-fade-in">
    <!-- Back -->
    <router-link to="/catalog" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 mb-6 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Catalog
    </router-link>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
    </div>

    <ConflictBanner :message="error" />

    <!-- Content -->
    <div v-if="resource && !loading" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main info -->
      <div class="lg:col-span-2 space-y-4">
        <div class="card p-6">
          <div class="flex items-start gap-4 mb-4">
            <div class="w-14 h-14 rounded-xl bg-slate-100 flex items-center justify-center text-2xl flex-shrink-0">
              {{ typeIcons[resource.type] || '📦' }}
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="badge badge-slate">{{ resource.type }}</span>
                <span v-if="resource.category" class="badge badge-purple">{{ resource.category }}</span>
              </div>
              <h1 class="text-xl font-bold text-slate-900">{{ resource.name }}</h1>
              <p v-if="resource.department" class="text-sm text-slate-500 mt-0.5">{{ resource.department }}</p>
            </div>
          </div>
          <p v-if="resource.description" class="text-slate-600 text-sm leading-relaxed">{{ resource.description }}</p>
        </div>

        <!-- Specifications -->
        <div v-if="resource.vendor || resource.manufacturer || resource.model_number" class="card p-6">
          <h3 class="font-semibold text-slate-900 mb-4">Specifications</h3>
          <dl class="grid grid-cols-2 gap-3">
            <div v-if="resource.vendor" class="bg-slate-50 rounded-lg p-3">
              <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-0.5">Vendor</dt>
              <dd class="text-sm text-slate-800 font-medium">{{ resource.vendor }}</dd>
            </div>
            <div v-if="resource.manufacturer" class="bg-slate-50 rounded-lg p-3">
              <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-0.5">Manufacturer</dt>
              <dd class="text-sm text-slate-800 font-medium">{{ resource.manufacturer }}</dd>
            </div>
            <div v-if="resource.model_number" class="bg-slate-50 rounded-lg p-3">
              <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-0.5">Model</dt>
              <dd class="text-sm text-slate-800 font-medium">{{ resource.model_number }}</dd>
            </div>
            <div v-if="resource.category" class="bg-slate-50 rounded-lg p-3">
              <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-0.5">Category</dt>
              <dd class="text-sm text-slate-800 font-medium">{{ resource.category }}</dd>
            </div>
          </dl>
        </div>

        <!-- Recent availability -->
        <div v-if="resource.units && resource.units.length" class="card p-6">
          <h3 class="font-semibold text-slate-900 mb-4">Units / Copies</h3>
          <table class="table-base">
            <thead>
              <tr>
                <th>Unit ID</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Location</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="unit in resource.units" :key="unit.id">
                <td class="font-mono text-xs">{{ unit.serial_number || unit.id }}</td>
                <td>
                  <span :class="conditionClass(unit.condition)" class="badge">{{ unit.condition }}</span>
                </td>
                <td>
                  <span :class="unit.available ? 'badge-green' : 'badge-red'" class="badge">
                    {{ unit.available ? 'Available' : 'Checked Out' }}
                  </span>
                </td>
                <td class="text-slate-500">{{ unit.location || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Sidebar: Request -->
      <div class="space-y-4">
        <!-- Availability card -->
        <div class="card p-5">
          <h3 class="font-semibold text-slate-900 mb-4">Availability</h3>
          <div class="flex items-center justify-between mb-4">
            <div>
              <div class="text-3xl font-bold" :class="resource.available_quantity > 0 ? 'text-emerald-600' : 'text-red-500'">
                {{ resource.available_quantity }}
              </div>
              <div class="text-xs text-slate-500">of {{ resource.total_quantity }} available</div>
            </div>
            <div :class="resource.available_quantity > 0 ? 'badge-green' : 'badge-red'" class="badge text-sm px-3 py-1">
              {{ resource.available_quantity > 0 ? 'Available' : 'Unavailable' }}
            </div>
          </div>

          <!-- Availability bar -->
          <div class="w-full bg-slate-100 rounded-full h-2 mb-4">
            <div
              class="h-2 rounded-full transition-all"
              :class="resource.available_quantity > 0 ? 'bg-emerald-500' : 'bg-red-400'"
              :style="`width: ${resource.total_quantity ? (resource.available_quantity / resource.total_quantity) * 100 : 0}%`"
            ></div>
          </div>


          <ConflictBanner :message="requestError" />

          <div v-if="requestSuccess" class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2 rounded-lg mb-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Request submitted successfully!
          </div>

          <div class="space-y-2">
            <div v-if="userClasses.length">
              <label class="label">Class</label>
              <select v-model="requestClassId" class="input" required>
                <option value="" disabled>Select a class…</option>
                <option v-for="cls in userClasses" :key="cls.id" :value="cls.id">
                  {{ cls.course?.name || '' }} — {{ cls.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="label">Notes (optional)</label>
              <textarea v-model="requestNotes" class="input resize-none" rows="2" placeholder="Any special requirements..."></textarea>
            </div>
            <button
              v-if="resource.type === 'equipment'"
              @click="requestLoan"
              :disabled="resource.available_quantity === 0 || requesting || requestSuccess"
              class="w-full btn-primary justify-center"
            >
              <span v-if="requesting" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
              <span v-else-if="resource.available_quantity === 0">Not Available</span>
              <span v-else-if="requestSuccess">Requested</span>
              <span v-else>Request Loan</span>
            </button>
            <!-- Date inputs for equipment reservations -->
            <div v-if="resource.type === 'equipment'" class="space-y-2 mb-3">
              <div>
                <label class="label">Start Date</label>
                <input v-model="reservationStartDate" type="date" class="input" :min="new Date(Date.now() + 86400000).toISOString().split('T')[0]" />
              </div>
              <div>
                <label class="label">End Date</label>
                <input v-model="reservationEndDate" type="date" class="input" :min="reservationStartDate || new Date(Date.now() + 86400000).toISOString().split('T')[0]" />
              </div>
            </div>
            <!-- Venue slot picker (only for venue resources) -->
            <div v-if="resource.type === 'venue' && venue?.available_slots?.length" class="mb-1">
              <label class="label">Select Time Slot</label>
              <select v-model="selectedSlotId" class="input text-sm">
                <option value="">Choose a slot...</option>
                <option v-for="slot in venue.available_slots" :key="slot.id" :value="slot.id">
                  {{ slot.date }} {{ slot.start_time.slice(0,5) }}–{{ slot.end_time.slice(0,5) }}
                </option>
              </select>
            </div>
            <button
              v-if="resource.type !== 'entitlement_package'"
              @click="requestReservation"
              :disabled="requesting || reservationSuccess"
              class="w-full btn-secondary justify-center"
            >
              <span v-if="reservationSuccess">Reservation Made</span>
              <span v-else>Reserve for Later</span>
            </button>
          </div>
        </div>

        <!-- Loan policy -->
        <div class="card p-5">
          <h3 class="font-semibold text-slate-900 mb-3 text-sm">Loan Policy</h3>
          <ul class="space-y-2 text-xs text-slate-600">
            <li class="flex items-center gap-2">
              <span class="w-1.5 h-1.5 bg-blue-400 rounded-full flex-shrink-0"></span>
              Requires staff approval
            </li>
            <li class="flex items-center gap-2">
              <span class="w-1.5 h-1.5 bg-amber-400 rounded-full flex-shrink-0"></span>
              Standard loan period: 7 days
            </li>
            <li class="flex items-center gap-2">
              <span class="w-1.5 h-1.5 bg-purple-400 rounded-full flex-shrink-0"></span>
              1 renewal allowed (if no waitlist)
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useCatalogStore } from '../../stores/catalog.js';
import { useLoansStore } from '../../stores/loans.js';
import api from '../../services/api.js';
import ConflictBanner from '../../components/ConflictBanner.vue';
import { useIdempotency } from '../../composables/useIdempotency.js';

const route = useRoute();
const catalogStore = useCatalogStore();
const loansStore = useLoansStore();
const { generateKey } = useIdempotency();

const resource = ref(null);
const venue = ref(null);
const selectedSlotId = ref('');
const loading = ref(true);
const error = ref('');
const requestError = ref('');
const requesting = ref(false);
const requestSuccess = ref(false);
const reservationSuccess = ref(false);
const requestNotes = ref('');
const requestClassId = ref('');
const reservationStartDate = ref('');
const reservationEndDate = ref('');
const userClasses = ref([]);

const typeIcons = { equipment: '⚙️', venue: '🏢', entitlement_package: '📦' };

const conditionClass = (c) => {
  const m = { new: 'badge-green', good: 'badge-green', fair: 'badge-amber', poor: 'badge-red', damaged: 'badge-red' };
  return m[c] || 'badge-slate';
};

const requestLoan = async () => {
  requestError.value = '';
  if (userClasses.value.length && !requestClassId.value) {
    requestError.value = 'Please select a class for this request.';
    return;
  }
  requesting.value = true;
  try {
    await loansStore.createLoan({
      resource_id: resource.value.id,
      notes: requestNotes.value || undefined,
      class_id: requestClassId.value ? parseInt(requestClassId.value) : undefined,
      idempotency_key: generateKey(),
    });
    requestSuccess.value = true;
    await catalogStore.fetchResource(route.params.id).then(r => resource.value = r);
  } catch (e) {
    requestError.value = e.response?.data?.message || 'Failed to submit request.';
  } finally {
    requesting.value = false;
  }
};

const requestReservation = async () => {
  requestError.value = '';
  if (userClasses.value.length && !requestClassId.value) {
    requestError.value = 'Please select a class for this request.';
    return;
  }
  requesting.value = true;
  try {
    const key = generateKey();
    const payload = {
      resource_id: resource.value.id,
      notes: requestNotes.value || undefined,
      idempotency_key: key,
    };

    if (resource.value.type === 'venue' || resource.value.resource_type === 'venue') {
      if (!selectedSlotId.value) {
        requestError.value = 'Please select a time slot for venue reservations.';
        requesting.value = false;
        return;
      }
      payload.reservation_type = 'venue';
      payload.venue_id = venue.value?.id;
      payload.venue_time_slot_id = parseInt(selectedSlotId.value);
    } else {
      payload.reservation_type = 'equipment';
      if (!reservationStartDate.value || !reservationEndDate.value) {
        requestError.value = 'Please select start and end dates for the reservation.';
        requesting.value = false;
        return;
      }
      payload.start_date = reservationStartDate.value;
      payload.end_date = reservationEndDate.value;
    }

    if (requestClassId.value) {
      payload.class_id = parseInt(requestClassId.value);
    }

    await api.post('/reservations', payload, { headers: { 'X-Idempotency-Key': key } });
    reservationSuccess.value = true;
  } catch (e) {
    requestError.value = e.response?.data?.error || e.response?.data?.message || 'Failed to create reservation.';
  } finally {
    requesting.value = false;
  }
};

onMounted(async () => {
  try {
    resource.value = await catalogStore.fetchResource(route.params.id);
    venue.value = resource.value?.venue || null;
  } catch (e) {
    error.value = e.response?.data?.message || 'Resource not found.';
  } finally {
    loading.value = false;
  }

  // Load student's enrolled classes from their permission scopes
  try {
    const { data } = await api.get('/my-classes');
    userClasses.value = data || [];
  } catch {}
});

const pollTimer = setInterval(async () => {
  try {
    resource.value = await catalogStore.fetchResource(route.params.id);
    venue.value = resource.value?.venue || null;
  } catch {}
}, 30000);
onUnmounted(() => clearInterval(pollTimer));
</script>
