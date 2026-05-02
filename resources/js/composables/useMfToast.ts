import { useToast } from 'primevue/usetoast';

const AUTO_DISMISS_MS = 4000;

export function useMfToast() {
    const toast = useToast();

    const success = (message: string, title?: string): void => {
        toast.add({
            severity: 'success',
            summary: title ?? 'Success',
            detail: message,
            life: AUTO_DISMISS_MS,
        });
    };

    const error = (message: string, title?: string): void => {
        toast.add({
            severity: 'error',
            summary: title ?? 'Error',
            detail: message,
        });
    };

    const info = (message: string, title?: string): void => {
        toast.add({
            severity: 'info',
            summary: title ?? 'Info',
            detail: message,
            life: AUTO_DISMISS_MS,
        });
    };

    return { success, error, info };
}
