import { describe, expect, it } from 'vitest';
import {
    IMB_BAR_COUNT,
    IMB_HEIGHT,
    IMB_TRACKER_HEIGHT,
    IMB_WIDTH,
    imbBars,
} from '@/lib/imbBarcode';

const trackerTop = (IMB_HEIGHT - IMB_TRACKER_HEIGHT) / 2;
const trackerBottom = trackerTop + IMB_TRACKER_HEIGHT;

describe('imbBars', () => {
    it('returns one bar per character of a 65-char barcode', () => {
        expect(imbBars('FADT'.repeat(16) + 'F')).toHaveLength(IMB_BAR_COUNT);
    });

    it('draws a full bar for F', () => {
        const [bar] = imbBars('F');

        expect(bar.y).toBeCloseTo(0);
        expect(bar.height).toBeCloseTo(IMB_HEIGHT);
    });

    it('draws an ascender for A', () => {
        const [bar] = imbBars('A');

        expect(bar.y).toBeCloseTo(0);
        expect(bar.height).toBeCloseTo(trackerBottom);
    });

    it('draws a descender for D', () => {
        const [bar] = imbBars('D');

        expect(bar.y).toBeCloseTo(trackerTop);
        expect(bar.height).toBeCloseTo(IMB_HEIGHT - trackerTop);
    });

    it('draws a tracker for T', () => {
        const [bar] = imbBars('T');

        expect(bar.y).toBeCloseTo(trackerTop);
        expect(bar.height).toBeCloseTo(IMB_TRACKER_HEIGHT);
    });

    it('positions bar i at i / 22 inches', () => {
        const bars = imbBars('FADT'.repeat(16) + 'F');

        bars.forEach((bar, i) => {
            expect(bar.x).toBeCloseTo(i / 22);
        });
    });

    it('skips unknown characters', () => {
        expect(imbBars('FXT')).toHaveLength(2);
    });
});

describe('IMB_WIDTH', () => {
    it('spans 64 pitches plus one bar width and fits in 3.5 inches', () => {
        expect(IMB_WIDTH).toBeCloseTo(64 / 22 + 0.02);
        expect(IMB_WIDTH).toBeLessThan(3.5);
    });
});
