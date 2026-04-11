import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useCatalogStore } from '../../../app/stores/catalog.js';

vi.mock('../../../app/services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}));

import api from '../../../app/services/api.js';

describe('Catalog Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('fetches resources', async () => {
    api.get.mockResolvedValue({ data: { data: [{ id: 1, name: 'Laptop' }], meta: { total: 1 } } });
    const store = useCatalogStore();
    await store.fetchResources();
    expect(store.resources).toHaveLength(1);
    expect(store.resources[0].name).toBe('Laptop');
  });

  it('sets filters', () => {
    const store = useCatalogStore();
    store.setFilter('resource_type', 'equipment');
    expect(store.filters.resource_type).toBe('equipment');
  });
});
