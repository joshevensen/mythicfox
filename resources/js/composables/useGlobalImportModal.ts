import { ref } from 'vue';

export type GlobalImportTab = 'catalog' | 'orders';

const visible = ref(false);
const activeTab = ref<GlobalImportTab>('catalog');

export function useGlobalImportModal() {
    const open = (tab: GlobalImportTab = 'catalog'): void => {
        activeTab.value = tab;
        visible.value = true;
    };

    const close = (): void => {
        visible.value = false;
    };

    return { activeTab, visible, open, close };
}
