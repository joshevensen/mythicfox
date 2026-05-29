export function abbreviateCondition(cond: string): string {
    const c = cond.toLowerCase();
    const isFoil = c.includes('foil');

    let abbr: string;

    if (c.startsWith('near mint')) {
        abbr = 'NM';
    } else if (c.startsWith('lightly played')) {
        abbr = 'LP';
    } else if (c.startsWith('moderately played')) {
        abbr = 'MP';
    } else if (c.startsWith('heavily played')) {
        abbr = 'HP';
    } else if (c.startsWith('damaged')) {
        abbr = 'D';
    } else {
        abbr = cond.length <= 3 ? cond : cond.slice(0, 3);
    }

    return isFoil ? `${abbr} Foil` : abbr;
}
