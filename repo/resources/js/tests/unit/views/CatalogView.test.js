import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  // Stub RouterLink as a simple slot-rendering anchor so template parses without a real router
  RouterLink: { props: ['to'], template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>' },
}));

vi.mock('../../../app/services/api.js', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}));

// usePolling triggers setInterval; stub to a no-op so tests don't leak timers
vi.mock('../../../app/composables/usePolling.js', () => ({
  usePolling: vi.fn(),
}));

import CatalogView from '../../../app/views/student/CatalogView.vue';
import api from '../../../app/services/api.js';

describe('CatalogView', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('fetches the catalog on mount and renders resource cards', async () => {
    api.get.mockResolvedValue({
      data: {
        data: [
          { id: 1, name: 'Microscope', type: 'equipment', available_quantity: 3 },
          { id: 2, name: 'Lecture Hall', type: 'venue', available_quantity: 1 },
        ],
        meta: { current_page: 1, last_page: 1 },
      },
    });

    const wrapper = mount(CatalogView, {
      global: {
        stubs: { 'router-link': { props: ['to'], template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>' } },
      },
    });
    await flushPromises();

    expect(api.get).toHaveBeenCalledWith('/catalog', expect.objectContaining({ params: expect.any(Object) }));
    expect(wrapper.text()).toContain('Microscope');
    expect(wrapper.text()).toContain('Lecture Hall');
  });

  it('shows an empty state when no resources match', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: { current_page: 1, last_page: 1 } } });

    const wrapper = mount(CatalogView, {
      global: {
        stubs: { 'router-link': { props: ['to'], template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>' } },
      },
    });
    await flushPromises();

    expect(wrapper.text()).toContain('No resources found');
  });

  it('displays an error banner when the request fails', async () => {
    api.get.mockRejectedValue({ response: { data: { message: 'Server exploded.' } } });

    const wrapper = mount(CatalogView, {
      global: {
        stubs: { 'router-link': { props: ['to'], template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>' } },
      },
    });
    await flushPromises();

    expect(wrapper.text()).toContain('Server exploded.');
  });

  it('forwards the selected type filter to the API', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: null } });

    const wrapper = mount(CatalogView, {
      global: {
        stubs: { 'router-link': { props: ['to'], template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>' } },
      },
    });
    await flushPromises();

    api.get.mockClear();
    await wrapper.find('select').setValue('venue');
    await flushPromises();

    const call = api.get.mock.calls[0];
    expect(call[0]).toBe('/catalog');
    expect(call[1].params.resource_type).toBe('venue');
  });
});
