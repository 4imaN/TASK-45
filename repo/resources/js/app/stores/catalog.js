import { defineStore } from 'pinia';
import api from '../services/api.js';

export const useCatalogStore = defineStore('catalog', {
  state: () => ({ resources: [], pagination: null, loading: false, filters: {} }),
  actions: {
    async fetchResources(params = {}) {
      this.loading = true;
      try {
        const { data } = await api.get('/catalog', { params: { ...this.filters, ...params } });
        this.resources = data.data;
        this.pagination = data.meta;
      } finally { this.loading = false; }
    },
    async fetchResource(id) {
      const { data } = await api.get(`/catalog/${id}`);
      const resource = data.data || data;
      const availability = data.availability || {};
      const lots = availability.lots || [];
      return {
        ...resource,
        available_quantity: availability.available_quantity ?? resource.available_quantity ?? 0,
        total_quantity: lots.reduce((sum, l) => sum + (l.total || 0), 0),
        units: lots.map(l => ({
          id: l.id,
          serial_number: l.lot_number,
          condition: 'good',
          available: l.available > 0,
          location: l.location || null,
        })),
        type: resource.type || resource.resource_type,
        department: typeof resource.department === 'object' ? resource.department?.name : resource.department,
        venue: data.venue || null,
      };
    },
    setFilter(key, value) { this.filters[key] = value; },
  },
});
