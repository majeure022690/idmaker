<script setup lang="ts">
import { ref } from 'vue';
import { useDesignerStore } from '../../stores/designer';
import { api } from '../../lib/api';
import type { CodeFormat, DesignerTool } from '../../types/design';

const activeTool = defineModel<DesignerTool>('activeTool', { required: true });
const pendingField = defineModel<string>('pendingField', { required: true });
const pendingFormat = defineModel<CodeFormat>('pendingFormat', { required: true });

const store = useDesignerStore();
const bgFileInput = ref<HTMLInputElement | null>(null);
const uploadingBg = ref(false);

const tools: { key: DesignerTool; label: string; hint: string }[] = [
    { key: 'select', label: 'Select', hint: 'Select and edit elements' },
    { key: 'text', label: 'Text', hint: 'Click the canvas to add static text' },
    { key: 'dynamic_text', label: 'Dynamic Field', hint: 'Click the canvas to add a data-bound field' },
    { key: 'image', label: 'Image', hint: 'Click the canvas, then choose a file (logo, seal, etc.)' },
    { key: 'photo', label: 'Photo', hint: "Click the canvas to add a record's profile photo slot" },
    { key: 'signature', label: 'Signature', hint: "Click the canvas to add a record's signature slot" },
    { key: 'qrcode', label: 'QR Code', hint: 'Click the canvas to add a QR code' },
    { key: 'barcode', label: 'Barcode', hint: 'Click the canvas to add a barcode' },
    { key: 'shape', label: 'Shape', hint: 'Click the canvas to add a rectangle/circle/line' },
    { key: 'background', label: 'Background', hint: 'Set a solid color or image background' },
];

const formats: CodeFormat[] = ['CODE128', 'CODE39', 'EAN13', 'EAN8', 'UPC'];

function pickBackgroundImage(): void {
    bgFileInput.value?.click();
}

async function onBackgroundFileSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;

    uploadingBg.value = true;
    try {
        const formData = new FormData();
        formData.append('file', file);
        const { data } = await api.post<{ path: string }>('/uploads', formData);
        store.setBackgroundImage(data.path);
    } finally {
        uploadingBg.value = false;
    }
}
</script>

<template>
    <div class="flex w-48 shrink-0 flex-col gap-1 overflow-y-auto border-r border-slate-200 bg-white p-3">
        <button
            v-for="tool in tools"
            :key="tool.key"
            :title="tool.hint"
            class="rounded-md px-3 py-2 text-left text-sm font-medium"
            :class="activeTool === tool.key ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100'"
            @click="activeTool = tool.key"
        >
            {{ tool.label }}
        </button>

        <label v-if="activeTool === 'dynamic_text'" class="mt-2 block text-xs">
            <span class="mb-1 block text-slate-500">Field name(s)</span>
            <input
                v-model="pendingField"
                type="text"
                placeholder="full_name  or  barangay, municipality"
                class="w-full rounded border border-slate-300 px-2 py-1 text-sm"
            />
            <p class="mt-1 text-slate-400">Separate multiple fields with commas — brackets added automatically.</p>
        </label>

        <label v-if="activeTool === 'photo'" class="mt-2 block text-xs">
            <span class="mb-1 block text-slate-500">Field name</span>
            <input v-model="pendingField" type="text" placeholder="photo" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
        </label>

        <label v-if="activeTool === 'signature'" class="mt-2 block text-xs">
            <span class="mb-1 block text-slate-500">Field name</span>
            <input v-model="pendingField" type="text" placeholder="signature" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
        </label>

        <label v-if="activeTool === 'qrcode'" class="mt-2 block text-xs">
            <span class="mb-1 block text-slate-500">Encoded value</span>
            <input v-model="pendingField" type="text" placeholder="{{id_number}}" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
            <p class="mt-1 text-slate-400">Static text, a single field placeholder, or several joined with a pipe.</p>
        </label>

        <div v-if="activeTool === 'barcode'" class="mt-2 flex flex-col gap-2 text-xs">
            <label class="block">
                <span class="mb-1 block text-slate-500">Encoded value</span>
                <input v-model="pendingField" type="text" placeholder="{{id_number}}" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
            </label>
            <label class="block">
                <span class="mb-1 block text-slate-500">Format</span>
                <select v-model="pendingFormat" class="w-full rounded border border-slate-300 px-2 py-1 text-sm">
                    <option v-for="f in formats" :key="f" :value="f">{{ f }}</option>
                </select>
            </label>
        </div>

        <div v-if="activeTool === 'background'" class="mt-2 flex flex-col gap-2 text-xs">
            <label class="block">
                <span class="mb-1 block text-slate-500">Solid color</span>
                <input
                    type="color"
                    class="h-8 w-full rounded border border-slate-300"
                    :value="store.currentSide.background.type === 'color' ? store.currentSide.background.value : '#ffffff'"
                    @change="store.setBackground(($event.target as HTMLInputElement).value)"
                />
            </label>
            <button
                class="rounded border border-slate-300 px-2 py-1.5 text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                :disabled="uploadingBg"
                @click="pickBackgroundImage"
            >
                {{ uploadingBg ? 'Uploading…' : 'Upload background image' }}
            </button>
            <input ref="bgFileInput" type="file" accept="image/png,image/jpeg,image/svg+xml" class="hidden" @change="onBackgroundFileSelected" />
        </div>
    </div>
</template>
