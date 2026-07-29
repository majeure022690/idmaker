import { Canvas, FabricImage, FabricObject, Rect, Textbox, type TPointerEventInfo, type TPointerEvent } from 'fabric';
import QRCode from 'qrcode';
import JsBarcode from 'jsbarcode';
import { mmToPx, pxToMm } from '../lib/units';
import { resolveStorageUrl } from '../lib/storage';
import { dynamicFieldPreviewText } from '../lib/dynamicField';
import type {
    BarcodeElement,
    CardBackground,
    CardSide,
    DesignElement,
    DesignElementBase,
    DesignerTool,
    DynamicTextElement,
    QrCodeElement,
    ShapeElement,
    TextElement,
} from '../types/design';

declare module 'fabric' {
    interface FabricObject {
        elementId?: string;
        isDynamicText?: boolean;
    }
}

const PT_PER_PX = 72 / 96;
const fontSizePxToPt = (px: number) => px * PT_PER_PX;
const fontSizePtToPx = (pt: number) => pt / PT_PER_PX;

type GeometryPatch = Partial<Pick<DesignElementBase, 'x' | 'y' | 'width' | 'height' | 'rotation'>> & { fontSize?: number; content?: string };

export type PlacementMode = Exclude<DesignerTool, 'background'>;

async function generateQrDataUrl(el: QrCodeElement): Promise<string> {
    return QRCode.toDataURL(el.value || ' ', {
        margin: 0,
        color: { dark: el.foreground || '#000000', light: el.background || '#ffffff' },
    });
}

function generateBarcodeDataUrl(el: BarcodeElement): string {
    const canvas = document.createElement('canvas');
    try {
        JsBarcode(canvas, el.value || '0', {
            format: el.format,
            lineColor: el.foreground || '#000000',
            background: el.background || '#ffffff',
            displayValue: false,
            margin: 4,
        });
    } catch {
        canvas.width = 200;
        canvas.height = 80;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.fillStyle = el.background || '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = '#ef4444';
            ctx.lineWidth = 4;
            ctx.strokeRect(2, 2, canvas.width - 4, canvas.height - 4);
        }
    }
    return canvas.toDataURL('image/png');
}

export interface CardCanvasOptions {
    onElementChanged: (id: string, patch: GeometryPatch) => void;
    onSelectionChanged: (id: string | null) => void;
    onCanvasClick: (xMm: number, yMm: number) => void;
}

export class CardCanvas {
    private canvas: Canvas;
    private objectsById = new Map<string, FabricObject>();
    private placementMode: PlacementMode = 'select';
    private widthMm: number;
    private heightMm: number;
    private zoom = 1;
    private snapToGrid = false;
    private gridSizePx = mmToPx(5);

    constructor(el: HTMLCanvasElement, widthMm: number, heightMm: number, private options: CardCanvasOptions) {
        this.widthMm = widthMm;
        this.heightMm = heightMm;
        this.canvas = new Canvas(el, {
            width: mmToPx(widthMm),
            height: mmToPx(heightMm),
            preserveObjectStacking: true,
        });

        this.canvas.on('object:modified', (e) => this.handleModified(e.target));
        this.canvas.on('object:moving', (e) => this.handleMoving(e.target));
        this.canvas.on('selection:created', (e) => this.handleSelection(e.selected));
        this.canvas.on('selection:updated', (e) => this.handleSelection(e.selected));
        this.canvas.on('selection:cleared', () => this.options.onSelectionChanged(null));
        this.canvas.on('mouse:down', (e) => this.handleMouseDown(e));
    }

    setPlacementMode(mode: PlacementMode): void {
        this.placementMode = mode;
        this.canvas.defaultCursor = mode === 'select' ? 'default' : 'crosshair';
        this.canvas.selection = mode === 'select';
    }

    setSnapToGrid(enabled: boolean, gridSizeMm: number): void {
        this.snapToGrid = enabled;
        this.gridSizePx = mmToPx(gridSizeMm);
    }

    alignActive(edge: 'left' | 'right' | 'top' | 'bottom' | 'centerH' | 'centerV'): void {
        const target = this.canvas.getActiveObject();
        if (!target) return;

        const widthPx = (target.width ?? 0) * (target.scaleX ?? 1);
        const heightPx = (target.height ?? 0) * (target.scaleY ?? 1);
        const canvasWidthPx = mmToPx(this.widthMm);
        const canvasHeightPx = mmToPx(this.heightMm);

        switch (edge) {
            case 'left':
                target.set('left', 0);
                break;
            case 'right':
                target.set('left', canvasWidthPx - widthPx);
                break;
            case 'top':
                target.set('top', 0);
                break;
            case 'bottom':
                target.set('top', canvasHeightPx - heightPx);
                break;
            case 'centerH':
                target.set('left', (canvasWidthPx - widthPx) / 2);
                break;
            case 'centerV':
                target.set('top', (canvasHeightPx - heightPx) / 2);
                break;
        }

        target.setCoords();
        this.canvas.requestRenderAll();
        this.handleModified(target);
    }

    setZoom(zoom: number): void {
        this.zoom = zoom;
        this.canvas.setDimensions({ width: mmToPx(this.widthMm) * zoom, height: mmToPx(this.heightMm) * zoom });
        this.canvas.setZoom(zoom);
    }

    resizePhysical(widthMm: number, heightMm: number): void {
        this.widthMm = widthMm;
        this.heightMm = heightMm;
        this.canvas.setDimensions({ width: mmToPx(widthMm) * this.zoom, height: mmToPx(heightMm) * this.zoom });
    }

    async setBackground(background: CardBackground): Promise<void> {
        if (background.type === 'image') {
            const image = await FabricImage.fromURL(resolveStorageUrl(background.value), { crossOrigin: 'anonymous' });
            image.set({
                scaleX: mmToPx(this.widthMm) / (image.width || 1),
                scaleY: mmToPx(this.heightMm) / (image.height || 1),
                originX: 'left',
                originY: 'top',
                left: 0,
                top: 0,
            });
            this.canvas.backgroundColor = '#ffffff';
            this.canvas.backgroundImage = image;
        } else {
            this.canvas.backgroundImage = undefined;
            this.canvas.backgroundColor = background.value;
        }
        this.canvas.requestRenderAll();
    }

    async rebuildFromElements(elements: DesignElement[], background: CardBackground): Promise<void> {
        this.canvas.discardActiveObject();
        this.canvas.clear();
        this.objectsById.clear();
        await this.setBackground(background);

        for (const element of elements) {
            const object = await this.createObject(element);
            if (!object) continue;
            this.objectsById.set(element.id, object);
            this.canvas.add(object);
        }

        this.canvas.requestRenderAll();
    }

    async applyElementUpdate(element: DesignElement): Promise<void> {
        const object = this.objectsById.get(element.id);
        if (!object) return;

        object.set({
            left: mmToPx(element.x),
            top: mmToPx(element.y),
            angle: element.rotation,
            opacity: element.opacity,
        });

        if (object instanceof Textbox && (element.type === 'text' || element.type === 'dynamic_text')) {
            object.set('width', mmToPx(element.width));
            this.applyTextProps(object, element);
        } else if (element.type === 'image' && object instanceof FabricImage) {
            // object.width/height are the source's natural pixel size (never
            // mutated - see handleModified); scale alone controls display size.
            object.set({
                scaleX: mmToPx(element.width) / (object.width || 1),
                scaleY: mmToPx(element.height) / (object.height || 1),
            });
        } else if ((element.type === 'photo' || element.type === 'signature' || element.type === 'shape') && object instanceof Rect) {
            object.set({
                width: mmToPx(element.width),
                height: mmToPx(element.height),
                scaleX: 1,
                scaleY: 1,
            });
            if (element.type === 'shape') {
                object.set({
                    fill: element.fill,
                    stroke: element.shape === 'line' ? null : element.stroke,
                    strokeWidth: element.shape === 'line' ? 0 : mmToPx(element.strokeWidth),
                    rx: element.shape === 'circle' ? Infinity : 0,
                    ry: element.shape === 'circle' ? Infinity : 0,
                });
            }
        } else if (element.type === 'qrcode' && object instanceof FabricImage) {
            const dataUrl = await generateQrDataUrl(element);
            await object.setSrc(dataUrl);
            object.set({
                scaleX: mmToPx(element.width) / (object.width || 1),
                scaleY: mmToPx(element.height) / (object.height || 1),
            });
        } else if (element.type === 'barcode' && object instanceof FabricImage) {
            const dataUrl = generateBarcodeDataUrl(element);
            await object.setSrc(dataUrl);
            object.set({
                scaleX: mmToPx(element.width) / (object.width || 1),
                scaleY: mmToPx(element.height) / (object.height || 1),
            });
        }

        object.setCoords();
        this.canvas.requestRenderAll();
    }

    selectElement(id: string | null): void {
        if (!id) {
            this.canvas.discardActiveObject();
            this.canvas.requestRenderAll();
            return;
        }
        const object = this.objectsById.get(id);
        if (!object) return;
        this.canvas.setActiveObject(object);
        this.canvas.requestRenderAll();
    }

    isEditingText(): boolean {
        const active = this.canvas.getActiveObject();
        return !!(active instanceof Textbox && active.isEditing);
    }

    getElementHeightMm(id: string): number | null {
        const object = this.objectsById.get(id);
        if (!(object instanceof Textbox)) return null;

        return pxToMm(object.height ?? 0);
    }

    dispose(): void {
        this.canvas.dispose();
    }

    private handleMouseDown(e: TPointerEventInfo<TPointerEvent>): void {
        if (this.placementMode === 'select') return;
        const point = e.scenePoint;
        this.options.onCanvasClick(pxToMm(point.x), pxToMm(point.y));
    }

    private handleMoving(target?: FabricObject): void {
        if (!this.snapToGrid || !target) return;
        const grid = this.gridSizePx;
        target.set({
            left: Math.round((target.left ?? 0) / grid) * grid,
            top: Math.round((target.top ?? 0) / grid) * grid,
        });
    }

    private handleSelection(selected?: FabricObject[]): void {
        const id = selected?.[0]?.elementId ?? null;
        this.options.onSelectionChanged(id);
    }

    private handleModified(target?: FabricObject): void {
        if (!target?.elementId) return;

        if (target instanceof Textbox) {
            this.handleTextModified(target);
            return;
        }

        const scaleX = target.scaleX ?? 1;
        const scaleY = target.scaleY ?? 1;
        if (target instanceof FabricImage) {
            // A FabricImage's width/height are the *source* pixel dimensions;
            // scaleX/scaleY are what actually produce the on-screen size.
            // Baking the drag scale into width/height (as done below for
            // shapes) would permanently overwrite that source size, and
            // nothing ever restores it for a plain image (unlike QR/barcode,
            // which reload their source on every property update) - the
            // object silently renders blank from then on. So for images,
            // report the resulting size but leave width/height/scale as
            // fabric's own interactive resize already left them.
            const widthPx = target.getScaledWidth();
            const heightPx = target.getScaledHeight();
            target.setCoords();
            this.options.onElementChanged(target.elementId, {
                x: pxToMm(target.left ?? 0),
                y: pxToMm(target.top ?? 0),
                width: pxToMm(widthPx),
                height: pxToMm(heightPx),
                rotation: target.angle ?? 0,
            });
            return;
        }

        const widthPx = (target.width ?? 0) * scaleX;
        const heightPx = (target.height ?? 0) * scaleY;
        target.set({ width: widthPx, height: heightPx, scaleX: 1, scaleY: 1 });
        target.setCoords();

        this.options.onElementChanged(target.elementId, {
            x: pxToMm(target.left ?? 0),
            y: pxToMm(target.top ?? 0),
            width: pxToMm(widthPx),
            height: pxToMm(heightPx),
            rotation: target.angle ?? 0,
        });
    }

    private handleTextModified(target: Textbox): void {
        if (!target.elementId) return;
        const scaleX = target.scaleX ?? 1;
        const scaleY = target.scaleY ?? 1;

        let fontSize = target.fontSize ?? 12;
        if (scaleY !== 1) {
            fontSize = Math.max(1, Math.round(fontSize * scaleY));
        }
        const widthPx = Math.max(10, (target.width ?? 10) * scaleX);

        target.set({ fontSize, width: widthPx, scaleX: 1, scaleY: 1 });
        target.initDimensions();
        target.setCoords();

        this.options.onElementChanged(target.elementId, {
            x: pxToMm(target.left ?? 0),
            y: pxToMm(target.top ?? 0),
            width: pxToMm(widthPx),
            height: pxToMm(target.height ?? 0),
            rotation: target.angle ?? 0,
            fontSize: fontSizePxToPt(fontSize),
            ...(target.isDynamicText ? {} : { content: target.text }),
        });
    }

    private applyTextProps(object: Textbox, element: TextElement | DynamicTextElement): void {
        object.set({
            text: element.type === 'text' ? element.content : dynamicFieldPreviewText(element.field),
            fontFamily: element.fontFamily,
            fontSize: fontSizePtToPx(element.fontSize),
            fontWeight: element.fontWeight,
            fontStyle: element.fontStyle,
            fill: element.color,
            textAlign: element.textAlign,
            lineHeight: element.lineHeight,
            charSpacing: element.letterSpacing,
        });
    }

    private async createObject(element: DesignElement): Promise<FabricObject | null> {
        if (element.type === 'text' || element.type === 'dynamic_text') {
            const textbox = new Textbox(element.type === 'text' ? element.content : dynamicFieldPreviewText(element.field), {
                left: mmToPx(element.x),
                top: mmToPx(element.y),
                originX: 'left',
                originY: 'top',
                width: mmToPx(element.width),
                angle: element.rotation,
                opacity: element.opacity,
                fontFamily: element.fontFamily,
                fontSize: fontSizePtToPx(element.fontSize),
                fontWeight: element.fontWeight,
                fontStyle: element.fontStyle,
                fill: element.color,
                textAlign: element.textAlign,
                lineHeight: element.lineHeight,
                charSpacing: element.letterSpacing,
                splitByGrapheme: false,
                editable: element.type !== 'dynamic_text',
            });
            textbox.elementId = element.id;
            textbox.isDynamicText = element.type === 'dynamic_text';
            return textbox;
        }

        if (element.type === 'image') {
            const image = await FabricImage.fromURL(resolveStorageUrl(element.source), { crossOrigin: 'anonymous' });
            image.set({
                left: mmToPx(element.x),
                top: mmToPx(element.y),
                originX: 'left',
                originY: 'top',
                angle: element.rotation,
                opacity: element.opacity,
                scaleX: mmToPx(element.width) / (image.width || 1),
                scaleY: mmToPx(element.height) / (image.height || 1),
            });
            image.elementId = element.id;
            return image;
        }

        if (element.type === 'photo' || element.type === 'signature') {
            const rect = new Rect({
                left: mmToPx(element.x),
                top: mmToPx(element.y),
                originX: 'left',
                originY: 'top',
                width: mmToPx(element.width),
                height: mmToPx(element.height),
                angle: element.rotation,
                opacity: element.opacity,
                fill: '#f1f5f9',
                stroke: '#94a3b8',
                strokeWidth: 1,
                strokeDashArray: [6, 4],
            });
            rect.elementId = element.id;
            return rect;
        }

        if (element.type === 'qrcode') {
            const dataUrl = await generateQrDataUrl(element);
            const image = await FabricImage.fromURL(dataUrl);
            image.set({
                left: mmToPx(element.x),
                top: mmToPx(element.y),
                originX: 'left',
                originY: 'top',
                angle: element.rotation,
                opacity: element.opacity,
                scaleX: mmToPx(element.width) / (image.width || 1),
                scaleY: mmToPx(element.height) / (image.height || 1),
            });
            image.elementId = element.id;
            return image;
        }

        if (element.type === 'barcode') {
            const dataUrl = generateBarcodeDataUrl(element);
            const image = await FabricImage.fromURL(dataUrl);
            image.set({
                left: mmToPx(element.x),
                top: mmToPx(element.y),
                originX: 'left',
                originY: 'top',
                angle: element.rotation,
                opacity: element.opacity,
                scaleX: mmToPx(element.width) / (image.width || 1),
                scaleY: mmToPx(element.height) / (image.height || 1),
            });
            image.elementId = element.id;
            return image;
        }

        if (element.type === 'shape') {
            const rect = this.createShapeObject(element);
            rect.elementId = element.id;
            return rect;
        }

        return null;
    }

    private createShapeObject(element: ShapeElement): Rect {
        return new Rect({
            left: mmToPx(element.x),
            top: mmToPx(element.y),
            originX: 'left',
            originY: 'top',
            width: mmToPx(element.width),
            height: mmToPx(element.height),
            angle: element.rotation,
            opacity: element.opacity,
            fill: element.fill,
            stroke: element.shape === 'line' ? null : element.stroke,
            strokeWidth: element.shape === 'line' ? 0 : mmToPx(element.strokeWidth),
            rx: element.shape === 'circle' ? Infinity : 0,
            ry: element.shape === 'circle' ? Infinity : 0,
        });
    }
}

export function defaultCardSide(): CardSide {
    return { background: { type: 'color', value: '#FFFFFF' }, elements: [] };
}
