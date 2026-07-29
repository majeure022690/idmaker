export type Unit = 'mm' | 'cm' | 'in' | 'px';

export type DesignerTool =
    | 'select'
    | 'text'
    | 'dynamic_text'
    | 'image'
    | 'photo'
    | 'signature'
    | 'qrcode'
    | 'barcode'
    | 'shape'
    | 'background';

export type ElementType =
    | 'text'
    | 'dynamic_text'
    | 'image'
    | 'photo'
    | 'signature'
    | 'qrcode'
    | 'barcode'
    | 'shape';

export interface DesignElementBase {
    id: string;
    type: ElementType;
    x: number;
    y: number;
    width: number;
    height: number;
    rotation: number;
    opacity: number;
}

export interface TextStyle {
    fontFamily: string;
    fontSize: number;
    fontWeight: 'normal' | 'bold';
    fontStyle: 'normal' | 'italic';
    color: string;
    textAlign: 'left' | 'center' | 'right';
    lineHeight: number;
    letterSpacing: number;
}

export interface TextElement extends DesignElementBase, TextStyle {
    type: 'text';
    content: string;
}

export interface DynamicTextElement extends DesignElementBase, TextStyle {
    type: 'dynamic_text';
    field: string;
}

export interface ImageElement extends DesignElementBase {
    type: 'image';
    source: string;
}

export interface PhotoElement extends DesignElementBase {
    type: 'photo';
    field: string;
}

export interface SignatureElement extends DesignElementBase {
    type: 'signature';
    field: string;
}

export type CodeFormat = 'CODE128' | 'CODE39' | 'EAN13' | 'EAN8' | 'UPC';

export interface QrCodeElement extends DesignElementBase {
    type: 'qrcode';
    value: string;
    foreground: string;
    background: string;
}

export interface BarcodeElement extends DesignElementBase {
    type: 'barcode';
    value: string;
    format: CodeFormat;
    foreground: string;
    background: string;
}

export type ShapeKind = 'rectangle' | 'circle' | 'line';

export interface ShapeElement extends DesignElementBase {
    type: 'shape';
    shape: ShapeKind;
    fill: string;
    stroke: string;
    strokeWidth: number;
}

export type DesignElement =
    | TextElement
    | DynamicTextElement
    | ImageElement
    | PhotoElement
    | SignatureElement
    | QrCodeElement
    | BarcodeElement
    | ShapeElement;

export interface CardBackground {
    type: 'color' | 'image';
    value: string;
}

export interface CardSide {
    background: CardBackground;
    elements: DesignElement[];
}

export interface TemplateDesign {
    width: number;
    height: number;
    unit: Unit;
    front: CardSide;
    back: CardSide | null;
}

export interface TemplateSummary {
    id: number;
    name: string;
    width: string;
    height: string;
    unit: Unit;
    has_back: boolean;
    updated_at: string;
}

export interface TemplateRecord extends TemplateSummary {
    front_design: CardSide;
    back_design: CardSide | null;
}
