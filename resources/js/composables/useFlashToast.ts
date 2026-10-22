import { router } from '@inertiajs/vue3';
import { onUnmounted } from 'vue';
import { useMfToast } from '@/composables/useMfToast';
import type { FlashToast } from '@/types/ui';

/**
 * Renders server-flashed `toast` data (`back()->with('toast', ...)`) through
 * MfToast. Call once from a layout that mounts `<MfToast />`.
 */
export function useFlashToast(): void {
    const toast = useMfToast();

    const stopListening = router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        const show = {
            success: toast.success,
            info: toast.info,
            warning: toast.warning,
            error: toast.error,
        }[data.type];

        show?.(data.message);
    });

    onUnmounted(stopListening);
}
