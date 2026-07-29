<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useDesignerStore } from '../stores/designer';
import { useTemplatesStore } from '../stores/templates';
import { api } from '../lib/api';
import { pxToMm } from '../lib/units';
import { resolveStorageUrl } from '../lib/storage';
import Toolbar from '../components/designer/Toolbar.vue';
import LeftToolbar from '../components/designer/LeftToolbar.vue';
import PropertiesPanel from '../components/designer/PropertiesPanel.vue';
import CanvasStage from '../components/designer/CanvasStage.vue';
import type { CodeFormat, DesignerTool } from '../types/design';

const props = defineProps<{ id: string }>();

const store = useDesignerStore();
const templates = useTemplatesStore();
const loading = ref(true);
const activeTool = ref<DesignerTool>('select');
const pendingField = ref('full_name');
const pendingFormat = ref<CodeFormat>('CODE128');
const fileInput = ref<HTMLInputElement | null>(null);
const pendingImageSpot = ref<{ x: number; y: number } | null>(null);
const canvasStage = ref<InstanceType<typeof CanvasStage> | null>(null);

onMounted(async () => {
    const record = await templates.fetch(Number(props.id));
    store.loadFromTemplate(record);
    loading.value = false;
});

async function save(): Promise<void> {
    store.saving = true;
    try {
        const payload = {
            name: store.name,
            width: store.width,
            height: store.height,
            unit: store.unit,
            has_back: store.back !== null,
            front_design: store.front,
            back_design: store.back,
        };
        if (store.id) {
            await api.put(`/templates/${store.id}`, payload);
            store.markSaved(store.id);
        }
    } finally {
        store.saving = false;
    }
}

function requestImage(xMm: number, yMm: number): void {
    pendingImageSpot.value = { x: xMm, y: yMm };
    fileInput.value?.click();
}

function loadImageSize(url: string): Promise<{ width: number; height: number }> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve({ width: img.naturalWidth, height: img.naturalHeight });
        img.onerror = reject;
        img.src = url;
    });
}

async function onFileSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    const spot = pendingImageSpot.value;
    input.value = '';
    if (!file || !spot) return;

    const formData = new FormData();
    formData.append('file', file);
    const { data } = await api.post<{ path: string; url: string }>('/uploads', formData);

    const natural = await loadImageSize(resolveStorageUrl(data.path));
    const maxWidthMm = 25;
    const widthMm = Math.min(maxWidthMm, pxToMm(natural.width));
    const heightMm = widthMm * (natural.height / natural.width);

    store.addImageElement(spot.x, spot.y, widthMm, heightMm, data.path);
    activeTool.value = 'select';
}
</script>

<template>
    <div v-if="loading" class="flex h-screen items-center justify-center text-slate-400">Loading template…</div>

    <div v-else class="flex h-screen flex-col">
        <Toolbar @save="save" />

        <div class="flex min-h-0 flex-1">
            <LeftToolbar v-model:active-tool="activeTool" v-model:pending-field="pendingField" v-model:pending-format="pendingFormat" />

            <div class="flex flex-1 flex-col">
                <div class="border-b border-slate-200 bg-white px-4 py-1 text-xs text-slate-500">
                    {{ store.width }} × {{ store.height }} mm · {{ store.side === 'front' ? 'Front' : 'Back' }}
                </div>
                <CanvasStage
                    ref="canvasStage"
                    :active-tool="activeTool"
                    :pending-field="pendingField"
                    :pending-format="pendingFormat"
                    @tool-used="activeTool = 'select'"
                    @request-image="requestImage"
                />
            </div>

            <PropertiesPanel @align="(edge) => canvasStage?.alignActive(edge)" />
        </div>

        <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/svg+xml" class="hidden" @change="onFileSelected" />
    </div>
</template>
