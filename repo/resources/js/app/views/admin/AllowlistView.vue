<template>
  <div class="animate-fade-in">
    <h1 class="page-title mb-6">Allowlist Management</h1>
    <ConflictBanner :message="error" />
    <div class="card p-6 mb-4">
      <h3 class="font-semibold text-slate-900 mb-4">Add to Allowlist</h3>
      <form @submit.prevent="addEntry" class="grid grid-cols-2 gap-4">
        <div><label class="label">User ID</label><input v-model="form.user_id" type="number" class="input" required /></div>
        <div><label class="label">Scope Type</label><select v-model="form.scope_type" class="input" required><option value="department">Department</option><option value="global">Global</option></select></div>
        <div><label class="label">Scope ID</label><input v-model="form.scope_id" type="number" class="input" required /></div>
        <div><label class="label">Reason</label><input v-model="form.reason" type="text" class="input" required /></div>
        <button type="submit" :disabled="saving" class="btn-primary col-span-2 justify-center">Add</button>
      </form>
    </div>
    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100"><h3 class="font-semibold text-slate-900">Current Entries</h3></div>
      <div v-if="loading" class="p-6 text-center"><div class="animate-spin h-5 w-5 border-2 border-slate-200 border-t-slate-700 rounded-full mx-auto"></div></div>
      <div v-if="!loading && !entries.length" class="p-6 text-center text-slate-400">No allowlist entries</div>
      <div v-for="e in entries" :key="e.id" class="px-5 py-3 border-b border-slate-50 flex justify-between items-center text-sm">
        <div>
          <span class="font-medium">User #{{ e.user_id }}</span> — {{ e.scope_type }}:{{ e.scope_id }}
          <span class="text-slate-500 ml-2">{{ e.reason }}</span>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-xs text-slate-400">{{ new Date(e.created_at).toLocaleDateString() }}</span>
          <button @click="removeEntry(e.id)" :disabled="removing === e.id" class="btn-danger text-xs px-2 py-1">Remove</button>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api.js';
import ConflictBanner from '../../components/ConflictBanner.vue';
const entries = ref([]);
const error = ref('');
const saving = ref(false);
const loading = ref(true);
const removing = ref(null);
const form = ref({ user_id: '', scope_type: 'department', scope_id: '', reason: '' });

const fetchEntries = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/admin/allowlists');
    entries.value = data.data || data;
  } catch {} finally { loading.value = false; }
};

onMounted(fetchEntries);

const addEntry = async () => {
  saving.value = true; error.value = '';
  try {
    const key = 'allow-' + Date.now();
    await api.post('/admin/allowlists', form.value, { headers: { 'X-Idempotency-Key': key } });
    form.value = { user_id: '', scope_type: 'department', scope_id: '', reason: '' };
    await fetchEntries();
  } catch (e) { error.value = e.response?.data?.error || e.response?.data?.message || 'Failed.'; }
  finally { saving.value = false; }
};

const removeEntry = async (id) => {
  removing.value = id;
  try {
    await api.delete(`/admin/allowlists/${id}`, { headers: { 'X-Idempotency-Key': 'del-allow-' + id + '-' + Date.now() } });
    await fetchEntries();
  } catch (e) { error.value = e.response?.data?.error || e.response?.data?.message || 'Failed to remove.'; }
  finally { removing.value = null; }
};
</script>
