export const COPYRIGHT_LAUNCH_YEAR = 2025;

export function copyrightYearLabel(currentYear: number): string {
    return currentYear <= COPYRIGHT_LAUNCH_YEAR
        ? String(COPYRIGHT_LAUNCH_YEAR)
        : `${COPYRIGHT_LAUNCH_YEAR} – ${currentYear}`;
}
