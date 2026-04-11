<template>
  <div class="animate-fade-in">
    <h1 class="page-title mb-6">Vendor / Manufacturer Aliases</h1>
    <ConflictBanner :message="error" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Vendor Aliases -->
      <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="font-semibold text-slate-900">Vendor Aliases</h3>
          <button @click="showAddVendor = true" class="btn-secondary text-xs">+ Add</button>
        </div>
        <div v-if="vendorLoading" class="p-6 text-center"><div class="animate-spin h-5 w-5 border-2 border-slate-200 border-t-slate-700 rounded-full mx-auto"></div></div>
        <div v-for="a in vendorAliases" :key="a.id" class="px-5 py-3 border-b border-slate-50 flex items-center justify-between text-sm">
          <div>
            <span class="text-slate-400">{{ a.alias }}</span>
            <span class="mx-2">→</span>
            <span class="font-medium text-slate-900">{{ a.canonical_name }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span :class="a.status === 'approved' ? 'badge-green' : a.status === 'rejected' ? 'badge-red' : 'badge-amber'" class="badge">{{ a.status }}</span>
            <button v-if="a.status === 'pending'" @click="updateAlias('vendor', a.id, 'approved')" class="btn-success text-xs px-2 py-0.5">Approve</button>
            <button v-if="a.status === 'pending'" @click="updateAlias('vendor', a.id, 'rejected')" class="btn-danger text-xs px-2 py-0.5">Reject</button>
          </div>
        </div>
      </div>

      <!-- Manufacturer Aliases -->
      <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="font-semibold text-slate-900">Manufacturer Aliases</h3>
          <button @click="showAddMfg = true" class="btn-secondary text-xs">+ Add</button>
        </div>
        <div v-if="mfgLoading" class="p-6 text-center"><div class="animate-spin h-5 w-5 border-2 border-slate-200 border-t-slate-700 rounded-full mx-auto"></div></div>
        <div v-for="a in mfgAliases" :key="a.id" class="px-5 py-3 border-b border-slate-50 flex items-center justify-between text-sm">
          <div>
            <span class="text-slate-400">{{ a.alias }}</span>
            <span class="mx-2">→</span>
            <span class="font-medium text-slate-900">{{ a.canonical_name }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span :class="a.status === 'approved' ? 'badge-green' : a.status === 'rejected' ? 'badge-red' : 'badge-amber'" class="badge">{{ a.status }}</span>
            <button v-if="a.status === 'pending'" @click="updateAlias('manufacturer', a.id, 'approved')" class="btn-success text-xs px-2 py-0.5">Approve</button>
            <button v-if="a.status === 'pending'" @click="updateAlias('manufacturer', a.id, 'rejected')" class="btn-danger text-xs px-2 py-0.5">Reject</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add vendor alias modal -->
    <div v-if="showAddVendor" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-semibold mb-4">Add Vendor Alias</h3>
        <div class="space-y-3">
          <div><label class="label">Alias</label><input v-model="newAlias.alias" class="input" placeholder="e.g. Dell Inc" /></div>
          <div><label class="label">Canonical Name</label><input v-model="newAlias.canonical_name" class="input" placeholder="e.g. Dell Technologies" /></div>
        </div>
        <div class="flex gap-3 mt-4">
          <button @click="showAddVendor = false" class="btn-secondary flex-1 justify-center">Cancel</button>
          <button @click="addAlias('vendor')" class="btn-primary flex-1 justify-center">Add</button>
        </div>
      </div>
    </div>

    <!-- Add manufacturer alias modal -->
    <div v-if="showAddMfg" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-semibold mb-4">Add Manufacturer Alias</h3>
        <div class="space-y-3">
          <div><label class="label">Alias</label><input v-model="newAlias.alias" class="input" /></div>
          <div><label class="label">Canonical Name</label><input v-model="newAlias.canonical_name" class="input" /></div>
        </div>
        <div class="flex gap-3 mt-4">
          <button @click="showAddMfg = false" class="btn-secondary flex-1 justify-center">Cancel</button>
          <button @click="addAlias('manufacturer')" class="btn-primary flex-1 justify-center">Add</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api.js';
import ConflictBanner from '../../components/ConflictBanner.vue';

const error = ref('');
const vendorAliases = ref([]);
const mfgAliases = ref([]);
const vendorLoading = ref(true);
const mfgLoading = ref(true);
const showAddVendor = ref(false);
const showAddMfg = ref(false);
const newAlias = ref({ alias: '', canonical_name: '' });

const fetchVendors = async () => {
  vendorLoading.value = true;
  try { const { data } = await api.get('/data-quality/vendor-aliases'); vendorAliases.value = data.data || data; }
  catch {} finally { vendorLoading.value = false; }
};
const fetchMfgs = async () => {
  mfgLoading.value = true;
  try { const { data } = await api.get('/data-quality/manufacturer-aliases'); mfgAliases.value = data.data || data; }
  catch {} finally { mfgLoading.value = false; }
};

const addAlias = async (type) => {
  try {
    const key = `alias-${type}-${Date.now()}`;
    await api.post(`/data-quality/${type}-aliases`, newAlias.value, { headers: { 'X-Idempotency-Key': key } });
    newAlias.value = { alias: '', canonical_name: '' };
    showAddVendor.value = false; showAddMfg.value = false;
    type === 'vendor' ? await fetchVendors() : await fetchMfgs();
  } catch (e) { error.value = e.response?.data?.error || 'Failed.'; }
};

const updateAlias = async (type, id, status) => {
  try {
    const key = `alias-update-${id}-${Date.now()}`;
    await api.put(`/data-quality/${type}-aliases/${id}`, { status }, { headers: { 'X-Idempotency-Key': key } });
    type === 'vendor' ? await fetchVendors() : await fetchMfgs();
  } catch (e) { error.value = e.response?.data?.error || 'Failed.'; }
};

onMounted(() => { fetchVendors(); fetchMfgs(); });
</script>
