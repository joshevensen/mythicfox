export type FilterKind =
    | 'text'
    | 'enum'
    | 'select'
    | 'range'
    | 'date'
    | 'boolean';

export type FilterOption = {
    value: string;
    label: string;
};

export type FilterDef = {
    kind: FilterKind;
    key: string;
    label: string;
    options?: FilterOption[];
    /** Enum only: show a type-to-narrow search box in the dropdown. Default false. */
    searchable?: boolean;
    /** Render the control disabled (enum and select). Default false. */
    disabled?: boolean;
    /** Placeholder shown while disabled; falls back to `label`. */
    disabledPlaceholder?: string;
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
