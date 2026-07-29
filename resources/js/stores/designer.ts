import { defineStore } from 'pinia';
import { markRaw, toRaw } from 'vue';
import type {
    BarcodeElement,
    CardSide,
    CodeFormat,
    DesignElement,
    DynamicTextElement,
    ImageElement,
    PhotoElement,
    QrCodeElement,
    ShapeElement,
    ShapeKind,
    SignatureElement,
    TemplateRecord,
    TextElement,
    Unit,
} from '../types/design';
import { HistoryStack } from '../lib/history';

function emptySide(): CardSide {
    return { background: { type: 'color', value: '#FFFFFF' }, elements: [] };
}

interface Snapshot {
    front: CardSide;
    back: CardSide | null;
}

interface DesignerState {
    id: number | null;
    name: string;
    width: number;
    height: number;
    unit: Unit;
    front: CardSide;
    back: CardSide | null;
    side: 'front' | 'back';
    selectedElementId: string | null;
    zoom: number;
    history: HistoryStack<Snapshot> | null;
    canUndo: boolean;
    canRedo: boolean;
    saving: boolean;
    dirty: boolean;
    structureVersion: number;
    showGrid: boolean;
    snapToGrid: boolean;
    gridSizeMm: number;
}

export const useDesignerStore = defineStore('designer', {
    state: (): DesignerState => ({
        id: null,
        name: '',
        width: 85.6,
        height: 54,
        unit: 'mm',
        front: emptySide(),
        back: null,
        side: 'front',
        selectedElementId: null,
        zoom: 1,
        history: null,
        canUndo: false,
        canRedo: false,
        saving: false,
        dirty: false,
        structureVersion: 0,
        showGrid: false,
        snapToGrid: false,
        gridSizeMm: 5,
    }),

    getters: {
        currentSide(state): CardSide {
            return state.side === 'front' ? state.front : (state.back ?? emptySide());
        },
        selectedElement(state): DesignElement | null {
            const elements = state.side === 'front' ? state.front.elements : (state.back?.elements ?? []);
            return elements.find((el) => el.id === state.selectedElementId) ?? null;
        },
    },

    actions: {
        loadFromTemplate(record: TemplateRecord): void {
            this.id = record.id;
            this.name = record.name;
            this.width = Number(record.width);
            this.height = Number(record.height);
            this.unit = record.unit;
            this.front = record.front_design;
            this.back = record.back_design;
            this.side = 'front';
            this.selectedElementId = null;
            this.history = markRaw(new HistoryStack<Snapshot>(this.snapshot()));
            this.dirty = false;
            this.syncHistoryFlags();
        },

        newTemplate(name: string, width: number, height: number, unit: Unit, hasBack: boolean): void {
            this.id = null;
            this.name = name;
            this.width = width;
            this.height = height;
            this.unit = unit;
            this.front = emptySide();
            this.back = hasBack ? emptySide() : null;
            this.side = 'front';
            this.selectedElementId = null;
            this.history = markRaw(new HistoryStack<Snapshot>(this.snapshot()));
            this.dirty = false;
            this.syncHistoryFlags();
        },

        setSide(side: 'front' | 'back'): void {
            this.side = side;
            this.selectedElementId = null;
        },

        setShowGrid(value: boolean): void {
            this.showGrid = value;
        },

        setSnapToGrid(value: boolean): void {
            this.snapToGrid = value;
        },

        setGridSize(mm: number): void {
            this.gridSizeMm = mm;
        },

        toggleBack(hasBack: boolean): void {
            if (hasBack && !this.back) {
                this.back = emptySide();
            } else if (!hasBack) {
                this.back = null;
                if (this.side === 'back') this.side = 'front';
            }
            this.structureVersion++;
            this.commit();
        },

        setBackground(color: string): void {
            this.currentSideMutable().background = { type: 'color', value: color };
            this.structureVersion++;
            this.commit();
        },

        setBackgroundImage(path: string): void {
            this.currentSideMutable().background = { type: 'image', value: path };
            this.structureVersion++;
            this.commit();
        },

        addTextElement(x: number, y: number): TextElement {
            const element: TextElement = {
                id: crypto.randomUUID(),
                type: 'text',
                x,
                y,
                width: 40,
                height: 8,
                rotation: 0,
                opacity: 1,
                content: 'New Text',
                fontFamily: 'Arial',
                fontSize: 10,
                fontWeight: 'normal',
                fontStyle: 'normal',
                color: '#000000',
                textAlign: 'left',
                lineHeight: 1.16,
                letterSpacing: 0,
            };
            this.currentSideMutable().elements.push(element);
            this.selectedElementId = element.id;
            this.structureVersion++;
            this.commit();
            return element;
        },

        addDynamicTextElement(x: number, y: number, field: string): DynamicTextElement {
            const element: DynamicTextElement = {
                id: crypto.randomUUID(),
                type: 'dynamic_text',
                x,
                y,
                width: 40,
                height: 8,
                rotation: 0,
                opacity: 1,
                field,
                fontFamily: 'Arial',
                fontSize: 12,
                fontWeight: 'bold',
                fontStyle: 'normal',
                color: '#000000',
                textAlign: 'left',
                lineHeight: 1.16,
                letterSpacing: 0,
            };
            this.currentSideMutable().elements.push(element);
            this.selectedElementId = element.id;
            this.structureVersion++;
            this.commit();
            return element;
        },

        addImageElement(x: number, y: number, width: number, height: number, source: string): ImageElement {
            const element: ImageElement = {
                id: crypto.randomUUID(),
                type: 'image',
                x,
                y,
                width,
                height,
                rotation: 0,
                opacity: 1,
                source,
            };
            this.currentSideMutable().elements.push(element);
            this.selectedElementId = element.id;
            this.structureVersion++;
            this.commit();
            return element;
        },

        addPhotoElement(x: number, y: number, field = 'photo'): PhotoElement {
            const element: PhotoElement = {
                id: crypto.randomUUID(),
                type: 'photo',
                x,
                y,
                width: 20,
                height: 25,
                rotation: 0,
                opacity: 1,
                field,
            };
            this.currentSideMutable().elements.push(element);
            this.selectedElementId = element.id;
            this.structureVersion++;
            this.commit();
            return element;
        },

        addSignatureElement(x: number, y: number, field = 'signature'): SignatureElement {
            const element: SignatureElement = {
                id: crypto.randomUUID(),
                type: 'signature',
                x,
                y,
                width: 30,
                height: 12,
                rotation: 0,
                opacity: 1,
                field,
            };
            this.currentSideMutable().elements.push(element);
            this.selectedElementId = element.id;
            this.structureVersion++;
            this.commit();
            return element;
        },

        addQrCodeElement(x: number, y: number, value = '{{id_number}}'): QrCodeElement {
            const element: QrCodeElement = {
                id: crypto.randomUUID(),
                type: 'qrcode',
                x,
                y,
                width: 15,
                height: 15,
                rotation: 0,
                opacity: 1,
                value,
                foreground: '#000000',
                background: '#ffffff',
            };
            this.currentSideMutable().elements.push(element);
            this.selectedElementId = element.id;
            this.structureVersion++;
            this.commit();
            return element;
        },

        addBarcodeElement(x: number, y: number, value = '{{id_number}}', format: CodeFormat = 'CODE128'): BarcodeElement {
            const element: BarcodeElement = {
                id: crypto.randomUUID(),
                type: 'barcode',
                x,
                y,
                width: 35,
                height: 15,
                rotation: 0,
                opacity: 1,
                value,
                format,
                foreground: '#000000',
                background: '#ffffff',
            };
            this.currentSideMutable().elements.push(element);
            this.selectedElementId = element.id;
            this.structureVersion++;
            this.commit();
            return element;
        },

        addShapeElement(x: number, y: number, shape: ShapeKind = 'rectangle'): ShapeElement {
            const element: ShapeElement = {
                id: crypto.randomUUID(),
                type: 'shape',
                x,
                y,
                width: shape === 'line' ? 30 : 20,
                height: shape === 'line' ? 1 : 20,
                rotation: 0,
                opacity: 1,
                shape,
                fill: shape === 'line' ? '#000000' : '#e2e8f0',
                stroke: '#000000',
                strokeWidth: 1,
            };
            this.currentSideMutable().elements.push(element);
            this.selectedElementId = element.id;
            this.structureVersion++;
            this.commit();
            return element;
        },

        updateElement(id: string, patch: Partial<DesignElement>, record = true): void {
            const elements = this.currentSideMutable().elements;
            const index = elements.findIndex((el) => el.id === id);
            if (index === -1) return;
            Object.assign(elements[index], patch);
            if (record) this.commit();
            else this.dirty = true;
        },

        duplicateElement(id: string): void {
            const elements = this.currentSideMutable().elements;
            const original = elements.find((el) => el.id === id);
            if (!original) return;
            const copy = { ...original, id: crypto.randomUUID(), x: original.x + 5, y: original.y + 5 } as DesignElement;
            elements.push(copy);
            this.selectedElementId = copy.id;
            this.structureVersion++;
            this.commit();
        },

        removeElement(id: string): void {
            const elements = this.currentSideMutable().elements;
            const index = elements.findIndex((el) => el.id === id);
            if (index === -1) return;
            elements.splice(index, 1);
            if (this.selectedElementId === id) this.selectedElementId = null;
            this.structureVersion++;
            this.commit();
        },

        reorderElement(id: string, to: 'front' | 'back' | 'forward' | 'backward'): void {
            const elements = this.currentSideMutable().elements;
            const from = elements.findIndex((el) => el.id === id);
            if (from === -1) return;

            let target = from;
            if (to === 'front') target = elements.length - 1;
            else if (to === 'back') target = 0;
            else if (to === 'forward') target = Math.min(elements.length - 1, from + 1);
            else if (to === 'backward') target = Math.max(0, from - 1);
            if (target === from) return;

            const [moved] = elements.splice(from, 1);
            elements.splice(target, 0, toRaw(moved));
            this.structureVersion++;
            this.commit();
        },

        select(id: string | null): void {
            this.selectedElementId = id;
        },

        undo(): void {
            const snapshot = this.history?.undo();
            if (!snapshot) return;
            this.applySnapshot(snapshot);
            this.syncHistoryFlags();
        },

        redo(): void {
            const snapshot = this.history?.redo();
            if (!snapshot) return;
            this.applySnapshot(snapshot);
            this.syncHistoryFlags();
        },

        commit(): void {
            this.history?.push(this.snapshot());
            this.dirty = true;
            this.syncHistoryFlags();
        },

        syncHistoryFlags(): void {
            this.canUndo = this.history?.canUndo ?? false;
            this.canRedo = this.history?.canRedo ?? false;
        },

        markSaved(id: number): void {
            this.id = id;
            this.dirty = false;
        },

        currentSideMutable(): CardSide {
            if (this.side === 'back') {
                if (!this.back) this.back = emptySide();
                return this.back;
            }
            return this.front;
        },

        snapshot(): Snapshot {
            return {
                front: structuredClone(toRaw(this.front)),
                back: this.back ? structuredClone(toRaw(this.back)) : null,
            };
        },

        applySnapshot(snapshot: Snapshot): void {
            this.front = snapshot.front;
            this.back = snapshot.back;
            if (this.side === 'back' && !this.back) this.side = 'front';
            this.selectedElementId = null;
            this.structureVersion++;
        },
    },
});
