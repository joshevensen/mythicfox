import { ref } from 'vue';

export type GlobalImportTab = 'orders' | 'catalog';

const visible = ref(false);
const activeTab = ref<GlobalImportTab>('orders');

export function useGlobalImportModal() {
    const open = (tab: GlobalImportTab = 'orders'): void => {
        activeTab.value = tab;
        visible.value = true;
    };

    const close = (): void => {
        visible.value = false;
    };

    return { activeTab, visible, open, close };
}
