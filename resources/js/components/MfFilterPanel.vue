<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import InputNumber from 'primevue/inputnumber';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import { computed, ref, watch } from 'vue';
import type {
    ActiveFilter,
    FilterDef,
    FilterValue,
} from '@/components/MfFilter.types';
import MfFilterChips from '@/components/MfFilterChips.vue';
import MfSearchInput from '@/components/MfSearchInput.vue';

type Props = {
    filters: FilterDef[];
    endpoint?: string;
};

const props = defineProps<Props>();

const page = usePage();

const initialUrl = (): URL => {
    const href =
        typeof window === 'undefined'
            ? 'http://localhost/'
            : window.location.href;

    return new URL(href);
};

const readValueForFilter = (def: FilterDef): FilterValue => {
    const url = initialUrl();

    switch (def.kind) {
        case 'text': {
            return url.searchParams.get(def.key) ?? '';
        }
        case 'enum': {
            const raw = url.searchParams.get(def.key);

            return raw ? raw.split(',').filter((v) => v.length > 0) : [];
        }
        case 'select': {
            return url.searchParams.get(def.key) ?? '';
        }
        case 'range': {
            return {
                min: url.searchParams.get(`${def.key}_min`) ?? undefined,
                max: url.searchParams.get(`${def.key}_max`) ?? undefined,
            };
        }
        case 'date': {
            return {
                from: url.searchParams.get(`${def.key}_from`) ?? undefined,
                to: url.searchParams.get(`${def.key}_to`) ?? undefined,
            };
        }
        case 'boolean': {
            return url.searchParams.get(def.key) === '1';
        }
    }
};

const values = ref<Record<string, FilterValue>>(
    Object.fromEntries(
        props.filters.map((f) => [f.key, readValueForFilter(f)]),
    ),
);

watch(
    () => page.url,
    () => {
        for (const def of props.filters) {
            values.value[def.key] = readValueForFilter(def);
        }
    },
);

const writeUrl = (mutate: (params: URLSearchParams) => void) => {
    const url = initialUrl();
    const params = url.searchParams;

    mutate(params);
    params.delete('page');

    const target = props.endpoint ?? `${url.pathname}`;

    router.get(target, Object.fromEntries(params.entries()), {
        preserveState: true,
        preserveScroll: true,
    });
};

const setFilter = (def: FilterDef, value: FilterValue) => {
    values.value[def.key] = value;

    writeUrl((params) => {
        const removeKeys = (keys: string[]) => {
            for (const k of keys) {
                params.delete(k);
            }
        };

        switch (def.kind) {
            case 'text': {
                const text = String(value ?? '');

                if (text.length > 0) {
                    params.set(def.key, text);
                } else {
                    params.delete(def.key);
                }

                break;
            }
            case 'enum': {
                const list = (value as string[]) ?? [];

                if (list.length > 0) {
                    params.set(def.key, list.join(','));
                } else {
                    params.delete(def.key);
                }

                break;
            }
            case 'select': {
                const text = String(value ?? '');

                if (text.length > 0) {
                    params.set(def.key, text);
                } else {
                    params.delete(def.key);
                }

                break;
            }
            case 'range': {
                removeKeys([`${def.key}_min`, `${def.key}_max`]);

                const range = (value as { min?: string; max?: string }) ?? {};

                if (
                    range.min !== undefined &&
                    range.min !== null &&
                    range.min !== ''
                ) {
                    params.set(`${def.key}_min`, String(range.min));
                }

                if (
                    range.max !== undefined &&
                    range.max !== null &&
                    range.max !== ''
                ) {
                    params.set(`${def.key}_max`, String(range.max));
                }

                break;
            }
            case 'date': {
                removeKeys([`${def.key}_from`, `${def.key}_to`]);

                const date = (value as { from?: string; to?: string }) ?? {};

                if (date.from) {
                    params.set(`${def.key}_from`, date.from);
                }

                if (date.to) {
                    params.set(`${def.key}_to`, date.to);
                }

                break;
            }
            case 'boolean': {
                if (value === true) {
                    params.set(def.key, '1');
                } else {
                    params.delete(def.key);
                }

                break;
            }
        }
    });
};

const removeFilter = (key: string) => {
    const def = props.filters.find((f) => f.key === key);

    if (!def) {
        return;
    }

    switch (def.kind) {
        case 'text': {
            setFilter(def, '');
            break;
        }
        case 'enum': {
            setFilter(def, []);
            break;
        }
        case 'select': {
            setFilter(def, '');
            break;
        }
        case 'range': {
            setFilter(def, { min: undefined, max: undefined });
            break;
        }
        case 'date': {
            setFilter(def, { from: undefined, to: undefined });
            break;
        }
        case 'boolean': {
            setFilter(def, false);
            break;
        }
    }
};

const clearAll = () => {
    writeUrl((params) => {
        for (const def of props.filters) {
            switch (def.kind) {
                case 'range': {
                    params.delete(`${def.key}_min`);
                    params.delete(`${def.key}_max`);
                    break;
                }
                case 'date': {
                    params.delete(`${def.key}_from`);
                    params.delete(`${def.key}_to`);
                    break;
                }
                default: {
                    params.delete(def.key);
                }
            }
        }
    });
};

const activeFilters = computed<ActiveFilter[]>(() => {
    const list: ActiveFilter[] = [];

    for (const def of props.filters) {
        const value = values.value[def.key];

        if (
            def.kind === 'text' &&
            typeof value === 'string' &&
            value.length > 0
        ) {
            list.push({
                key: def.key,
                label: def.label,
                display: value,
                raw: value,
            });
        } else if (
            def.kind === 'enum' &&
            Array.isArray(value) &&
            value.length > 0
        ) {
            const labels = value.map(
                (v) => def.options?.find((o) => o.value === v)?.label ?? v,
            );

            list.push({
                key: def.key,
                label: def.label,
                display: labels.join(', '),
                raw: value,
            });
        } else if (
            def.kind === 'select' &&
            typeof value === 'string' &&
            value.length > 0
        ) {
            const label =
                def.options?.find((o) => o.value === value)?.label ?? value;

            list.push({
                key: def.key,
                label: def.label,
                display: label,
                raw: value,
            });
        } else if (def.kind === 'range') {
            const range = value as { min?: string; max?: string } | null;

            if (range && (range.min || range.max)) {
                list.push({
                    key: def.key,
                    label: def.label,
                    display: `${range.min ?? '…'}–${range.max ?? '…'}`,
                    raw: range,
                });
            }
        } else if (def.kind === 'date') {
            const date = value as { from?: string; to?: string } | null;

            if (date && (date.from || date.to)) {
                list.push({
                    key: def.key,
                    label: def.label,
                    display: `${date.from ?? '…'} → ${date.to ?? '…'}`,
                    raw: date,
                });
            }
        } else if (def.kind === 'boolean' && value === true) {
            list.push({
                key: def.key,
                label: def.label,
                display: 'Yes',
                raw: true,
            });
        }
    }

    return list;
});

const hasActive = computed(() => activeFilters.value.length > 0);

const FILTER_PANEL_CONTENT = computed(() => props.filters);

const rangeOf = (key: string) =>
    (values.value[key] as { min?: string; max?: string }) ?? {};
const dateOf = (key: string) =>
    (values.value[key] as { from?: string; to?: string }) ?? {};

const setRangeMin = (def: FilterDef, raw: number | null): void => {
    setFilter(def, {
        ...rangeOf(def.key),
        min: raw === null ? undefined : String(raw),
    });
};
const setRangeMax = (def: FilterDef, raw: number | null): void => {
    setFilter(def, {
        ...rangeOf(def.key),
        max: raw === null ? undefined : String(raw),
    });
};
const setDateFrom = (def: FilterDef, raw: string): void => {
    setFilter(def, { ...dateOf(def.key), from: raw || undefined });
};
const setDateTo = (def: FilterDef, raw: string): void => {
    setFilter(def, { ...dateOf(def.key), to: raw || undefined });
};
</script>

<template>
    <div data-mf-component="filter-panel">
        <MfFilterChips
            v-if="hasActive"
            class="mb-3"
            :filters="activeFilters"
            @remove="removeFilter"
            @clear-all="clearAll"
        />

        <div
            class="flex flex-wrap items-end gap-4 rounded-lg border border-border bg-card p-4"
        >
            <div
                v-for="def in FILTER_PANEL_CONTENT"
                :key="def.key"
                class="flex flex-col gap-1.5"
            >
                <label class="text-xs font-medium text-muted-foreground">{{
                    def.label
                }}</label>
                <MfSearchInput
                    v-if="def.kind === 'text'"
                    :model-value="(values[def.key] as string) ?? ''"
                    :placeholder="def.label"
                    @update:model-value="(v: string) => setFilter(def, v)"
                />
                <MultiSelect
                    v-else-if="def.kind === 'enum'"
                    :model-value="(values[def.key] as string[]) ?? []"
                    :options="def.options"
                    option-value="value"
                    option-label="label"
                    display="chip"
                    :placeholder="def.label"
                    @update:model-value="(v: string[]) => setFilter(def, v)"
                />
                <Select
                    v-else-if="def.kind === 'select'"
                    :model-value="(values[def.key] as string) ?? ''"
                    :options="def.options"
                    option-value="value"
                    option-label="label"
                    :placeholder="def.label"
                    show-clear
                    @update:model-value="
                        (v: string | null) => setFilter(def, v ?? '')
                    "
                />
                <div
                    v-else-if="def.kind === 'range'"
                    class="flex items-center gap-2"
                >
                    <InputNumber
                        :model-value="
                            rangeOf(def.key).min !== undefined
                                ? Number(rangeOf(def.key).min)
                                : null
                        "
                        placeholder="Min"
                        @update:model-value="
                            (v: number | null) => setRangeMin(def, v)
                        "
                    />
                    <span class="text-muted-foreground">–</span>
                    <InputNumber
                        :model-value="
                            rangeOf(def.key).max !== undefined
                                ? Number(rangeOf(def.key).max)
                                : null
                        "
                        placeholder="Max"
                        @update:model-value="
                            (v: number | null) => setRangeMax(def, v)
                        "
                    />
                </div>
                <div
                    v-else-if="def.kind === 'date'"
                    class="flex items-center gap-2"
                >
                    <input
                        type="date"
                        :value="dateOf(def.key).from ?? ''"
                        class="rounded-md border border-input bg-background px-2 py-1 text-sm"
                        @input="
                            (e) =>
                                setDateFrom(
                                    def,
                                    (e.target as HTMLInputElement).value,
                                )
                        "
                    />
                    <span class="text-muted-foreground">→</span>
                    <input
                        type="date"
                        :value="dateOf(def.key).to ?? ''"
                        class="rounded-md border border-input bg-background px-2 py-1 text-sm"
                        @input="
                            (e) =>
                                setDateTo(
                                    def,
                                    (e.target as HTMLInputElement).value,
                                )
                        "
                    />
                </div>
                <ToggleSwitch
                    v-else-if="def.kind === 'boolean'"
                    :model-value="values[def.key] === true"
                    @update:model-value="(v: boolean) => setFilter(def, v)"
                />
            </div>
        </div>
    </div>
</template>
