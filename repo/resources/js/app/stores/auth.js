import { defineStore } from 'pinia';
import api from '../services/api.js';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    token: localStorage.getItem('token') || null,
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.roles?.includes('admin') || false,
    isTeacher: (state) => state.user?.roles?.includes('teacher') || false,
    isTA: (state) => state.user?.roles?.includes('ta') || false,
    isStudent: (state) => state.user?.roles?.includes('student') || false,
    isStaff: (state) => state.user?.roles?.includes('admin') || state.user?.roles?.includes('teacher') || state.user?.roles?.includes('ta') || false,
  },
  actions: {
    async login(username, password) {
      const loginKey = 'login-' + Date.now() + '-' + Math.random().toString(36).slice(2);
      const { data } = await api.post('/auth/login', { username, password }, { headers: { 'X-Idempotency-Key': loginKey } });
      this.token = data.token;
      this.user = data.user;
      localStorage.setItem('token', data.token);
      localStorage.setItem('user', JSON.stringify(data.user));
      // Fetch full profile with reminders_count
      await this.fetchMe();
      return data;
    },
    async logout() {
      try { await api.post('/auth/logout', {}, { headers: { 'X-Idempotency-Key': 'logout-' + Date.now() } }); } catch {}
      this.clearAuth();
    },
    clearAuth() {
      this.token = null;
      this.user = null;
      localStorage.removeItem('token');
      localStorage.removeItem('user');
    },
    async fetchMe() {
      const { data } = await api.get('/auth/me');
      this.user = data;
      localStorage.setItem('user', JSON.stringify(data));
    },
    async changePassword(payload) {
      const key = 'chgpwd-' + Date.now();
      await api.post('/auth/change-password', payload, { headers: { 'X-Idempotency-Key': key } });
      this.user = { ...this.user, force_password_change: false };
      localStorage.setItem('user', JSON.stringify(this.user));
    },
  },
});
