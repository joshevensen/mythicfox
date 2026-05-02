import { useConfirm } from 'primevue/useconfirm';

// Banned generic verbs — callers must pick a specific verb so the dialog reads
// correctly out of context (the action button reads 'Delete' not 'OK', etc.).
export type ConfirmVerb =
    | 'Delete'
    | 'Reset'
    | 'Clear'
    | 'Discard'
    | 'Remove'
    | 'Cancel'
    | 'Archive'
    | 'Restore'
    | 'Send'
    | 'Save'
    | (string & {});

const BANNED_VERBS = ['OK', 'Yes', 'Confirm', 'Continue'];

export type MfConfirmOptions = {
    title: string;
    body: string;
    verb: ConfirmVerb;
    destructive?: boolean;
    onConfirm: () => void;
    onCancel?: () => void;
};

export function useMfConfirm() {
    const confirmService = useConfirm();

    const confirm = (options: MfConfirmOptions): void => {
        if (BANNED_VERBS.includes(options.verb)) {
            console.warn(
                `useMfConfirm: verb "${options.verb}" is too generic — pick a specific verb (Delete, Reset, Clear, etc.).`,
            );
        }

        confirmService.require({
            header: options.title,
            message: options.body,
            acceptLabel: options.verb,
            rejectLabel: 'Cancel',
            acceptProps: {
                severity: options.destructive ? 'danger' : 'primary',
            },
            rejectProps: {
                severity: 'secondary',
                outlined: true,
            },
            accept: options.onConfirm,
            reject: options.onCancel,
        });
    };

    return { confirm };
}
