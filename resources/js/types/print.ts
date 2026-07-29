export type PaperPreset = 'A4' | 'Letter' | 'Legal' | 'A5' | 'Custom';
export type PrintSide = 'front' | 'back' | 'both';

export interface PrintConfig {
    paper: PaperPreset;
    custom_width?: number;
    custom_height?: number;
    orientation: 'portrait' | 'landscape';
    margin_top: number;
    margin_bottom: number;
    margin_left: number;
    margin_right: number;
    spacing_x: number;
    spacing_y: number;
    columns?: number | null;
    rows?: number | null;
    side: PrintSide;
}

export interface LayoutPreview {
    columns: number;
    rows: number;
    perPage: number;
    positions: { x: number; y: number }[];
    fits: boolean;
    error: string | null;
    paperWidth: number;
    paperHeight: number;
    cardWidth: number;
    cardHeight: number;
}

export interface GenerationBatch {
    id: number;
    name: string;
    template_id: number;
    template_name: string | null;
    record_ids: number[];
    record_count: number;
    print_config: PrintConfig;
    pdf_path: string | null;
    pdf_url: string | null;
    generated_at: string | null;
    created_at: string | null;
}
