<template>
  <div class="animate-fade-in">
    <div class="mb-6 flex items-start gap-4">
      <router-link to="/catalog" class="btn-secondary text-xs mt-1">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Catalog
      </router-link>
      <div>
        <h1 class="page-title">Recommended for You</h1>
        <p class="page-subtitle">Personalized resource suggestions based on your usage patterns</p>
      </div>
    </div>

    <!-- Class selector -->
    <div v-if="userClasses.length > 1" class="mb-5 flex items-center gap-3">
      <label class="text-sm font-medium text-slate-700 flex-shrink-0">Class context</label>
      <select v-model="selectedClassId" @change="loadRecommendations" class="input max-w-xs">
        <option v-for="cls in userClasses" :key="cls.id" :value="cls.id">
          {{ cls.course?.name || '' }} — {{ cls.name }}
        </option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="flex flex-col items-center gap-3">
        <div class="animate-spin h-8 w-8 border-2 border-slate-200 border-t-amber-500 rounded-full"></div>
        <span class="text-sm text-slate-500">Generating recommendations...</span>
      </div>
    </div>

    <ConflictBanner :message="error" />

    <!-- Empty -->
    <div v-if="!loading && !error && recommendations.length === 0" class="card p-12 text-center">
      <div class="w-16 h-16 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
        </svg>
      </div>
      <p class="text-slate-500 font-medium">No recommendations yet</p>
      <p class="text-slate-400 text-sm mt-1">Borrow more resources to get personalized suggestions</p>
    </div>

    <!-- Recommendations -->
    <div v-if="!loading && recommendations.length > 0" class="space-y-4">
      <div v-for="(rec, i) in recommendations" :key="rec.resource?.id || i" class="card p-5 flex items-start gap-5">
        <!-- Rank badge -->
        <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center font-bold text-lg"
          :class="i === 0 ? 'bg-amber-100 text-amber-600' : i === 1 ? 'bg-slate-100 text-slate-500' : 'bg-stone-100 text-stone-500'">
          {{ i + 1 }}
        </div>

        <div class="flex-1">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="flex items-center gap-2 mb-0.5">
                <h3 class="font-semibold text-slate-900">{{ rec.resource?.name || rec.name }}</h3>
                <span v-if="rec.resource?.type" class="badge badge-slate">{{ rec.resource.type }}</span>
              </div>
              <p v-if="rec.resource?.description" class="text-xs text-slate-500 line-clamp-2 mb-3">{{ rec.resource.description }}</p>
            </div>
            <router-link v-if="rec.resource?.id" :to="`/catalog/${rec.resource.id}`" class="btn-primary text-xs flex-shrink-0">
              View
            </router-link>
          </div>

          <!-- Contributing factors -->
          <div v-if="rec.factors && rec.factors.length" class="mt-2">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Why recommended</p>
            <div class="flex flex-wrap gap-2">
              <div v-for="factor in rec.factors" :key="factor.type" class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5">
                <span class="text-sm">{{ factorIcon(factor.type) }}</span>
                <span class="text-xs text-slate-600">{{ factor.label || factor.type }}</span>
                <span v-if="factor.score" class="text-xs font-semibold text-amber-600">{{ Math.round(factor.score * 100) }}%</span>
              </div>
            </div>
          </div>

          <!-- Score bar -->
          <div v-if="rec.score" class="mt-3 flex items-center gap-2">
            <div class="flex-1 bg-slate-100 rounded-full h-1.5 max-w-32">
              <div class="h-1.5 rounded-full bg-amber-400" :style="`width: ${Math.min(rec.score * 100, 100)}%`"></div>
            </div>
            <span class="text-xs text-slate-400">{{ Math.round(rec.score * 100) }}% match</span>
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

const recommendations = ref([]);
const loading = ref(true);
const error = ref('');
const userClasses = ref([]);
const selectedClassId = ref(null);

const factorIcon = (type) => {
  const icons = {
    history: '📋',
    popularity: '🔥',
    department: '🏫',
    similarity: '🔗',
    membership: '⭐',
    trending: '📈',
  };
  return icons[type] || '💡';
};

const loadRecommendations = async () => {
  loading.value = true;
  error.value = '';
  try {
    const idempotencyKey = 'rec-' + Date.now();
    const { data } = await api.post('/recommendations/for-class', { class_id: selectedClassId.value }, { headers: { 'X-Idempotency-Key': idempotencyKey } });
    recommendations.value = data.recommendations || data.data || data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load recommendations.';
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  try {
    const { data } = await api.get('/my-classes');
    userClasses.value = data || [];
    if (userClasses.value.length) selectedClassId.value = userClasses.value[0].id;
  } catch {}
  await loadRecommendations();
});
</script>
