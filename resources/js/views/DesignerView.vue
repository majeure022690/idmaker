<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
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

// Unsaved-changes guard: leaving the designer (via the router, e.g. the
// "← Templates" link or any other in-app navigation) while dirty pauses the
// navigation and asks the user to save or discard, instead of silently
// losing edits.
const showLeaveModal = ref(false);
let resolveLeave: ((allow: boolean) => void) | null = null;

onBeforeRouteLeave(() => {
    if (!store.dirty) return true;
    showLeaveModal.value = true;
    return new Promise<boolean>((resolve) => {
        resolveLeave = resolve;
    });
});

async function saveAndLeave(): Promise<void> {
    await save();
    showLeaveModal.value = false;
    resolveLeave?.(true);
    resolveLeave = null;
}

function discardAndLeave(): void {
    showLeaveModal.value = false;
    resolveLeave?.(true);
    resolveLeave = null;
}

function cancelLeave(): void {
    showLeaveModal.value = false;
    resolveLeave?.(false);
    resolveLeave = null;
}

// Closing the tab or refreshing bypasses the router entirely, so it needs
// its own warning via the browser's native "leave site?" prompt.
function handleBeforeUnload(event: BeforeUnloadEvent): void {
    if (!store.dirty) return;
    event.preventDefault();
    event.returnValue = '';
}

onMounted(() => window.addEventListener('beforeunload', handleBeforeUnload));
onBeforeUnmount(() => window.removeEventListener('beforeunload', handleBeforeUnload));

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
                <div class="flex items-center gap-1 border-b border-slate-200 bg-white px-4 py-1 text-xs text-slate-500">
                    <input
                        :value="store.width"
                        type="number"
                        step="0.1"
                        min="1"
                        title="Card width (mm)"
                        class="w-14 rounded border border-transparent px-1 py-0.5 text-xs hover:border-slate-300 focus:border-slate-400 focus:outline-none"
                        @change="store.resize(Number(($event.target as HTMLInputElement).value), store.height)"
                    />
                    <span>×</span>
                    <input
                        :value="store.height"
                        type="number"
                        step="0.1"
                        min="1"
                        title="Card height (mm)"
                        class="w-14 rounded border border-transparent px-1 py-0.5 text-xs hover:border-slate-300 focus:border-slate-400 focus:outline-none"
                        @change="store.resize(store.width, Number(($event.target as HTMLInputElement).value))"
                    />
                    <span>mm · {{ store.side === 'front' ? 'Front' : 'Back' }}</span>
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

        <div v-if="showLeaveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-2 text-lg font-semibold">Unsaved changes</h2>
                <p class="mb-6 text-sm text-slate-600">You have unsaved changes on this template. Save them before leaving, or discard them?</p>
                <div class="flex justify-end gap-2">
                    <button class="rounded px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="cancelLeave">Cancel</button>
                    <button class="rounded px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50" @click="discardAndLeave">Discard</button>
                    <button
                        class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                        :disabled="store.saving"
                        @click="saveAndLeave"
                    >
                        {{ store.saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
