// Offline-first API client: all requests go to the co-located local Laravel API.
// No external cloud services, CDNs, or third-party APIs are used.
// The baseURL is relative, served from the same local HTTPS origin.
import axios from 'axios';
import { useAuthStore } from '../stores/auth.js';

const api = axios.create({ baseURL: '/api', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } });

api.interceptors.request.use(config => {
  const auth = useAuthStore();
  if (auth.token) config.headers.Authorization = `Bearer ${auth.token}`;
  return config;
});

api.interceptors.response.use(r => r, error => {
  if (!error.response) {
    // Network error — local server may be unreachable (offline or not started)
    console.error('Network error: the local server may be unreachable.');
  }
  if (error.response?.status === 401) {
    const auth = useAuthStore();
    auth.clearAuth();
    window.location.href = '/login';
  }
  return Promise.reject(error);
});

export default api;
