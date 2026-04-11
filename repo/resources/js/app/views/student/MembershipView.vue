<template>
  <div class="animate-fade-in">
    <div class="mb-6">
      <h1 class="page-title">Membership</h1>
      <p class="page-subtitle">Your membership tier, points, and entitlements</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
    </div>

    <ConflictBanner :message="error" />

    <div v-if="!loading && membershipStore.membership" class="space-y-6">
      <!-- Membership card -->
      <div class="rounded-2xl overflow-hidden shadow-lg" :style="tierGradient">
        <div class="px-8 py-6 text-white">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-white/70 text-sm font-medium uppercase tracking-widest mb-1">Membership Tier</p>
              <h2 class="text-3xl font-bold tracking-tight">{{ membershipStore.membership.tier_name || 'Standard' }}</h2>
              <p class="text-white/70 text-sm mt-1">Member since {{ formatDate(membershipStore.membership.starts_at) }}</p>
            </div>
            <div class="text-right">
              <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center">
                <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                </svg>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-6 mt-6 pt-6 border-t border-white/20">
            <div>
              <p class="text-white/60 text-xs uppercase tracking-wide mb-1">Points Balance</p>
              <p class="text-2xl font-bold">{{ membershipStore.pointsBalance.toLocaleString() }}</p>
            </div>
            <div>
              <p class="text-white/60 text-xs uppercase tracking-wide mb-1">Stored Value</p>
              <p class="text-2xl font-bold">{{ formatCurrency(membershipStore.storedValueCents) }}</p>
            </div>
          </div>

          <div v-if="membershipStore.membership.expires_at" class="mt-4 text-white/60 text-xs">
            Expires {{ formatDate(membershipStore.membership.expires_at) }}
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Redeem Points -->
        <div class="card p-5">
          <h3 class="font-semibold text-slate-900 mb-1">Redeem Points</h3>
          <p class="text-xs text-slate-500 mb-4">Convert your points to benefits</p>
          <ConflictBanner :message="pointsError" />
          <div v-if="pointsSuccess" class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2 rounded-lg mb-3 text-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Points redeemed successfully
          </div>
          <div class="space-y-3">
            <div>
              <label class="label">Points to Redeem</label>
              <input v-model.number="pointsForm.points" type="number" min="1" :max="membershipStore.pointsBalance" class="input" placeholder="e.g. 100" />
            </div>
            <div>
              <label class="label">Description</label>
              <input v-model="pointsForm.description" type="text" class="input" placeholder="What for?" />
            </div>
            <button @click="handleRedeemPoints" :disabled="!pointsForm.points || pointsLoading" class="btn-amber w-full justify-center">
              <span v-if="pointsLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
              <span v-else>Redeem {{ pointsForm.points || '' }} Points</span>
            </button>
          </div>
        </div>

        <!-- Redeem Stored Value -->
        <div class="card p-5">
          <h3 class="font-semibold text-slate-900 mb-1">Use Stored Value</h3>
          <p class="text-xs text-slate-500 mb-4">Spend from your stored balance</p>
          <ConflictBanner :message="valueError" />
          <div v-if="valueSuccess" class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2 rounded-lg mb-3 text-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Stored value redeemed
          </div>
          <div class="space-y-3">
            <div>
              <label class="label">Amount (dollars)</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                <input v-model.number="valueForm.amountDollars" type="number" min="0.01" step="0.01" class="input pl-7" placeholder="0.00" />
              </div>
              <p class="text-xs text-slate-400 mt-1">Available: {{ formatCurrency(membershipStore.storedValueCents) }}</p>
            </div>
            <div>
              <label class="label">Description</label>
              <input v-model="valueForm.description" type="text" class="input" placeholder="What for?" />
            </div>
            <button @click="handleRedeemValue" :disabled="!valueForm.amountDollars || valueLoading" class="btn-primary w-full justify-center">
              <span v-if="valueLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
              <span v-else>Spend {{ valueForm.amountDollars ? `$${valueForm.amountDollars}` : '' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Entitlements -->
      <div class="card">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="font-semibold text-slate-900">Entitlements</h3>
          <span class="badge badge-slate">{{ membershipStore.entitlements.length }} total</span>
        </div>
        <div v-if="membershipStore.entitlements.length === 0" class="px-6 py-8 text-center text-slate-400 text-sm">
          No entitlements on this membership
        </div>
        <div class="divide-y divide-slate-100">
          <div v-for="ent in membershipStore.entitlements" :key="ent.id" class="px-6 py-4">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-0.5">
                  <h4 class="text-sm font-semibold text-slate-900">{{ ent.package_name || ent.package?.name || 'Entitlement' }}</h4>
                  <span :class="ent.remaining > 0 ? 'badge-green' : 'badge-slate'" class="badge">{{ ent.remaining > 0 ? 'Active' : 'Exhausted' }}</span>
                </div>
                <p class="text-xs text-slate-500">{{ ent.unit ? ent.remaining + ' ' + ent.unit + ' remaining' : '' }}</p>
              </div>
              <div class="text-right flex-shrink-0">
                <div v-if="ent.usage !== undefined && ent.limit" class="text-right">
                  <span class="text-sm font-semibold text-slate-700">{{ ent.usage }} / {{ ent.limit }}</span>
                  <p class="text-xs text-slate-400">used</p>
                  <div class="w-24 bg-slate-100 rounded-full h-1.5 mt-1">
                    <div class="h-1.5 rounded-full bg-blue-500" :style="`width: ${Math.min((ent.usage / ent.limit) * 100, 100)}%`"></div>
                  </div>
                </div>
                <div v-else-if="ent.remaining !== undefined" class="text-sm font-semibold text-emerald-600">
                  {{ ent.remaining }} remaining
                </div>
                <div v-if="ent.remaining > 0" class="mt-2">
                  <button @click="consumeEntitlement(ent)" :disabled="consumeLoading === ent.id" class="btn-secondary text-xs px-3 py-1">
                    <span v-if="consumeLoading === ent.id" class="animate-spin h-3 w-3 border border-slate-400 border-t-slate-700 rounded-full"></span>
                    <span v-else>Use</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Available packages -->
      <div v-if="packages.length" class="card p-6">
        <h3 class="font-semibold text-slate-900 mb-4">Available Packages</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="pkg in packages" :key="pkg.id" class="border border-slate-200 rounded-xl p-4">
            <h4 class="font-medium text-slate-900">{{ pkg.name }}</h4>
            <p class="text-sm text-slate-500 mt-1">{{ pkg.description }}</p>
            <div class="flex items-center gap-3 mt-3 text-xs text-slate-600">
              <span>{{ pkg.quantity }} {{ pkg.unit }}</span>
              <span>Valid {{ pkg.validity_days }} days</span>
              <span v-if="pkg.price_in_cents" class="font-semibold text-slate-900">${{ (pkg.price_in_cents / 100).toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useMembershipStore } from '../../stores/membership.js';
import api from '../../services/api.js';
import ConflictBanner from '../../components/ConflictBanner.vue';

const membershipStore = useMembershipStore();
const loading = computed(() => membershipStore.loading);
const error = ref('');
const packages = ref([]);

const pointsForm = ref({ points: null, description: '' });
const valueForm = ref({ amountDollars: null, description: '' });
const pointsLoading = ref(false);
const valueLoading = ref(false);
const pointsError = ref('');
const valueError = ref('');
const pointsSuccess = ref(false);
const valueSuccess = ref(false);
const consumeLoading = ref(null);

const tierGradient = computed(() => {
  const tier = membershipStore.membership?.tier_name?.toLowerCase() || 'standard';
  const gradients = {
    bronze: 'background: linear-gradient(135deg, #92400e, #b45309)',
    silver: 'background: linear-gradient(135deg, #374151, #6b7280)',
    gold: 'background: linear-gradient(135deg, #92400e, #d97706)',
    platinum: 'background: linear-gradient(135deg, #1e3a5f, #3b82f6)',
    standard: 'background: linear-gradient(135deg, #1e293b, #334155)',
  };
  return gradients[tier] || gradients.standard;
});

const formatCurrency = (cents) => `$${((cents || 0) / 100).toFixed(2)}`;
const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '—';

const handleRedeemPoints = async () => {
  pointsError.value = '';
  pointsSuccess.value = false;
  pointsLoading.value = true;
  try {
    await membershipStore.redeemPoints(pointsForm.value.points, pointsForm.value.description);
    pointsSuccess.value = true;
    pointsForm.value = { points: null, description: '' };
  } catch (e) {
    pointsError.value = e.response?.data?.message || 'Failed to redeem points.';
  } finally {
    pointsLoading.value = false;
  }
};

const handleRedeemValue = async () => {
  valueError.value = '';
  valueSuccess.value = false;
  valueLoading.value = true;
  try {
    await membershipStore.redeemStoredValue(Math.round(valueForm.value.amountDollars * 100), valueForm.value.description);
    valueSuccess.value = true;
    valueForm.value = { amountDollars: null, description: '' };
  } catch (e) {
    valueError.value = e.response?.data?.message || 'Failed to redeem stored value.';
  } finally {
    valueLoading.value = false;
  }
};

const consumeEntitlement = async (ent) => {
  consumeLoading.value = ent.id;
  try {
    const key = 'consume-' + ent.id + '-' + Date.now();
    await api.post(`/memberships/entitlements/${ent.id}/consume`, { quantity: 1 }, { headers: { 'X-Idempotency-Key': key } });
    await membershipStore.fetchMembership();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to use entitlement.';
  } finally {
    consumeLoading.value = null;
  }
};

onMounted(async () => {
  try {
    await membershipStore.fetchMembership();
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load membership.';
  }
  try {
    const pkgRes = await api.get('/memberships/packages');
    packages.value = pkgRes.data;
  } catch {}
});
</script>
