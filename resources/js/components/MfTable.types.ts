export type ColumnAlign = 'left' | 'right' | 'center';

export type ColumnDef<TRow = Record<string, unknown>> = {
    key: string;
    label: string;
    sortable?: boolean;
    align?: ColumnAlign;
    formatter?: (value: unknown, row: TRow) => string;
    slot?: string;
};

export type SortDir = 'asc' | 'desc';

export type SortState = {
    field: string;
    dir: SortDir;
} | null;

export type RowAction = 'navigate' | 'modal' | 'none';

export type MfTableMeta = {
    total: number;
    current_page: number;
    per_page: number;
};

export type MfTableResponse<TRow> = {
    data: TRow[];
    meta: MfTableMeta;
};

export type MfTableState = {
    page: number;
    perPage: number;
    sort: SortState;
};

export const PAGE_SIZE_OPTIONS = [25, 50, 100, 200] as const;
export const DEFAULT_PAGE_SIZE = 50;
