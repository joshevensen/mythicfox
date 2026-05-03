import { ref } from 'vue';

const visible = ref(false);

export function useCatalogUploadModal() {
    const open = (): void => {
        visible.value = true;
    };

    const close = (): void => {
        visible.value = false;
    };

    return { visible, open, close };
}
