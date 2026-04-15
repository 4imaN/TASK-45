import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { defineComponent, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { usePolling } from '../../../app/composables/usePolling.js';

describe('usePolling', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });
  afterEach(() => {
    vi.useRealTimers();
  });

  it('fires the callback immediately on mount and then on the interval', async () => {
    const cb = vi.fn();

    const Host = defineComponent({
      template: '<div />',
      setup() {
        usePolling(cb, 1000);
      },
    });

    const wrapper = mount(Host);
    await nextTick();
    expect(cb).toHaveBeenCalledTimes(1);

    vi.advanceTimersByTime(1000);
    expect(cb).toHaveBeenCalledTimes(2);

    vi.advanceTimersByTime(2500);
    expect(cb).toHaveBeenCalledTimes(4);

    wrapper.unmount();
  });

  it('clears the interval when the host component unmounts', async () => {
    const cb = vi.fn();

    const Host = defineComponent({
      template: '<div />',
      setup() {
        usePolling(cb, 500);
      },
    });

    const wrapper = mount(Host);
    await nextTick();
    expect(cb).toHaveBeenCalledTimes(1);

    wrapper.unmount();

    vi.advanceTimersByTime(5000);
    // Still just the single initial invocation — no interval firing after unmount
    expect(cb).toHaveBeenCalledTimes(1);
  });
});
