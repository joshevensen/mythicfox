import { reactive } from 'vue';

/**
 * Inline-edit save coordinator for table cells.
 *
 * Tracks per-cell save state (idle / saving / error) and coalesces in-flight
 * writes to the same cell via AbortController. Independent cells run in
 * parallel; concurrent writes to the same cell collapse to last-write-wins
 * via abort + re-dispatch.
 *
 * Pattern documented in docs/ux/inventory.md#save-semantics-inline-edits.
 */

export type CellState = {
    saving: boolean;
    error: string | null;
};

export type CellKey = string;

export type SaveFn<TInput, TResult> = (
    input: TInput,
    signal: AbortSignal,
) => Promise<TResult>;

export function useInlineCellSave<TInput, TResult = unknown>(
    save: SaveFn<TInput, TResult>,
) {
    const states = reactive<Record<CellKey, CellState>>({});
    const controllers = new Map<CellKey, AbortController>();

    const stateFor = (key: CellKey): CellState =>
        states[key] ?? { saving: false, error: null };

    const dispatch = async (
        key: CellKey,
        input: TInput,
    ): Promise<TResult | null> => {
        const previous = controllers.get(key);

        if (previous) {
            previous.abort();
        }

        const controller = new AbortController();
        controllers.set(key, controller);

        states[key] = { saving: true, error: null };

        try {
            const result = await save(input, controller.signal);

            // If we were aborted by a newer dispatch, suppress the result —
            // the newer call owns the cell state now.
            if (controller.signal.aborted) {
                return null;
            }

            states[key] = { saving: false, error: null };

            return result;
        } catch (error) {
            if (
                (error instanceof DOMException &&
                    error.name === 'AbortError') ||
                controller.signal.aborted
            ) {
                return null;
            }

            const message =
                error instanceof Error ? error.message : 'Save failed';
            states[key] = { saving: false, error: message };

            return null;
        } finally {
            if (controllers.get(key) === controller) {
                controllers.delete(key);
            }
        }
    };

    const clearError = (key: CellKey): void => {
        if (states[key]) {
            states[key] = { ...states[key], error: null };
        }
    };

    return {
        states,
        stateFor,
        dispatch,
        clearError,
    };
}
