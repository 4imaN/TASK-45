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

import LoansView from '../../../app/views/student/LoansView.vue';
import api from '../../../app/services/api.js';

const mountLoans = () =>
  mount(LoansView, {
    global: {
      stubs: {
        'router-link': { props: ['to'], template: '<a><slot /></a>' },
        OverdueCountdown: true,
      },
    },
  });

describe('LoansView', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('fetches loans on mount and renders the list', async () => {
    api.get.mockResolvedValue({
      data: {
        data: [
          { id: 1, status: 'checked_out', resource: { name: 'Microscope' }, checkout: { due_date: '2026-05-01' } },
          { id: 2, status: 'pending', resource: { name: 'Camera' } },
        ],
      },
    });

    const wrapper = mountLoans();
    await flushPromises();

    expect(api.get).toHaveBeenCalledWith('/loans', { params: {} });
    expect(wrapper.text()).toContain('Microscope');
    expect(wrapper.text()).toContain('Camera');
  });

  it('forwards the status filter to the API', async () => {
    api.get.mockResolvedValue({ data: { data: [] } });
    const wrapper = mountLoans();
    await flushPromises();

    api.get.mockClear();
    await wrapper.find('select').setValue('approved');
    await flushPromises();

    expect(api.get).toHaveBeenCalledWith('/loans', { params: { status: 'approved' } });
  });

  it('shows an error banner when the fetch fails', async () => {
    api.get.mockRejectedValue({ response: { data: { message: 'Boom.' } } });

    const wrapper = mountLoans();
    await flushPromises();

    expect(wrapper.text()).toContain('Boom.');
  });
});
