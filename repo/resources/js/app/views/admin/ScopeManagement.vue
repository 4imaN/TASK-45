<template>
  <div class="animate-fade-in">
    <div class="mb-6">
      <h1 class="page-title">Scope Management</h1>
      <p class="page-subtitle">Assign and manage permission scopes for users</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Assign scope form -->
      <div class="card p-6">
        <h3 class="font-semibold text-slate-900 mb-4">Assign Permission Scope</h3>
        <ConflictBanner :message="assignError" />
        <div v-if="assignSuccess" class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2 rounded-lg mb-4 text-sm">
          Scope assigned successfully
        </div>
        <form @submit.prevent="assignScope" class="space-y-4">
          <div>
            <label class="label">User ID</label>
            <input v-model="assignForm.user_id" type="number" class="input" placeholder="User ID" required />
          </div>
          <div>
            <label class="label">Scope Type</label>
            <select v-model="assignForm.scope_type" class="input" required>
              <option value="">Select type</option>
              <option value="full">Full</option>
              <option value="course">Course</option>
              <option value="class">Class</option>
              <option value="assignment">Assignment</option>
              <option value="department">Department</option>
            </select>
          </div>
          <div v-if="assignForm.scope_type === 'course'">
            <label class="label">Course ID</label>
            <input v-model="assignForm.course_id" type="number" class="input" placeholder="Course ID" />
          </div>
          <div v-if="assignForm.scope_type === 'class'">
            <label class="label">Class ID</label>
            <input v-model="assignForm.class_id" type="number" class="input" placeholder="Class ID" />
          </div>
          <div v-if="assignForm.scope_type === 'assignment'">
            <label class="label">Assignment ID</label>
            <input v-model="assignForm.assignment_id" type="number" class="input" placeholder="Assignment ID" />
          </div>
          <div v-if="assignForm.scope_type === 'department'">
            <label class="label">Department ID</label>
            <input v-model="assignForm.department_id" type="number" class="input" placeholder="Department ID" />
          </div>
          <button type="submit" :disabled="assignLoading" class="btn-primary w-full justify-center">
            <span v-if="assignLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            <span v-else>Assign Scope</span>
          </button>
        </form>
      </div>

      <!-- Right column -->
      <div class="space-y-4">
        <!-- User scope lookup -->
        <div class="card p-5">
          <h3 class="font-semibold text-slate-900 mb-3">Lookup User Scopes</h3>
          <div class="flex gap-2">
            <input v-model="lookupUser" type="text" class="input" placeholder="User ID or username" />
            <button @click="lookupScopes()" :disabled="lookupLoading" class="btn-secondary flex-shrink-0">
              <span v-if="lookupLoading" class="animate-spin h-4 w-4 border border-slate-400 border-t-slate-700 rounded-full"></span>
              <span v-else>Lookup</span>
            </button>
          </div>
          <div v-if="userScopes.length > 0" class="mt-3 space-y-2">
            <div v-for="us in userScopes" :key="us.id" class="flex items-center justify-between p-2 bg-slate-50 rounded-lg text-sm">
              <div>
                <span class="font-medium text-slate-800">{{ us.scope_type }}</span>
                <div class="text-xs text-slate-400 mt-0.5">
                  <span v-if="us.course">Course: {{ us.course.name }}</span>
                  <span v-if="us.class_model">Class: {{ us.class_model.name }}</span>
                  <span v-if="us.assignment">Assignment: {{ us.assignment.name }}</span>
                  <span v-if="us.department">Dept: {{ us.department.name }}</span>
                </div>
              </div>
              <button @click="revokeScope(us.id)" :disabled="revokeLoading === us.id" class="btn-danger text-xs px-2 py-1">
                <span v-if="revokeLoading === us.id" class="animate-spin h-3 w-3 border border-white border-t-transparent rounded-full"></span>
                <span v-else>Revoke</span>
              </button>
            </div>
          </div>
          <p v-if="userScopes.length === 0 && lookupDone" class="text-sm text-slate-400 mt-3">No scopes found for this user</p>
        </div>

        <!-- All scopes -->
        <div class="card">
          <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-900">All Assigned Scopes</h3>
          </div>
          <div v-if="scopesLoading" class="flex justify-center py-6">
            <div class="animate-spin h-5 w-5 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
          </div>
          <div class="divide-y divide-slate-100">
            <div v-for="scope in allScopes" :key="scope.id" class="px-5 py-3 flex items-center justify-between">
              <div>
                <div class="text-sm font-medium text-slate-900">User #{{ scope.user_id }} — {{ scope.scope_type }}</div>
                <div class="text-xs text-slate-500">
                  <span v-if="scope.user">{{ scope.user.display_name || scope.user.username }}</span>
                </div>
              </div>
              <span class="badge badge-blue">{{ scope.scope_type }}</span>
            </div>
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

const allScopes = ref([]);
const scopesLoading = ref(true);
const assignForm = ref({ user_id: '', scope_type: '', course_id: '', class_id: '', assignment_id: '', department_id: '' });
const assignLoading = ref(false);
const assignError = ref('');
const assignSuccess = ref(false);
const lookupUser = ref('');
const lookupLoading = ref(false);
const userScopes = ref([]);
const lookupDone = ref(false);
const revokeLoading = ref(null);

const fetchScopes = async () => {
  scopesLoading.value = true;
  try {
    const { data } = await api.get('/admin/scopes');
    allScopes.value = data.data || data;
  } catch {} finally { scopesLoading.value = false; }
};

const assignScope = async () => {
  assignError.value = '';
  assignSuccess.value = false;
  assignLoading.value = true;
  try {
    const key = 'scope-' + Date.now();
    const payload = { user_id: assignForm.value.user_id, scope_type: assignForm.value.scope_type };
    if (assignForm.value.course_id) payload.course_id = assignForm.value.course_id;
    if (assignForm.value.class_id) payload.class_id = assignForm.value.class_id;
    if (assignForm.value.assignment_id) payload.assignment_id = assignForm.value.assignment_id;
    if (assignForm.value.department_id) payload.department_id = assignForm.value.department_id;
    await api.post('/admin/scopes', payload, { headers: { 'X-Idempotency-Key': key } });
    assignSuccess.value = true;
    assignForm.value = { user_id: '', scope_type: '', course_id: '', class_id: '', assignment_id: '', department_id: '' };
    await fetchScopes();
  } catch (e) {
    assignError.value = e.response?.data?.error || e.response?.data?.message || 'Failed to assign scope.';
  } finally {
    assignLoading.value = false;
  }
};

const lookupScopes = async () => {
  lookupLoading.value = true;
  lookupDone.value = false;
  try {
    const { data } = await api.get('/admin/scopes/user', { params: { user: lookupUser.value } });
    userScopes.value = data.data || [];
    lookupDone.value = true;
  } catch {} finally { lookupLoading.value = false; }
};

const revokeScope = async (id) => {
  revokeLoading.value = id;
  try {
    await api.delete(`/admin/scopes/${id}`, { headers: { 'X-Idempotency-Key': 'revoke-scope-' + id + '-' + Date.now() } });
    userScopes.value = userScopes.value.filter(s => s.id !== id);
    await fetchScopes();
  } catch {} finally { revokeLoading.value = null; }
};

onMounted(fetchScopes);
</script>
