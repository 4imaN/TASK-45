import { defineStore } from 'pinia';
import api from '../services/api.js';

const idemKey = () => crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now();

export const useLoansStore = defineStore('loans', {
  state: () => ({ loans: [], loading: false }),
  actions: {
    async fetchLoans(params = {}) {
      this.loading = true;
      try {
        const { data } = await api.get('/loans', { params });
        this.loans = data.data;
      } finally { this.loading = false; }
    },
    async createLoan(payload) {
      payload.idempotency_key = payload.idempotency_key || idemKey();
      const { data } = await api.post('/loans', payload, { headers: { 'X-Idempotency-Key': payload.idempotency_key } });
      return data;
    },
    async approveLoan(loanId, status, reason = null) {
      const key = idemKey();
      const { data } = await api.post(`/loans/${loanId}/approve`, { status, reason }, { headers: { 'X-Idempotency-Key': key } });
      return data;
    },
    async checkoutLoan(loanId) {
      const key = idemKey();
      const { data } = await api.post(`/loans/${loanId}/checkout`, {}, { headers: { 'X-Idempotency-Key': key } });
      return data;
    },
    async checkinCheckout(checkoutId, condition, notes) {
      const key = idemKey();
      const { data } = await api.post(`/checkouts/${checkoutId}/checkin`, { condition, notes }, { headers: { 'X-Idempotency-Key': key } });
      return data;
    },
    async renewCheckout(checkoutId) {
      const key = idemKey();
      const { data } = await api.post(`/checkouts/${checkoutId}/renew`, {}, { headers: { 'X-Idempotency-Key': key } });
      return data;
    },
  },
});
