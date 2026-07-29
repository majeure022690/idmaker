<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useDesignerStore } from '../../stores/designer';
import { CardCanvas, type PlacementMode } from '../../canvas/CardCanvas';
import { mmToPx } from '../../lib/units';
import { autoWrapFieldTemplate } from '../../lib/dynamicField';
import type { CodeFormat, DesignerTool } from '../../types/design';

const props = defineProps<{
    activeTool: DesignerTool;
    pendingField: string;
    pendingFormat: CodeFormat;
}>();

const emit = defineEmits<{
    (e: 'tool-used'): void;
    (e: 'request-image', xMm: number, yMm: number): void;
}>();

const store = useDesignerStore();
const canvasEl = ref<HTMLCanvasElement | null>(null);
let cardCanvas: CardCanvas | null = null;

const canvasPlacementMode = computed<PlacementMode>(() => (props.activeTool === 'background' ? 'select' : props.activeTool));

const gridStyle = computed(() => {
    if (!store.showGrid) return {};
    const size = mmToPx(store.gridSizeMm) * store.zoom;
    return {
        backgroundImage:
            'linear-gradient(to right, rgba(15,23,42,0.15) 1px, transparent 1px), linear-gradient(to bottom, rgba(15,23,42,0.15) 1px, transparent 1px)',
        backgroundSize: `${size}px ${size}px`,
    };
});

async function rebuild(): Promise<void> {
    if (!cardCanvas) return;
    await cardCanvas.rebuildFromElements(store.currentSide.elements, store.currentSide.background);
    cardCanvas.selectElement(store.selectedElementId);
}

function handleCanvasClick(xMm: number, yMm: number): void {
    switch (props.activeTool) {
        case 'text':
            store.addTextElement(xMm, yMm);
            emit('tool-used');
            break;
        case 'dynamic_text':
            store.addDynamicTextElement(xMm, yMm, autoWrapFieldTemplate(props.pendingField || 'field_name'));
            emit('tool-used');
            break;
        case 'image':
            emit('request-image', xMm, yMm);
            emit('tool-used');
            break;
        case 'photo':
            store.addPhotoElement(xMm, yMm, props.pendingField || 'photo');
            emit('tool-used');
            break;
        case 'signature':
            store.addSignatureElement(xMm, yMm, props.pendingField || 'signature');
            emit('tool-used');
            break;
        case 'qrcode':
            store.addQrCodeElement(xMm, yMm, autoWrapFieldTemplate(props.pendingField || 'id_number'));
            emit('tool-used');
            break;
        case 'barcode':
            store.addBarcodeElement(xMm, yMm, autoWrapFieldTemplate(props.pendingField || 'id_number'), props.pendingFormat);
            emit('tool-used');
            break;
        case 'shape':
            store.addShapeElement(xMm, yMm);
            emit('tool-used');
            break;
    }
}

const NUDGE_MM = 1;
const NUDGE_MM_FAST = 5;
const ARROW_DELTAS: Record<string, [number, number]> = {
    ArrowUp: [0, -1],
    ArrowDown: [0, 1],
    ArrowLeft: [-1, 0],
    ArrowRight: [1, 0],
};

/**
 * Delete/Backspace removes the selected element, and arrow keys nudge it
 * (Shift = bigger step, for covering distance fast) — but only when the user
 * isn't actually typing somewhere (a properties-panel input, the tool
 * sidebar's field-name box, or inline text-editing on the canvas itself,
 * where Backspace must delete a character, not the whole element, and arrow
 * keys must move the text cursor, not the element).
 */
function handleKeyDown(e: KeyboardEvent): void {
    const active = document.activeElement;
    const isTyping = active instanceof HTMLElement && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable);
    if (isTyping || cardCanvas?.isEditingText()) return;

    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (store.selectedElementId) {
            e.preventDefault();
            store.removeElement(store.selectedElementId);
        }
        return;
    }

    const delta = ARROW_DELTAS[e.key];
    const el = store.selectedElement;
    if (delta && el) {
        e.preventDefault();
        const step = e.shiftKey ? NUDGE_MM_FAST : NUDGE_MM;
        store.updateElement(el.id, {
            x: Math.round((el.x + delta[0] * step) * 100) / 100,
            y: Math.round((el.y + delta[1] * step) * 100) / 100,
        });
    }
}

onMounted(() => {
    if (!canvasEl.value) return;
    cardCanvas = new CardCanvas(canvasEl.value, store.width, store.height, {
        onElementChanged: (id, patch) => store.updateElement(id, patch),
        onSelectionChanged: (id) => store.select(id),
        onCanvasClick: handleCanvasClick,
    });
    cardCanvas.setZoom(store.zoom);
    cardCanvas.setPlacementMode(canvasPlacementMode.value);
    cardCanvas.setSnapToGrid(store.snapToGrid, store.gridSizeMm);
    rebuild();
    window.addEventListener('keydown', handleKeyDown);
});

onBeforeUnmount(() => {
    cardCanvas?.dispose();
    window.removeEventListener('keydown', handleKeyDown);
});

// Structural changes (add/remove/duplicate/background/side/undo/redo) require
// a full rebuild. Geometry patches from dragging on the canvas itself do not
// bump structureVersion, so they don't loop back into a rebuild here.
watch(() => [store.side, store.structureVersion], rebuild);

// The selected element's own property edits (from the panel) are pushed onto
// the live fabric object directly — cheap, and idempotent if it also fires
// after a canvas-driven drag (same values re-applied, no visual change).
watch(
    () => store.selectedElement,
    async (el) => {
        if (!el || !cardCanvas) return;
        await cardCanvas.applyElementUpdate(el);

        // Textbox height always auto-fits its wrapped content (e.g. a dynamic
        // field template combining several fields can wrap onto more lines
        // than before) — sync that back so the store/properties panel don't
        // keep showing a stale height from before the wrap. `record: false`
        // keeps this out of undo history since it's a derived correction,
        // not a user edit.
        if (el.type === 'text' || el.type === 'dynamic_text') {
            const actualHeight = cardCanvas.getElementHeightMm(el.id);
            if (actualHeight !== null && Math.abs(actualHeight - el.height) > 0.05) {
                store.updateElement(el.id, { height: actualHeight }, false);
            }
        }
    },
    { deep: true },
);

watch(() => store.selectedElementId, (id) => cardCanvas?.selectElement(id));
watch(() => store.zoom, (zoom) => cardCanvas?.setZoom(zoom));
watch(canvasPlacementMode, (mode) => cardCanvas?.setPlacementMode(mode));
watch([() => store.snapToGrid, () => store.gridSizeMm], ([enabled, size]) => cardCanvas?.setSnapToGrid(enabled, size));

defineExpose({
    alignActive: (edge: 'left' | 'right' | 'top' | 'bottom' | 'centerH' | 'centerV') => cardCanvas?.alignActive(edge),
});
</script>

<template>
    <div class="flex-1 overflow-auto bg-slate-200 p-10">
        <div class="mx-auto w-fit bg-white shadow-lg" :style="gridStyle">
            <canvas ref="canvasEl"></canvas>
        </div>
    </div>
</template>
