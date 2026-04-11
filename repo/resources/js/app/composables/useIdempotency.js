export function useIdempotency() {
  const generateKey = () => crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).substring(2);
  return { generateKey };
}
