import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  RouterLink: { props: ['to'], template: '<a><slot /></a>' },
}));

vi.mock('../../../app/services/api.js', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}));

import ReservationsView from '../../../app/views/student/ReservationsView.vue';
import api from '../../../app/services/api.js';

const mountReservations = () =>
  mount(ReservationsView, {
    global: {
      stubs: { 'router-link': { props: ['to'], template: '<a><slot /></a>' } },
    },
  });

describe('ReservationsView', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('loads and renders reservations', async () => {
    api.get.mockResolvedValue({
      data: { data: [{ id: 10, status: 'approved', resource: { name: 'Lab 3' } }] },
    });

    const wrapper = mountReservations();
    await flushPromises();

    expect(api.get).toHaveBeenCalledWith('/reservations');
    expect(wrapper.text()).toContain('Lab 3');
    expect(wrapper.text()).toContain('Approved');
  });

  it('cancels a reservation with an idempotency key and refreshes', async () => {
    api.get.mockResolvedValue({
      data: { data: [{ id: 10, status: 'pending', resource: { name: 'Projector' } }] },
    });
    api.post.mockResolvedValue({ data: {} });

    const wrapper = mountReservations();
    await flushPromises();

    // Click the first available cancel button
    const cancelBtn = wrapper.findAll('button').find(b => b.text().toLowerCase().includes('cancel'));
    expect(cancelBtn).toBeDefined();
    await cancelBtn.trigger('click');
    await flushPromises();

    const postCall = api.post.mock.calls.find(c => c[0] === '/reservations/10/cancel');
    expect(postCall).toBeDefined();
    expect(postCall[2].headers['X-Idempotency-Key']).toMatch(/^cancel-res-10-/);
    // /reservations was called twice — initial + after cancel
    expect(api.get.mock.calls.filter(c => c[0] === '/reservations').length).toBe(2);
  });

  it('shows the error banner when loading fails', async () => {
    api.get.mockRejectedValue({ response: { data: { message: 'Nope.' } } });

    const wrapper = mountReservations();
    await flushPromises();

    expect(wrapper.text()).toContain('Nope.');
  });
});
