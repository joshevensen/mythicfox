export const IMB_BAR_COUNT = 65;
export const IMB_BAR_PITCH = 1 / 22;
export const IMB_BAR_WIDTH = 0.02;
export const IMB_HEIGHT = 0.145;
export const IMB_TRACKER_HEIGHT = 0.05;
export const IMB_WIDTH = (IMB_BAR_COUNT - 1) * IMB_BAR_PITCH + IMB_BAR_WIDTH;

export type ImbBar = { x: number; y: number; width: number; height: number };

export function imbBars(barcode: string): ImbBar[] {
    const trackerTop = (IMB_HEIGHT - IMB_TRACKER_HEIGHT) / 2;
    const trackerBottom = trackerTop + IMB_TRACKER_HEIGHT;
    const bars: ImbBar[] = [];

    [...barcode].forEach((barState, index) => {
        const x = index * IMB_BAR_PITCH;
        let y: number;
        let height: number;

        switch (barState) {
            case 'F':
                y = 0;
                height = IMB_HEIGHT;
                break;
            case 'A':
                y = 0;
                height = trackerBottom;
                break;
            case 'D':
                y = trackerTop;
                height = IMB_HEIGHT - trackerTop;
                break;
            case 'T':
                y = trackerTop;
                height = IMB_TRACKER_HEIGHT;
                break;
            default:
                return;
        }

        bars.push({ x, y, width: IMB_BAR_WIDTH, height });
    });

    return bars;
}
