import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useMembershipStore } from '../../../app/stores/membership.js';

vi.mock('../../../app/services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}));

import api from '../../../app/services/api.js';

describe('Membership Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('fetches membership data', async () => {
    api.get.mockResolvedValue({ data: { membership: { tier: 'Basic' }, points_balance: 100, stored_value_cents: 5000, entitlements: [] } });
    const store = useMembershipStore();
    await store.fetchMembership();
    expect(store.pointsBalance).toBe(100);
    expect(store.storedValueCents).toBe(5000);
  });

  it('redeems points', async () => {
    api.post.mockResolvedValue({ data: { balance: 50 } });
    const store = useMembershipStore();
    const result = await store.redeemPoints(50, 'Test');
    expect(store.pointsBalance).toBe(50);
  });
});
