import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useMfToast } from '@/composables/useMfToast';

const mocks = vi.hoisted(() => ({
    add: vi.fn(),
}));

vi.mock('primevue/usetoast', () => ({
    useToast: () => ({ add: mocks.add }),
}));

describe('useMfToast', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('auto-dismisses warnings by default', () => {
        useMfToast().warning('m', 't');

        expect(mocks.add).toHaveBeenCalledExactlyOnceWith({
            severity: 'warn',
            summary: 't',
            detail: 'm',
            life: 4000,
        });
    });

    it('keeps sticky warnings until dismissed by omitting life', () => {
        useMfToast().warning('m', 't', { sticky: true });

        expect(mocks.add).toHaveBeenCalledOnce();

        const toast = mocks.add.mock.calls[0][0];

        expect(toast).toMatchObject({
            severity: 'warn',
            summary: 't',
            detail: 'm',
        });
        expect(toast).not.toHaveProperty('life');
    });

    it('keeps auto-dismissing success toasts', () => {
        useMfToast().success('m');

        expect(mocks.add).toHaveBeenCalledExactlyOnceWith(
            expect.objectContaining({
                severity: 'success',
                detail: 'm',
                life: 4000,
            }),
        );
    });
});
