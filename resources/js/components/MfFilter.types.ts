export type FilterKind = 'text' | 'enum' | 'range' | 'date' | 'boolean';

export type FilterOption = {
    value: string;
    label: string;
};

export type FilterDef = {
    kind: FilterKind;
    key: string;
    label: string;
    options?: FilterOption[];
};

export type FilterValue =
    | string
    | string[]
    | { min?: string; max?: string }
    | { from?: string; to?: string }
    | boolean
    | null;

export type ActiveFilter = {
    key: string;
    label: string;
    display: string;
    raw: FilterValue;
};
