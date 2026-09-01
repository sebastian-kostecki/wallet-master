import { ref } from 'vue';

const createRequested = ref(false);

export function requestOpenCreateTransaction(): void {
    createRequested.value = true;
}

export function useTransactionFormDialog() {
    return { createRequested };
}
