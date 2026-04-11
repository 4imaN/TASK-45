import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import OverdueCountdown from '../../../app/components/OverdueCountdown.vue';

describe('OverdueCountdown', () => {
  it('shows "Returned" when returned', () => {
    const wrapper = mount(OverdueCountdown, { props: { dueDate: '2024-01-01', returnedAt: '2024-01-01' } });
    expect(wrapper.text()).toContain('Returned');
  });

  it('shows overdue for past dates', () => {
    const past = new Date(Date.now() - 86400000).toISOString();
    const wrapper = mount(OverdueCountdown, { props: { dueDate: past } });
    expect(wrapper.text()).toContain('Overdue');
  });

  it('shows time remaining for future dates', () => {
    const future = new Date(Date.now() + 3 * 86400000).toISOString();
    const wrapper = mount(OverdueCountdown, { props: { dueDate: future } });
    expect(wrapper.text()).toContain('left');
  });

  it('uses warning color for items due within 48h', () => {
    const soon = new Date(Date.now() + 24 * 3600000).toISOString();
    const wrapper = mount(OverdueCountdown, { props: { dueDate: soon } });
    expect(wrapper.find('span').classes()).toContain('text-orange-500');
  });

  it('uses alert color for overdue items', () => {
    const past = new Date(Date.now() - 3600000).toISOString();
    const wrapper = mount(OverdueCountdown, { props: { dueDate: past } });
    expect(wrapper.find('span').classes()).toContain('text-red-600');
  });
});
