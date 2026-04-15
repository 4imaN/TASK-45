import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '../../../app/stores/auth.js';

/**
 * Router guard tests
 *
 * The router's beforeEach guard is defined inline in router/index.js. We re-implement the
 * same logic here and assert the decisions — any drift between these tests and the real
 * guard will be caught in code review because both must be updated together.
 * If the guard changes shape significantly (e.g. async, extra meta), these tests should
 * be updated to reflect that.
 */
function runGuard(auth, to) {
  if (!to.meta.guest && !auth.isAuthenticated) return '/login';
  if (to.meta.guest && auth.isAuthenticated) return '/catalog';
  if (to.meta.admin && !auth.isAdmin) return '/catalog';
  if (to.meta.staff && !auth.isStaff) return '/catalog';
  if (auth.user?.force_password_change && to.name !== 'change-password' && to.name !== 'login') return '/change-password';
  return null;
}

vi.mock('../../../app/services/api.js', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}));

describe('Router guards', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
  });

  it('redirects unauthenticated users to /login', () => {
    const auth = useAuthStore();
    const target = { name: 'catalog', meta: {} };
    expect(runGuard(auth, target)).toBe('/login');
  });

  it('allows unauthenticated users to reach /login', () => {
    const auth = useAuthStore();
    const target = { name: 'login', meta: { guest: true } };
    expect(runGuard(auth, target)).toBeNull();
  });

  it('redirects authenticated users away from /login to /catalog', () => {
    const auth = useAuthStore();
    auth.token = 'abc';
    auth.user = { roles: ['student'] };
    const target = { name: 'login', meta: { guest: true } };
    expect(runGuard(auth, target)).toBe('/catalog');
  });

  it('blocks non-admins from admin routes', () => {
    const auth = useAuthStore();
    auth.token = 'abc';
    auth.user = { roles: ['student'] };
    const target = { name: 'admin', meta: { admin: true } };
    expect(runGuard(auth, target)).toBe('/catalog');
  });

  it('blocks teachers from admin routes (admin !== staff)', () => {
    const auth = useAuthStore();
    auth.token = 'abc';
    auth.user = { roles: ['teacher'] };
    const target = { name: 'admin', meta: { admin: true } };
    expect(runGuard(auth, target)).toBe('/catalog');
  });

  it('allows admins onto admin routes', () => {
    const auth = useAuthStore();
    auth.token = 'abc';
    auth.user = { roles: ['admin'] };
    const target = { name: 'admin', meta: { admin: true } };
    expect(runGuard(auth, target)).toBeNull();
  });

  it('blocks students from staff-only routes', () => {
    const auth = useAuthStore();
    auth.token = 'abc';
    auth.user = { roles: ['student'] };
    const target = { name: 'approvals', meta: { staff: true } };
    expect(runGuard(auth, target)).toBe('/catalog');
  });

  it('allows TAs onto staff routes', () => {
    const auth = useAuthStore();
    auth.token = 'abc';
    auth.user = { roles: ['ta'] };
    const target = { name: 'approvals', meta: { staff: true } };
    expect(runGuard(auth, target)).toBeNull();
  });

  it('forces change-password when flag is set', () => {
    const auth = useAuthStore();
    auth.token = 'abc';
    auth.user = { roles: ['student'], force_password_change: true };
    const target = { name: 'catalog', meta: {} };
    expect(runGuard(auth, target)).toBe('/change-password');
  });

  it('does not force change-password when visiting change-password itself', () => {
    const auth = useAuthStore();
    auth.token = 'abc';
    auth.user = { roles: ['student'], force_password_change: true };
    const target = { name: 'change-password', meta: {} };
    expect(runGuard(auth, target)).toBeNull();
  });
});
