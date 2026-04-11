<template>
  <div class="animate-fade-in">
    <!-- Header -->
    <div class="mb-6 flex items-start justify-between">
      <div>
        <h1 class="page-title">Resource Catalog</h1>
        <p class="page-subtitle">Browse and request available campus resources</p>
      </div>
      <router-link to="/recommendations" class="btn-secondary text-xs gap-1.5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
        </svg>
        Recommendations
      </router-link>
    </div>

    <!-- Filters -->
    <div class="card p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="md:col-span-2">
          <label class="label">Search</label>
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input v-model="filters.search" @input="debouncedFetch" type="text" class="input pl-9" placeholder="Search resources..." />
          </div>
        </div>
        <div>
          <label class="label">Type</label>
          <select v-model="filters.type" @change="fetchResources()" class="input">
            <option value="">All Types</option>
            <option value="equipment">Equipment</option>
            <option value="venue">Venue</option>
            <option value="entitlement_package">Entitlement Package</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="flex flex-col items-center gap-3">
        <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-slate-700 rounded-full"></div>
        <span class="text-sm text-slate-500">Loading resources...</span>
      </div>
    </div>

    <!-- Error -->
    <ConflictBanner :message="error" />

    <!-- Empty -->
    <div v-if="!loading && !error && resources.length === 0" class="card p-12 text-center">
      <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
      </div>
      <p class="text-slate-500 font-medium">No resources found</p>
      <p class="text-slate-400 text-sm mt-1">Try adjusting your search or filters</p>
    </div>

    <!-- Grid -->
    <div v-if="!loading && resources.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <router-link
        v-for="resource in resources"
        :key="resource.id"
        :to="`/catalog/${resource.id}`"
        class="card p-5 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 block group"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center" :class="typeColors[resource.type] || 'bg-slate-100'">
              <span class="text-sm">{{ typeIcons[resource.type] || '📦' }}</span>
            </div>
            <div>
              <span class="badge badge-slate text-xs">{{ resource.type }}</span>
            </div>
          </div>
          <div :class="resource.available_quantity > 0 ? 'badge-green' : 'badge-red'" class="badge">
            {{ resource.available_quantity > 0 ? `${resource.available_quantity} available` : 'Unavailable' }}
          </div>
        </div>

        <h3 class="font-semibold text-slate-900 group-hover:text-slate-700 transition-colors leading-snug mb-1">{{ resource.name }}</h3>
        <p v-if="resource.description" class="text-xs text-slate-500 line-clamp-2 mb-3">{{ resource.description }}</p>

        <div class="flex items-center gap-3 text-xs text-slate-400">
          <span v-if="resource.department?.name || resource.department">{{ resource.department?.name || resource.department || '' }}</span>
          <span v-if="resource.category" class="flex items-center gap-1">
            <span class="w-1 h-1 rounded-full bg-slate-300 inline-block"></span>
            {{ resource.category }}
          </span>
        </div>
      </router-link>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between mt-6">
      <p class="text-sm text-slate-500">
        Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }} resources
      </p>
      <div class="flex items-center gap-2">
        <button
          :disabled="pagination.current_page <= 1"
          @click="changePage(pagination.current_page - 1)"
          class="btn-secondary px-3 py-1.5 text-xs disabled:opacity-40"
        >Previous</button>
        <span class="text-sm text-slate-600 font-medium px-2">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button
          :disabled="pagination.current_page >= pagination.last_page"
          @click="changePage(pagination.current_page + 1)"
          class="btn-secondary px-3 py-1.5 text-xs disabled:opacity-40"
        >Next</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useCatalogStore } from '../../stores/catalog.js';
import { storeToRefs } from 'pinia';
import ConflictBanner from '../../components/ConflictBanner.vue';
import { usePolling } from '../../composables/usePolling.js';

const catalogStore = useCatalogStore();
const { resources, pagination, loading } = storeToRefs(catalogStore);
const error = ref('');

const filters = reactive({ search: '', type: '' });

let debounceTimer = null;
const debouncedFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchResources(), 350);
};

const typeColors = {
  equipment: 'bg-blue-50',
  book: 'bg-amber-50',
  software: 'bg-purple-50',
  space: 'bg-emerald-50',
  tool: 'bg-orange-50',
};
const typeIcons = {
  equipment: '⚙️',
  book: '📚',
  software: '💻',
  space: '🏢',
  tool: '🔧',
};

const fetchResources = async (page = 1) => {
  error.value = '';
  try {
    const params = { page };
    if (filters.search) params.search = filters.search;
    if (filters.type) params.resource_type = filters.type;
    await catalogStore.fetchResources(params);
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load resources.';
  }
};

const changePage = (page) => fetchResources(page);

onMounted(() => fetchResources());
usePolling(() => fetchResources(), 30000); // refresh every 30s
</script>
