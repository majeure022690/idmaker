<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { useDesignerStore } from '../../stores/designer';
import { autoWrapFieldTemplate, dynamicFieldPreviewText } from '../../lib/dynamicField';
import type { CodeFormat, DesignElement, ShapeKind } from '../../types/design';

const emit = defineEmits<{ (e: 'align', edge: 'left' | 'right' | 'top' | 'bottom' | 'centerH' | 'centerV'): void }>();

const store = useDesignerStore();

const form = reactive({
    x: 0,
    y: 0,
    width: 0,
    height: 0,
    rotation: 0,
    opacity: 1,
    content: '',
    field: '',
    fontFamily: 'Arial',
    fontSize: 12,
    fontWeight: 'normal' as 'normal' | 'bold',
    fontStyle: 'normal' as 'normal' | 'italic',
    textAlign: 'left' as 'left' | 'center' | 'right',
    color: '#000000',
    lineHeight: 1.16,
    letterSpacing: 0,
    value: '',
    format: 'CODE128' as CodeFormat,
    foreground: '#000000',
    background: '#ffffff',
    shape: 'rectangle' as ShapeKind,
    fill: '#e2e8f0',
    stroke: '#000000',
    strokeWidth: 1,
});

const fieldPlaceholder = computed(() => dynamicFieldPreviewText(form.field || 'field'));

const typeLabels: Record<DesignElement['type'], string> = {
    text: 'Text',
    dynamic_text: 'Dynamic Field',
    image: 'Image',
    photo: 'Photo',
    signature: 'Signature',
    qrcode: 'QR Code',
    barcode: 'Barcode',
    shape: 'Shape',
};

function elementLabel(el: DesignElement): string {
    if (el.type === 'text') return el.content || 'Text';
    if (el.type === 'dynamic_text') return dynamicFieldPreviewText(el.field);
    if (el.type === 'photo' || el.type === 'signature') return `${typeLabels[el.type]}: ${el.field}`;
    return typeLabels[el.type];
}

function syncFromElement(el: DesignElement | null): void {
    if (!el) return;
    form.x = round(el.x);
    form.y = round(el.y);
    form.width = round(el.width);
    form.height = round(el.height);
    form.rotation = round(el.rotation);
    form.opacity = el.opacity;

    if (el.type === 'text' || el.type === 'dynamic_text') {
        form.fontFamily = el.fontFamily;
        form.fontSize = el.fontSize;
        form.fontWeight = el.fontWeight;
        form.fontStyle = el.fontStyle;
        form.textAlign = el.textAlign;
        form.color = el.color;
        form.lineHeight = el.lineHeight;
        form.letterSpacing = el.letterSpacing;
        if (el.type === 'text') form.content = el.content;
        if (el.type === 'dynamic_text') form.field = el.field;
    } else if (el.type === 'photo' || el.type === 'signature') {
        form.field = el.field;
    } else if (el.type === 'qrcode' || el.type === 'barcode') {
        form.value = el.value;
        form.foreground = el.foreground;
        form.background = el.background;
        if (el.type === 'barcode') form.format = el.format;
    } else if (el.type === 'shape') {
        form.shape = el.shape;
        form.fill = el.fill;
        form.stroke = el.stroke;
        form.strokeWidth = el.strokeWidth;
    }
}

function round(n: number): number {
    return Math.round(n * 100) / 100;
}

// deep: true is required — the `selectedElement` getter only reads `.id` while
// finding the match, so Vue's shallow dependency tracking never notices when
// x/width/etc. change via in-place mutation (e.g. a canvas drag). Deep forces
// Vue to also track those nested fields.
watch(() => store.selectedElement, syncFromElement, { immediate: true, deep: true });

function commit(): void {
    const el = store.selectedElement;
    if (!el) return;

    const patch: Partial<DesignElement> = {
        x: form.x,
        y: form.y,
        width: Math.max(1, form.width),
        height: Math.max(1, form.height),
        rotation: form.rotation,
        opacity: Math.min(1, Math.max(0, form.opacity)),
    };

    if (el.type === 'text') {
        Object.assign(patch, {
            content: form.content,
            fontFamily: form.fontFamily,
            fontSize: form.fontSize,
            fontWeight: form.fontWeight,
            fontStyle: form.fontStyle,
            textAlign: form.textAlign,
            color: form.color,
            lineHeight: form.lineHeight,
            letterSpacing: form.letterSpacing,
        });
    } else if (el.type === 'dynamic_text') {
        Object.assign(patch, {
            field: autoWrapFieldTemplate(form.field),
            fontFamily: form.fontFamily,
            fontSize: form.fontSize,
            fontWeight: form.fontWeight,
            fontStyle: form.fontStyle,
            textAlign: form.textAlign,
            color: form.color,
            lineHeight: form.lineHeight,
            letterSpacing: form.letterSpacing,
        });
    } else if (el.type === 'photo' || el.type === 'signature') {
        Object.assign(patch, { field: form.field });
    } else if (el.type === 'qrcode') {
        Object.assign(patch, { value: autoWrapFieldTemplate(form.value), foreground: form.foreground, background: form.background });
    } else if (el.type === 'barcode') {
        Object.assign(patch, { value: autoWrapFieldTemplate(form.value), format: form.format, foreground: form.foreground, background: form.background });
    } else if (el.type === 'shape') {
        Object.assign(patch, { shape: form.shape, fill: form.fill, stroke: form.stroke, strokeWidth: form.strokeWidth });
    }

    store.updateElement(el.id, patch);
}

function duplicate(): void {
    if (store.selectedElement) store.duplicateElement(store.selectedElement.id);
}

function remove(): void {
    if (store.selectedElement) store.removeElement(store.selectedElement.id);
}
</script>

<template>
    <div class="flex w-72 shrink-0 flex-col overflow-y-auto border-l border-slate-200 bg-white">
        <div class="border-b border-slate-200 p-4">
            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Layers</h3>
            <p v-if="store.currentSide.elements.length === 0" class="text-xs text-slate-400">No elements yet.</p>
            <ul v-else class="flex flex-col-reverse gap-1">
                <li
                    v-for="el in store.currentSide.elements"
                    :key="el.id"
                    class="flex items-center justify-between rounded px-2 py-1 text-xs"
                    :class="el.id === store.selectedElementId ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
                >
                    <button class="flex-1 truncate text-left" @click="store.select(el.id)">{{ elementLabel(el) }}</button>
                    <div class="flex shrink-0 gap-0.5">
                        <button title="Bring forward" class="px-1 opacity-80 hover:opacity-100" @click="store.reorderElement(el.id, 'forward')">▲</button>
                        <button title="Send backward" class="px-1 opacity-80 hover:opacity-100" @click="store.reorderElement(el.id, 'backward')">▼</button>
                    </div>
                </li>
            </ul>
        </div>

        <div v-if="!store.selectedElement" class="p-4 text-sm text-slate-400">Select an element to edit its properties.</div>

        <div v-else class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ typeLabels[store.selectedElement.type] }}</h3>
                <div class="flex gap-1">
                    <button class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-100" @click="duplicate">Duplicate</button>
                    <button class="rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove">Delete</button>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs text-slate-500">Layer order</label>
                <div class="grid grid-cols-4 gap-1 text-xs">
                    <button class="rounded border border-slate-300 py-1 hover:bg-slate-100" @click="store.reorderElement(store.selectedElement.id, 'front')">Front</button>
                    <button class="rounded border border-slate-300 py-1 hover:bg-slate-100" @click="store.reorderElement(store.selectedElement.id, 'forward')">Forward</button>
                    <button class="rounded border border-slate-300 py-1 hover:bg-slate-100" @click="store.reorderElement(store.selectedElement.id, 'backward')">Backward</button>
                    <button class="rounded border border-slate-300 py-1 hover:bg-slate-100" @click="store.reorderElement(store.selectedElement.id, 'back')">Back</button>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs text-slate-500">Align to card</label>
                <div class="grid grid-cols-3 gap-1 text-xs">
                    <button class="rounded border border-slate-300 py-1 hover:bg-slate-100" @click="emit('align', 'left')">⯇ Left</button>
                    <button class="rounded border border-slate-300 py-1 hover:bg-slate-100" @click="emit('align', 'centerH')">↔ Center</button>
                    <button class="rounded border border-slate-300 py-1 hover:bg-slate-100" @click="emit('align', 'right')">Right ⯈</button>
                    <button class="rounded border border-slate-300 py-1 hover:bg-slate-100" @click="emit('align', 'top')">⯅ Top</button>
                    <button class="rounded border border-slate-300 py-1 hover:bg-slate-100" @click="emit('align', 'centerV')">↕ Middle</button>
                    <button class="rounded border border-slate-300 py-1 hover:bg-slate-100" @click="emit('align', 'bottom')">Bottom ⯆</button>
                </div>
            </div>

            <div v-if="store.selectedElement.type === 'text'">
                <label class="mb-1 block text-xs text-slate-500">Text</label>
                <textarea v-model="form.content" rows="2" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
            </div>

            <div v-if="store.selectedElement.type === 'dynamic_text'">
                <label class="mb-1 block text-xs text-slate-500">Field name(s)</label>
                <input
                    v-model="form.field"
                    type="text"
                    placeholder="full_name  or  barangay, municipality"
                    class="w-full rounded border border-slate-300 px-2 py-1 text-sm"
                    @change="commit"
                />
                <p class="mt-1 text-xs text-slate-400">
                    One field, or several separated by commas — brackets are added automatically. Renders as {{ fieldPlaceholder }}
                </p>
            </div>

            <div v-if="store.selectedElement.type === 'photo' || store.selectedElement.type === 'signature'">
                <label class="mb-1 block text-xs text-slate-500">Record field name</label>
                <input v-model="form.field" type="text" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                <p class="mt-1 text-xs text-slate-400">The uploaded file on each selected record's "{{ form.field }}" field.</p>
            </div>

            <div v-if="store.selectedElement.type === 'qrcode' || store.selectedElement.type === 'barcode'" class="flex flex-col gap-2">
                <label class="block text-xs text-slate-500">
                    Field name(s) to encode
                    <input v-model="form.value" type="text" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                </label>
                <p class="text-xs text-slate-400">The record field whose value gets encoded — brackets are added automatically, so just type the field name (e.g. id_number).</p>
                <label v-if="store.selectedElement.type === 'barcode'" class="block text-xs text-slate-500">
                    Format
                    <select v-model="form.format" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit">
                        <option value="CODE128">CODE128</option>
                        <option value="CODE39">CODE39</option>
                        <option value="EAN13">EAN13</option>
                        <option value="EAN8">EAN8</option>
                        <option value="UPC">UPC</option>
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs text-slate-500">
                        Foreground
                        <input v-model="form.foreground" type="color" class="mt-1 h-8 w-full rounded border border-slate-300" @change="commit" />
                    </label>
                    <label class="text-xs text-slate-500">
                        Background
                        <input v-model="form.background" type="color" class="mt-1 h-8 w-full rounded border border-slate-300" @change="commit" />
                    </label>
                </div>
            </div>

            <div v-if="store.selectedElement.type === 'shape'" class="flex flex-col gap-2">
                <label class="block text-xs text-slate-500">
                    Shape
                    <select v-model="form.shape" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit">
                        <option value="rectangle">Rectangle</option>
                        <option value="circle">Circle / Ellipse</option>
                        <option value="line">Line</option>
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs text-slate-500">
                        Fill
                        <input v-model="form.fill" type="color" class="mt-1 h-8 w-full rounded border border-slate-300" @change="commit" />
                    </label>
                    <label v-if="form.shape !== 'line'" class="text-xs text-slate-500">
                        Border color
                        <input v-model="form.stroke" type="color" class="mt-1 h-8 w-full rounded border border-slate-300" @change="commit" />
                    </label>
                    <label v-if="form.shape !== 'line'" class="text-xs text-slate-500">
                        Border width
                        <input v-model.number="form.strokeWidth" type="number" min="0" step="0.5" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-slate-500">
                    X (mm)
                    <input v-model.number="form.x" type="number" step="0.1" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                </label>
                <label class="text-xs text-slate-500">
                    Y (mm)
                    <input v-model.number="form.y" type="number" step="0.1" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                </label>
                <label class="text-xs text-slate-500">
                    Width (mm)
                    <input v-model.number="form.width" type="number" step="0.1" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                </label>
                <label class="text-xs text-slate-500">
                    Height (mm)
                    <input v-model.number="form.height" type="number" step="0.1" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                </label>
                <label class="text-xs text-slate-500">
                    Rotation (°)
                    <input v-model.number="form.rotation" type="number" step="1" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                </label>
                <label class="text-xs text-slate-500">
                    Opacity
                    <input v-model.number="form.opacity" type="number" min="0" max="1" step="0.05" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                </label>
            </div>

            <template v-if="store.selectedElement.type === 'text' || store.selectedElement.type === 'dynamic_text'">
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs text-slate-500">
                        Font family
                        <input v-model="form.fontFamily" type="text" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                    </label>
                    <label class="text-xs text-slate-500">
                        Font size (pt)
                        <input v-model.number="form.fontSize" type="number" step="0.5" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                    </label>
                    <label class="text-xs text-slate-500">
                        Weight
                        <select v-model="form.fontWeight" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit">
                            <option value="normal">Normal</option>
                            <option value="bold">Bold</option>
                        </select>
                    </label>
                    <label class="text-xs text-slate-500">
                        Style
                        <select v-model="form.fontStyle" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit">
                            <option value="normal">Normal</option>
                            <option value="italic">Italic</option>
                        </select>
                    </label>
                    <label class="text-xs text-slate-500">
                        Align
                        <select v-model="form.textAlign" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit">
                            <option value="left">Left</option>
                            <option value="center">Center</option>
                            <option value="right">Right</option>
                        </select>
                    </label>
                    <label class="text-xs text-slate-500">
                        Color
                        <input v-model="form.color" type="color" class="mt-1 h-8 w-full rounded border border-slate-300" @change="commit" />
                    </label>
                    <label class="text-xs text-slate-500">
                        Line height
                        <input v-model.number="form.lineHeight" type="number" step="0.05" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                    </label>
                    <label class="text-xs text-slate-500">
                        Letter spacing
                        <input v-model.number="form.letterSpacing" type="number" step="10" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" @change="commit" />
                    </label>
                </div>
            </template>
        </div>
    </div>
</template>
