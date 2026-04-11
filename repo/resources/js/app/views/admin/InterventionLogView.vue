<template>
  <div class="animate-fade-in">
    <h1 class="page-title mb-6">Intervention Logs</h1>
    <div v-if="loading" class="flex justify-center py-12"><div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div></div>
    <div v-if="!loading && !logs.length" class="card p-12 text-center text-slate-400">No intervention logs</div>
    <div v-if="!loading && logs.length" class="card overflow-hidden">
      <table class="table-base">
        <thead><tr><th>ID</th><th>User</th><th>Action</th><th>Reason</th><th>Date</th><th>Resolved</th></tr></thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id">
            <td class="font-mono text-xs">{{ log.id }}</td>
            <td>User #{{ log.user_id }}</td>
            <td><span class="badge badge-amber">{{ log.action_type }}</span></td>
            <td class="text-sm text-slate-600 max-w-xs truncate">{{ log.reason }}</td>
            <td class="text-xs text-slate-500">{{ new Date(log.created_at).toLocaleString() }}</td>
            <td>
              <span v-if="log.resolved_at" class="badge badge-green">Resolved</span>
              <span v-else class="badge badge-red">Open</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api.js';
const logs = ref([]);
const loading = ref(true);
onMounted(async () => {
  try {
    const { data } = await api.get('/admin/interventions');
    logs.value = data.data || data;
  } catch {} finally { loading.value = false; }
});
</script>
