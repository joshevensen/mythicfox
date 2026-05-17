const dateFormatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
});

const datetimeFormatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
});

export type DateFormat = 'date' | 'datetime';

export type FormatDate = (value: string, format?: DateFormat) => string;

export function useDate(): { formatDate: FormatDate } {
    const formatDate: FormatDate = (value, format = 'date') => {
        if (!value) {
            return '—';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        if (format === 'datetime') {
            return datetimeFormatter
                .format(date)
                .replace(' AM', 'am')
                .replace(' PM', 'pm');
        }

        return dateFormatter.format(date);
    };

    return { formatDate };
}
