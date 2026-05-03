import { ref } from 'vue';

const visible = ref(false);

export function useOrdersImportModal() {
    const open = (): void => {
        visible.value = true;
    };

    const close = (): void => {
        visible.value = false;
    };

    return { visible, open, close };
}
