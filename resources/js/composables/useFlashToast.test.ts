import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import { useFlashToast } from '@/composables/useFlashToast';

const mocks = vi.hoisted(() => ({
    handler: null as null | ((event: Event) => void),
    stop: vi.fn(),
    success: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
    error: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        on: (_name: string, handler: (event: Event) => void) => {
            mocks.handler = handler;

            return mocks.stop;
        },
    },
}));

vi.mock('@/composables/useMfToast', () => ({
    useMfToast: () => ({
        success: mocks.success,
        info: mocks.info,
        warning: mocks.warning,
        error: mocks.error,
    }),
}));

const flash = (toast: unknown): void => {
    mocks.handler?.(new CustomEvent('flash', { detail: { flash: { toast } } }));
};

const mountHost = () =>
    mount(
        defineComponent({
            setup() {
                useFlashToast();

                return () => null;
            },
        }),
    );

describe('useFlashToast', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mocks.handler = null;
    });

    it.each(['success', 'info', 'warning', 'error'] as const)(
        'shows a flashed %s toast through MfToast',
        (type) => {
            mountHost();

            flash({ type, message: `A ${type} message` });

            expect(mocks[type]).toHaveBeenCalledExactlyOnceWith(
                `A ${type} message`,
            );
        },
    );

    it('ignores flashes without toast data', () => {
        mountHost();

        flash(undefined);

        expect(mocks.success).not.toHaveBeenCalled();
        expect(mocks.error).not.toHaveBeenCalled();
    });

    it('stops listening when the host unmounts', () => {
        mountHost().unmount();

        expect(mocks.stop).toHaveBeenCalledOnce();
    });
});
