import type { Unit } from '../types/design';

const PX_PER_MM = 96 / 25.4;

export function mmToPx(mm: number): number {
    return mm * PX_PER_MM;
}

export function pxToMm(px: number): number {
    return px / PX_PER_MM;
}

const MM_PER_UNIT: Record<Unit, number> = {
    mm: 1,
    cm: 10,
    in: 25.4,
    px: 25.4 / 96,
};

export function toMm(value: number, unit: Unit): number {
    return value * MM_PER_UNIT[unit];
}

export function fromMm(mm: number, unit: Unit): number {
    return mm / MM_PER_UNIT[unit];
}

export interface SizePreset {
    label: string;
    width: number;
    height: number;
    unit: Unit;
}

export const SIZE_PRESETS: SizePreset[] = [
    { label: 'CR80 / Standard ID Card (85.6 × 54 mm)', width: 85.6, height: 54, unit: 'mm' },
    { label: 'Credit Card Size (85.6 × 54 mm)', width: 85.6, height: 53.98, unit: 'mm' },
    { label: 'Custom', width: 85.6, height: 54, unit: 'mm' },
];
