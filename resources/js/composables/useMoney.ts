const formatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

export type FormatCents = (cents: number | null) => string;

export function useMoney(): { formatCents: FormatCents } {
    const formatCents: FormatCents = (cents) => {
        if (cents === null || cents === undefined) {
            return '—';
        }

        return formatter.format(cents / 100);
    };

    return { formatCents };
}
