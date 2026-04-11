import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '../../../app/stores/auth.js';

vi.mock('../../../app/services/api.js', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}));

import api from '../../../app/services/api.js';

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
  });

  it('starts unauthenticated', () => {
    const store = useAuthStore();
    expect(store.isAuthenticated).toBe(false);
  });

  it('login sets user and token', async () => {
    const userData = { id: 1, username: 'test', roles: ['student'], reminders_count: 0 };
    api.post.mockResolvedValue({ data: { user: userData, token: 'abc' } });
    api.get.mockResolvedValue({ data: userData }); // fetchMe() call after login
    const store = useAuthStore();
    await store.login('test', 'pass');
    expect(store.isAuthenticated).toBe(true);
    expect(store.token).toBe('abc');
    expect(store.user.username).toBe('test');
    expect(store.user.roles).toContain('student');
  });

  it('logout clears state', async () => {
    api.post.mockResolvedValue({ data: {} });
    const store = useAuthStore();
    store.token = 'abc';
    store.user = { id: 1 };
    await store.logout();
    expect(store.isAuthenticated).toBe(false);
    expect(store.token).toBeNull();
  });

  it('isAdmin returns true for admin role', () => {
    const store = useAuthStore();
    store.user = { roles: ['admin'] };
    expect(store.isAdmin).toBe(true);
  });

  it('isStaff returns true for teacher', () => {
    const store = useAuthStore();
    store.user = { roles: ['teacher'] };
    expect(store.isStaff).toBe(true);
  });
});
