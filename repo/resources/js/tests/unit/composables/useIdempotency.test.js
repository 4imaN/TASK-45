import { describe, it, expect, vi } from 'vitest';
import { useIdempotency } from '../../../app/composables/useIdempotency.js';

describe('useIdempotency', () => {
  it('returns a unique key each call when crypto.randomUUID is available', () => {
    const { generateKey } = useIdempotency();
    const a = generateKey();
    const b = generateKey();
    expect(typeof a).toBe('string');
    expect(a.length).toBeGreaterThan(0);
    expect(a).not.toBe(b);
  });

  it('falls back to Math.random when randomUUID is not available', () => {
    // Stub randomUUID to undefined via spy (crypto itself is read-only in jsdom)
    const spy = vi.spyOn(globalThis.crypto, 'randomUUID').mockReturnValue(undefined);
    // Force the `? :` branch by pretending randomUUID is missing
    const originalDesc = Object.getOwnPropertyDescriptor(globalThis.crypto, 'randomUUID');
    Object.defineProperty(globalThis.crypto, 'randomUUID', { value: undefined, configurable: true });

    const { generateKey } = useIdempotency();
    const key = generateKey();
    expect(typeof key).toBe('string');
    expect(key.length).toBeGreaterThan(0);

    if (originalDesc) Object.defineProperty(globalThis.crypto, 'randomUUID', originalDesc);
    spy.mockRestore();
  });
});
