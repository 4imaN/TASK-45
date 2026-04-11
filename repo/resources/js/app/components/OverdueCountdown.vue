<template>
  <span :class="classes">{{ display }}</span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ dueDate: String, returnedAt: String });

const display = computed(() => {
  if (props.returnedAt) return 'Returned';
  if (!props.dueDate) return '';
  const diff = new Date(props.dueDate) - new Date();
  if (diff < 0) {
    const h = Math.abs(Math.floor(diff / 3600000));
    const d = Math.floor(h / 24);
    return d > 0 ? `Overdue by ${d}d ${h % 24}h` : `Overdue by ${h}h`;
  }
  const h = Math.floor(diff / 3600000);
  const d = Math.floor(h / 24);
  return d > 0 ? `${d}d ${h % 24}h left` : `${h}h left`;
});

const classes = computed(() => {
  if (props.returnedAt) return 'text-emerald-600 font-medium text-xs';
  if (!props.dueDate) return 'text-xs text-slate-400';
  const diff = new Date(props.dueDate) - new Date();
  if (diff < 0) return 'text-red-600 font-bold text-xs animate-pulse';
  if (diff < 48 * 3600000) return 'text-orange-500 font-semibold text-xs';
  return 'text-slate-500 text-xs';
});
</script>
