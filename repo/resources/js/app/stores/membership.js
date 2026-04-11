import { defineStore } from 'pinia';
import api from '../services/api.js';

const idemKey = () => crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now();

export const useMembershipStore = defineStore('membership', {
  state: () => ({ membership: null, pointsBalance: 0, storedValueCents: 0, entitlements: [], loading: false }),
  actions: {
    async fetchMembership() {
      this.loading = true;
      try {
        const { data } = await api.get('/memberships/me');
        this.membership = data.membership;
        this.pointsBalance = data.points_balance;
        this.storedValueCents = data.stored_value_cents;
        this.entitlements = data.entitlements;
      } finally { this.loading = false; }
    },
    async redeemPoints(points, description) {
      const key = idemKey();
      const { data } = await api.post('/memberships/redeem-points', { points, description }, { headers: { 'X-Idempotency-Key': key } });
      this.pointsBalance = data.balance;
      return data;
    },
    async redeemStoredValue(amountCents, description) {
      const key = idemKey();
      const { data } = await api.post('/memberships/redeem-stored-value', { amount_cents: amountCents, description, idempotency_key: key }, { headers: { 'X-Idempotency-Key': key } });
      this.storedValueCents = data.balance_cents;
      return data;
    },
  },
});
