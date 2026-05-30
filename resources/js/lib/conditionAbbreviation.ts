const GRADES: [string, string][] = [
    ['near mint', 'NM'],
    ['lightly played', 'LP'],
    ['moderately played', 'MP'],
    ['heavily played', 'HP'],
    ['damaged', 'D'],
];

export function abbreviateCondition(cond: string): string {
    const c = cond.toLowerCase();

    for (const [prefix, abbr] of GRADES) {
        if (c.startsWith(prefix)) {
            const suffix = cond.slice(prefix.length).trim();

            return suffix ? `${abbr} ${suffix}` : abbr;
        }
    }

    return cond.length <= 3 ? cond : cond.slice(0, 3);
}
