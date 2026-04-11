import { onMounted, onUnmounted, ref } from 'vue';
export function usePolling(callback, intervalMs = 30000) {
  const timer = ref(null);
  onMounted(() => { callback(); timer.value = setInterval(callback, intervalMs); });
  onUnmounted(() => { if (timer.value) clearInterval(timer.value); });
}
