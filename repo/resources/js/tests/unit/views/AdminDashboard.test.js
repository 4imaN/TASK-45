import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  RouterLink: { props: ['to'], template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>' },
}));

vi.mock('../../../app/services/api.js', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}));

import AdminDashboard from '../../../app/views/admin/AdminDashboard.vue';
import api from '../../../app/services/api.js';

const mountDashboard = () =>
  mount(AdminDashboard, {
    global: {
      stubs: { 'router-link': { props: ['to'], template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>' } },
    },
  });

describe('AdminDashboard', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('loads stats and recent activity from the admin endpoints', async () => {
    api.get.mockImplementation((url) => {
      if (url === '/admin/stats') return Promise.resolve({ data: { users: 42, active_holds: 3 } });
      if (url === '/admin/audit-logs') return Promise.resolve({ data: { data: [{ id: 1, action: 'login', user: { display_name: 'Jane' }, created_at: new Date().toISOString() }] } });
      return Promise.resolve({ data: {} });
    });

    const wrapper = mountDashboard();
    await flushPromises();

    expect(api.get).toHaveBeenCalledWith('/admin/stats');
    expect(api.get).toHaveBeenCalledWith('/admin/audit-logs', { params: { per_page: 8 } });
    expect(wrapper.text()).toContain('login');
  });

  it('renders the full admin navigation grid', () => {
    api.get.mockResolvedValue({ data: {} });
    const wrapper = mountDashboard();
    const text = wrapper.text();

    expect(text).toContain('Scope Management');
    expect(text).toContain('Account Holds');
    expect(text).toContain('Audit Log');
    expect(text).toContain('Data Quality');
    expect(text).toContain('Allowlists');
    expect(text).toContain('Blacklists');
    expect(text).toContain('Interventions');
    expect(text).toContain('Alias Normalization');
  });

  it('tolerates a failing stats call without crashing', async () => {
    api.get.mockImplementation((url) => {
      if (url === '/admin/stats') return Promise.reject(new Error('nope'));
      return Promise.resolve({ data: { data: [] } });
    });

    const wrapper = mountDashboard();
    await flushPromises();

    // Still renders the shell / navigation
    expect(wrapper.text()).toContain('Scope Management');
  });
});
