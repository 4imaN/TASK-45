<template>
  <div class="animate-fade-in">
    <div class="mb-6 flex items-start gap-4">
      <router-link to="/data-quality" class="btn-secondary text-xs mt-1">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Data Quality
      </router-link>
      <div>
        <h1 class="page-title">Bulk Import</h1>
        <p class="page-subtitle">Import resources from CSV or JSON files</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Upload form -->
      <div class="space-y-4">
        <div class="card p-6">
          <h3 class="font-semibold text-slate-900 mb-4">Upload File</h3>
          <ConflictBanner :message="uploadError" />

          <!-- Drop zone -->
          <div
            class="border-2 border-dashed border-slate-200 rounded-xl p-8 text-center transition-all cursor-pointer hover:border-slate-400 hover:bg-slate-50"
            :class="{ 'border-blue-400 bg-blue-50': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave="isDragging = false"
            @drop.prevent="handleDrop"
            @click="$refs.fileInput.click()"
          >
            <input ref="fileInput" type="file" accept=".csv,.json" class="hidden" @change="handleFileSelect" />
            <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            <p class="text-slate-500 font-medium text-sm">Drop file here or click to browse</p>
            <p class="text-slate-400 text-xs mt-1">Supports .csv and .json</p>
          </div>

          <div v-if="selectedFile" class="mt-3 flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <div class="flex-1">
              <p class="text-sm font-medium text-blue-900">{{ selectedFile.name }}</p>
              <p class="text-xs text-blue-600">{{ formatFileSize(selectedFile.size) }}</p>
            </div>
            <button @click.stop="selectedFile = null; $refs.fileInput.value = ''" class="text-blue-400 hover:text-blue-700">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="mt-4 space-y-3">
            <div>
              <label class="label">Import Type</label>
              <select v-model="importType" class="input">
                <option value="resources">Resources / Catalog</option>
              </select>
            </div>
          </div>

          <div class="mt-4 flex gap-3">
            <button
              @click="validateFile()"
              :disabled="!selectedFile || validating"
              class="btn-secondary flex-1 justify-center"
            >
              <span v-if="validating" class="animate-spin h-4 w-4 border border-slate-400 border-t-slate-700 rounded-full"></span>
              <span v-else>Validate</span>
            </button>
            <button
              @click="processImport()"
              :disabled="!selectedFile || importing || (validationResult !== null && validationResult.summary?.invalid !== 0)"
              class="btn-primary flex-1 justify-center"
            >
              <span v-if="importing" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
              <span v-else>Import</span>
            </button>
          </div>
        </div>

        <div class="card p-5">
          <h3 class="font-semibold text-slate-900 mb-3 text-sm">Import Format</h3>
          <div class="text-xs text-slate-600 space-y-2">
            <p><strong>CSV:</strong> First row must be column headers. Required column: <code class="bg-slate-100 px-1 rounded">name</code></p>
            <p><strong>JSON:</strong> Array of objects with at least a <code class="bg-slate-100 px-1 rounded">name</code> field</p>
            <p><strong>Optional columns:</strong> category, vendor, manufacturer, model_number, tags, notes</p>
          </div>
        </div>
      </div>

      <!-- Validation / results -->
      <div class="space-y-4">
        <!-- Validation result -->
        <div v-if="validationResult" class="card p-5">
          <div class="flex items-center gap-2 mb-4">
            <div :class="validationResult.summary?.invalid === 0 ? 'w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center' : 'w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center'">
              <svg v-if="validationResult.summary?.invalid === 0" class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <svg v-else class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
              </svg>
            </div>
            <h3 class="font-semibold text-slate-900">{{ validationResult.summary?.invalid === 0 ? 'Validation Passed' : 'Validation Failed' }}</h3>
          </div>
          <div class="grid grid-cols-3 gap-3 mb-4 text-center">
            <div class="bg-slate-50 rounded-lg p-3">
              <div class="text-xl font-bold text-slate-900">{{ validationResult.summary?.total }}</div>
              <div class="text-xs text-slate-500">Total Rows</div>
            </div>
            <div class="bg-emerald-50 rounded-lg p-3">
              <div class="text-xl font-bold text-emerald-700">{{ validationResult.summary?.valid }}</div>
              <div class="text-xs text-slate-500">Valid</div>
            </div>
            <div class="bg-red-50 rounded-lg p-3">
              <div class="text-xl font-bold text-red-700">{{ validationResult.summary?.invalid }}</div>
              <div class="text-xs text-slate-500">Errors</div>
            </div>
          </div>
          <div v-if="validationResult.issues && validationResult.issues.length" class="max-h-48 overflow-y-auto space-y-2">
            <div v-for="(err, i) in validationResult.issues" :key="i" class="flex items-start gap-2 text-xs">
              <span class="badge badge-red flex-shrink-0">Row {{ err.row }}</span>
              <span class="text-slate-700">{{ Array.isArray(err.errors) ? err.errors.join('; ') : (err.errors || err.status) }}</span>
            </div>
          </div>
        </div>

        <!-- Import result -->
        <div v-if="importResult" class="card p-5">
          <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
              <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="font-semibold text-slate-900">Validation Complete</h3>
          </div>
          <div class="grid grid-cols-3 gap-3 text-center">
            <div class="bg-slate-50 rounded-lg p-3">
              <div class="text-xl font-bold text-slate-900">{{ importResult.summary?.total_rows ?? importResult.summary?.total ?? '—' }}</div>
              <div class="text-xs text-slate-500">Total Rows</div>
            </div>
            <div class="bg-emerald-50 rounded-lg p-3">
              <div class="text-xl font-bold text-emerald-700">{{ importResult.summary?.valid ?? '—' }}</div>
              <div class="text-xs text-slate-500">Valid</div>
            </div>
            <div class="bg-red-50 rounded-lg p-3">
              <div class="text-xl font-bold text-red-700">{{ importResult.summary?.invalid ?? '—' }}</div>
              <div class="text-xs text-slate-500">Issues Found</div>
            </div>
          </div>
          <p class="text-xs text-slate-500 mt-3">Valid rows have been queued for review. Items with issues appear in the remediation queue.</p>
        </div>

        <!-- Import history -->
        <div class="card">
          <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-900">Recent Imports</h3>
          </div>
          <div v-if="importHistory.length === 0" class="px-5 py-6 text-center text-slate-400 text-sm">No recent imports</div>
          <div class="divide-y divide-slate-100">
            <div v-for="h in importHistory" :key="h.id" class="px-5 py-3 flex items-center justify-between">
              <div>
                <div class="text-sm font-medium text-slate-900">{{ h.filename }}</div>
                <div class="text-xs text-slate-400">{{ (h.total_rows || 0) + ' rows' }} • {{ formatDateTime(h.created_at) }}</div>
              </div>
              <div class="text-right flex items-center gap-2">
                <button @click="downloadReport(h.id)" class="btn-secondary text-xs px-2 py-1" title="Download validation report">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                  </svg>
                </button>
                <div>
                  <span :class="h.status === 'completed' ? 'badge-green' : h.status === 'failed' ? 'badge-red' : 'badge-amber'" class="badge">{{ h.status }}</span>
                  <div class="text-xs text-slate-400 mt-0.5">{{ h.valid_rows || h.total_rows || 0 }} imported</div>
                </div>
              </div>
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

const fileInput = ref(null);
const selectedFile = ref(null);
const isDragging = ref(false);
const importType = ref('resources');
const validating = ref(false);
const importing = ref(false);
const validationResult = ref(null);
const importResult = ref(null);
const importHistory = ref([]);
const uploadError = ref('');

const formatFileSize = (bytes) => {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
const formatDateTime = (d) => d ? new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

const handleFileSelect = (e) => {
  const file = e.target.files[0];
  if (file) selectedFile.value = file;
  validationResult.value = null;
  importResult.value = null;
};

const handleDrop = (e) => {
  isDragging.value = false;
  const file = e.dataTransfer.files[0];
  if (file && (file.name.endsWith('.csv') || file.name.endsWith('.json'))) {
    selectedFile.value = file;
    validationResult.value = null;
    importResult.value = null;
  } else {
    uploadError.value = 'Only CSV and JSON files are supported.';
  }
};

const validateFile = async () => {
  if (!selectedFile.value) return;
  uploadError.value = '';
  validating.value = true;
  try {
    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('type', importType.value);
    formData.append('validate_only', '1');
    const valKey = 'import-validate-' + Date.now();
    const { data } = await api.post('/data-quality/import', formData, { headers: { 'Content-Type': 'multipart/form-data', 'X-Idempotency-Key': valKey } });
    validationResult.value = data;
  } catch (e) {
    uploadError.value = e.response?.data?.message || 'Validation failed.';
  } finally {
    validating.value = false;
  }
};

const processImport = async () => {
  if (!selectedFile.value) return;
  uploadError.value = '';
  importing.value = true;
  importResult.value = null;
  try {
    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('type', importType.value);
    const impKey = 'import-' + Date.now();
    const { data } = await api.post('/data-quality/import', formData, { headers: { 'Content-Type': 'multipart/form-data', 'X-Idempotency-Key': impKey } });
    importResult.value = data;
    await loadHistory();
  } catch (e) {
    uploadError.value = e.response?.data?.message || 'Import failed.';
  } finally {
    importing.value = false;
  }
};

const downloadReport = async (batchId) => {
  try {
    const response = await api.get(`/data-quality/batches/${batchId}/download`, { responseType: 'blob' });
    const url = URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `validation_report_${batchId}.json`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    uploadError.value = 'Failed to download report.';
  }
};

const loadHistory = async () => {
  try {
    const { data } = await api.get('/data-quality/batches');
    importHistory.value = (data.data || data).slice(0, 8);
  } catch {}
};

onMounted(loadHistory);
</script>
