import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';

// Mocks must be declared before the imports that pull them in
const pushMock = vi.fn();
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}));

vi.mock('../../../app/services/api.js', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}));

import LoginView from '../../../app/views/auth/LoginView.vue';
import api from '../../../app/services/api.js';

describe('LoginView', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    pushMock.mockClear();
    vi.clearAllMocks();
    localStorage.clear();
  });

  it('renders the username and password inputs', () => {
    const wrapper = mount(LoginView, { global: { plugins: [createPinia()] } });
    expect(wrapper.find('input[autocomplete="username"]').exists()).toBe(true);
    expect(wrapper.find('input[autocomplete="current-password"]').exists()).toBe(true);
  });

  it('submits login with idempotency key and redirects to /catalog on success', async () => {
    const userData = { id: 1, username: 'student', roles: ['student'], force_password_change: false };
    api.post.mockResolvedValue({ data: { user: userData, token: 'tok-123' } });
    api.get.mockResolvedValue({ data: userData });

    const wrapper = mount(LoginView);
    await wrapper.find('input[autocomplete="username"]').setValue('student');
    await wrapper.find('input[autocomplete="current-password"]').setValue('pw');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    // login POST was called
    const loginCall = api.post.mock.calls.find(c => c[0] === '/auth/login');
    expect(loginCall).toBeDefined();
    expect(loginCall[1]).toEqual({ username: 'student', password: 'pw' });
    // idempotency key header is present
    expect(loginCall[2].headers['X-Idempotency-Key']).toMatch(/^login-/);

    expect(pushMock).toHaveBeenCalledWith('/catalog');
  });

  it('redirects to /change-password when force_password_change is true', async () => {
    const userData = { id: 1, username: 'student', roles: ['student'], force_password_change: true };
    api.post.mockResolvedValue({ data: { user: userData, token: 'tok' } });
    api.get.mockResolvedValue({ data: userData });

    const wrapper = mount(LoginView);
    await wrapper.find('input[autocomplete="username"]').setValue('student');
    await wrapper.find('input[autocomplete="current-password"]').setValue('pw');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(pushMock).toHaveBeenCalledWith('/change-password');
  });

  it('shows server error message when login fails', async () => {
    api.post.mockRejectedValue({ response: { data: { message: 'Invalid credentials.' } } });

    const wrapper = mount(LoginView);
    await wrapper.find('input[autocomplete="username"]').setValue('student');
    await wrapper.find('input[autocomplete="current-password"]').setValue('wrong');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(wrapper.text()).toContain('Invalid credentials.');
    expect(pushMock).not.toHaveBeenCalled();
  });

  it('falls back to a generic error message when the server returns no message', async () => {
    api.post.mockRejectedValue({ response: { data: {} } });

    const wrapper = mount(LoginView);
    await wrapper.find('input[autocomplete="username"]').setValue('student');
    await wrapper.find('input[autocomplete="current-password"]').setValue('wrong');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(wrapper.text()).toContain('Invalid credentials');
  });
});
