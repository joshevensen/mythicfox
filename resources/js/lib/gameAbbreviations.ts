type GameRule = { test: (name: string) => boolean; abbr: string };

const GAME_RULES: GameRule[] = [
    { test: s => s.includes('lorcana'), abbr: 'Lor' },
    { test: s => s.includes('magic'), abbr: 'MtG' },
    { test: s => s.includes('flesh') && s.includes('blood'), abbr: 'FaB' },
];

export function abbreviateGame(name: string): string {
    const normalized = name.toLowerCase();

    for (const rule of GAME_RULES) {
        if (rule.test(normalized)) {
            return rule.abbr;
        }
    }

    return name.split(/\s+/)[0].slice(0, 4);
}
